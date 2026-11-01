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
}
