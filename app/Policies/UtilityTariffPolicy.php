<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UtilityTariff;
use App\Policies\Concerns\ManagesMasterData;

class UtilityTariffPolicy
{
    use ManagesMasterData;

    public function view(User $user, UtilityTariff $tariff): bool
    {
        return $user->isStaff();
    }

    /**
     * A tariff that has priced a confirmed reading is history. Editing it would change
     * what an already-issued bill computes to, so the answer is a new tariff instead.
     */
    public function update(User $user, UtilityTariff $tariff): bool
    {
        return $user->canManageMoney() && ! $tariff->isInUse();
    }

    public function delete(User $user, UtilityTariff $tariff): bool
    {
        return $user->canManageMoney() && ! $tariff->isInUse();
    }
}
