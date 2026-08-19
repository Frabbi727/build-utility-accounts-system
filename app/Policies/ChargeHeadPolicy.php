<?php

namespace App\Policies;

use App\Models\ChargeHead;
use App\Models\User;
use App\Policies\Concerns\ManagesMasterData;

class ChargeHeadPolicy
{
    use ManagesMasterData;

    public function view(User $user, ChargeHead $chargeHead): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, ChargeHead $chargeHead): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, ChargeHead $chargeHead): bool
    {
        return $user->canManageMoney();
    }
}
