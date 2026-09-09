<?php

namespace App\Policies;

use App\Models\Notice;
use App\Models\User;

class NoticePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Notice $notice): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        if ($user->isResident()) {
            if ($user->isOwner()) {
                return $user->owner?->flats()->where('building_id', $notice->building_id)->exists() ?? false;
            }

            if ($user->isTenant()) {
                return $user->tenant?->flat?->building_id === $notice->building_id;
            }
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Notice $notice): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Notice $notice): bool
    {
        return $user->isStaff();
    }
}
