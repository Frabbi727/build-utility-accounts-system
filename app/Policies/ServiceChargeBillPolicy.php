<?php

namespace App\Policies;

use App\Models\ServiceChargeBill;
use App\Models\User;

/**
 * Generated bills. Staff read them; only the money-handling roles create them.
 */
class ServiceChargeBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, ServiceChargeBill $bill): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->canManageMoney();
    }

    public function update(User $user, ServiceChargeBill $bill): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, ServiceChargeBill $bill): bool
    {
        return $user->canManageMoney();
    }
}
