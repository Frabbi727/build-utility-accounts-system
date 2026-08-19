<?php

namespace App\Policies;

use App\Models\Building;
use App\Models\User;
use App\Policies\Concerns\ManagesMasterData;

class BuildingPolicy
{
    use ManagesMasterData;

    public function view(User $user, Building $building): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Building $building): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, Building $building): bool
    {
        return $user->canManageMoney();
    }
}
