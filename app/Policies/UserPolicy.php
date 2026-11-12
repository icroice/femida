<?php

namespace App\Policies;

use App\Models\User;

/**
 * The "Users" section: the admin (Gate::before) and project owners.
 * An owner sees and creates users only for their own projects and only
 * with developer/tester roles; appointing owners and resetting passwords
 * is admin-only. Password reset/blocking — admin only (before).
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
