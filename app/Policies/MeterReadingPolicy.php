<?php

namespace App\Policies;

use App\Models\MeterReading;
use App\Models\User;
use App\Policies\Concerns\ManagesMasterData;

class MeterReadingPolicy
{
    use ManagesMasterData;

    public function view(User $user, MeterReading $reading): bool
    {
        return $user->isStaff();
    }

    /**
     * A billed reading is history: the bill it produced is already in an owner's hands
     * and the ledger. A misread found afterwards is corrected by the next month's
     * reading, not by editing this one.
     */
    public function update(User $user, MeterReading $reading): bool
    {
        return $user->canManageMoney() && ! $reading->isApplied();
    }

    public function delete(User $user, MeterReading $reading): bool
    {
        return $user->canManageMoney() && ! $reading->isApplied();
    }

    /**
     * Confirming settles the rate a unit will be charged at, so it is a money decision.
     */
    public function confirm(User $user, MeterReading $reading): bool
    {
        return $user->canManageMoney() && ! $reading->isApplied();
    }
}
