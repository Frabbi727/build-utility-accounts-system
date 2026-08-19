<?php

namespace App\Policies;

use App\Models\Meter;
use App\Models\User;
use App\Policies\Concerns\ManagesMasterData;

class MeterPolicy
{
    use ManagesMasterData;

    public function view(User $user, Meter $meter): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Meter $meter): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, Meter $meter): bool
    {
        return $user->canManageMoney();
    }
}
