<?php

namespace App\Policies;

use App\Models\User;

/**
 * The "Users" section: the instance admin (Gate::before) and project
 * admins. A project admin sees and creates users only for their own
 * projects, with any of the two start roles (v2-17). Password reset and
 * blocking are instance-admin-only (before).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesUsers();
    }

    public function create(User $user): bool
    {
        return $user->managesUsers();
    }

    public function update(User $user, User $target): bool
    {
        return false;
    }

    public function resetPassword(User $user, User $target): bool
    {
        return false;
    }

    public function block(User $user, User $target): bool
    {
        return false;
    }
}
