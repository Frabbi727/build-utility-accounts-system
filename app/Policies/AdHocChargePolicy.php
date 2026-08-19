<?php

namespace App\Policies;

use App\Models\AdHocCharge;
use App\Models\User;
use App\Policies\Concerns\ManagesMasterData;

class AdHocChargePolicy
{
    use ManagesMasterData;

    public function view(User $user, AdHocCharge $charge): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, AdHocCharge $charge): bool
    {
        // Once a charge is on a bill it is history; correct it on the ledger instead.
        return $user->canManageMoney() && ! $charge->isApplied();
    }

    public function delete(User $user, AdHocCharge $charge): bool
    {
        return $user->canManageMoney() && ! $charge->isApplied();
    }
}
