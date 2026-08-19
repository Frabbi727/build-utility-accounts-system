<?php

namespace App\Policies;

use App\Models\Flat;
use App\Models\User;

class FlatPolicy
{
    /**
     * Owners may only ever read their own flat; staff read all of them.
     */
    public function view(User $user, Flat $flat): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->owner !== null && $flat->owner_id === $user->owner->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function manage(User $user): bool
    {
        return $user->canManageMoney();
    }
}
