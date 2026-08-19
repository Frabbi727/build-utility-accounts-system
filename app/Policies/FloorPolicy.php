<?php

namespace App\Policies;

use App\Models\Floor;
use App\Models\User;
use App\Policies\Concerns\ManagesMasterData;

class FloorPolicy
{
    use ManagesMasterData;

    public function view(User $user, Floor $floor): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Floor $floor): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, Floor $floor): bool
    {
        return $user->canManageMoney();
    }
}
