<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Changing a role or the active flag. Admins cannot do this to their own
     * account, so there is always at least one active admin left.
     */
    public function changeAccess(User $actor, User $user): bool
    {
        return $actor->isAdmin() && $actor->isNot($user);
    }

    /**
     * Quick deactivate from the account list. Admin accounts are protected:
     * they can only lose access through a deliberate edit (demotion), which
     * keeps at least one active admin at all times.
     */
    public function deactivate(User $actor, User $user): bool
    {
        return $actor->isAdmin() && $user->is_active && ! $user->isAdmin();
    }

    public function activate(User $actor, User $user): bool
    {
        return $actor->isAdmin() && ! $user->is_active;
    }

    /**
     * Permanent removal. Admin accounts can never be removed; other accounts
     * can only be removed when they have no ticket history (FK restrict).
     */
    public function delete(User $actor, User $user): bool
    {
        return $actor->isAdmin() && ! $user->isAdmin();
    }
}
