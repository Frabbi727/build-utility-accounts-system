<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Utility;
use App\Policies\Concerns\ManagesMasterData;

class UtilityPolicy
{
    use ManagesMasterData;

    public function view(User $user, Utility $utility): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Utility $utility): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, Utility $utility): bool
    {
        return $user->canManageMoney();
    }
}
