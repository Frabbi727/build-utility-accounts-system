<?php

namespace App\Policies;

use App\Models\CostDistribution;
use App\Models\User;
use App\Policies\Concerns\ManagesMasterData;

class CostDistributionPolicy
{
    use ManagesMasterData;

    public function view(User $user, CostDistribution $distribution): bool
    {
        return $user->isStaff();
    }

    /**
     * An approved distribution's lines are frozen, and a billed one is history.
     */
    public function update(User $user, CostDistribution $distribution): bool
    {
        return $user->canManageMoney() && ! $distribution->isApproved();
    }

    public function delete(User $user, CostDistribution $distribution): bool
    {
        return $user->canManageMoney() && ! $distribution->isBilled();
    }

    /**
     * Approving settles what every owner will be charged, so it is a money decision.
     */
    public function approve(User $user, CostDistribution $distribution): bool
    {
        return $user->canManageMoney() && ! $distribution->isApproved();
    }

    public function revert(User $user, CostDistribution $distribution): bool
    {
        return $user->canManageMoney() && $distribution->isApproved() && ! $distribution->isBilled();
    }
}
