<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(UserIndexRequest $request): View
    {
        $filters = $request->validated();

        $users = User::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', ['roles' => UserRole::cases()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'phone', 'password']));
        $user->role = $request->enum('role', UserRole::class);
        $user->save();

        return redirect()->route('admin.users.index')
            ->with('success', __('Account created for :email.', ['email' => $user->email]));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $user->fill($request->safe()->only(['name', 'email', 'phone']));

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            if ($request->user()->can('changeAccess', $user)) {
                $user->role = $request->enum('role', UserRole::class);

                if ($request->exists('is_active')) {
                    $user->is_active = $request->boolean('is_active');
                }

                $this->ensureAnActiveAdminRemains($request->user(), $user);
            }

            $user->save();
        });

        return redirect()->route('admin.users.index')
            ->with('success', __('Account updated for :email.', ['email' => $user->email]));
    }

    /**
     * Demoting or deactivating an admin locks the active admin rows, so two
     * admins editing each other at the same time cannot both succeed.
     */
    private function ensureAnActiveAdminRemains(User $actor, User $user): void
    {
        $wasActiveAdmin = $user->getOriginal('role') === UserRole::Admin && $user->getOriginal('is_active');

        if (! $wasActiveAdmin || ($user->isAdmin() && $user->is_active)) {
            return;
        }

        $activeAdminIds = User::query()
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->lockForUpdate()
            ->pluck('id');

        if (! $activeAdminIds->contains($actor->id)) {
            throw ValidationException::withMessages([
                'role' => __('Your admin access changed while you were editing. Reload the page and try again.'),
            ]);
        }

        if ($activeAdminIds->diff([$user->id])->isEmpty()) {
            throw ValidationException::withMessages([
                'role' => __('This change would leave no active admin.'),
            ]);
        }
    }
}
