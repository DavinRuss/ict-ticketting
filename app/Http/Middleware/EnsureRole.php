<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Allow the request only when the signed-in user has one of the given roles.
     *
     * Usage: ->middleware('role:admin') or ->middleware('role:technician,admin').
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        if (! $request->user()?->hasRole(...$allowed)) {
            abort(403, __('You do not have permission to access this page.'));
        }

        return $next($request);
    }
}
