<?php

namespace App\Policies;

use App\Models\UnitType;
use App\Models\User;
use App\Policies\Concerns\ManagesMasterData;

class UnitTypePolicy
{
    use ManagesMasterData;

    public function view(User $user, UnitType $unitType): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, UnitType $unitType): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, UnitType $unitType): bool
    {
        return $user->canManageMoney();
    }
}
