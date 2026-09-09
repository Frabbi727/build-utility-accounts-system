<?php

namespace App\Policies;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || $user->isResident();
    }

    public function view(User $user, MaintenanceRequest $request): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        if ($user->id === $request->user_id) {
            return true;
        }

        if ($user->isResident()) {
            if ($user->isOwner()) {
                return $user->owner?->flats()->where('flats.id', $request->flat_id)->exists() ?? false;
            }

            if ($user->isTenant()) {
                return $user->tenant?->flat_id === $request->flat_id;
            }
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isStaff() || $user->isResident();
    }

    public function update(User $user, MaintenanceRequest $request): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        if ($user->id === $request->user_id && $request->status === MaintenanceStatus::Open) {
            return true;
        }

        return false;
    }

    public function delete(User $user, MaintenanceRequest $request): bool
    {
        return $user->isStaff();
    }
}
