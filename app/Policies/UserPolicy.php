<?php

namespace App\Policies;

use App\Models\User;

/**
 * User and role administration is the one thing an accountant may not do.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $subject): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $subject): bool
    {
        return $user->isAdmin();
    }

    /**
     * Nobody deletes themselves out of the only administrator seat by accident.
     */
    public function delete(User $user, User $subject): bool
    {
        return $user->isAdmin() && $user->id !== $subject->id;
    }
}
