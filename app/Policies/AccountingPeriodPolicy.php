<?php

namespace App\Policies;

use App\Models\AccountingPeriod;
use App\Models\User;

/**
 * Locking a period stops the accountant posting into it, so only an administrator
 * may turn the lock.
 */
class AccountingPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, AccountingPeriod $period): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, AccountingPeriod $period): bool
    {
        return $user->isAdmin();
    }
}
