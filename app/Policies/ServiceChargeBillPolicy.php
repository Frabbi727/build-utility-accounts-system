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

    /**
     * Staff read every bill; an owner reads only their own flat's, which is what makes
     * the printable bill reachable from the owner's statement. Same shape as
     * PaymentPolicy::view().
     */
    public function view(User $user, ServiceChargeBill $bill): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->owner !== null
            && $bill->flat !== null
            && $bill->flat->owner_id === $user->owner->id;
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
