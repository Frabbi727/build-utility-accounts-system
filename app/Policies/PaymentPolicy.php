<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Collections. Staff read the history; only the money-handling roles take money.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    /**
     * Staff read every receipt; an owner reads only their own flat's, which is what
     * makes the printable receipt reachable from the owner's statement.
     */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->owner !== null
            && $payment->flat !== null
            && $payment->flat->owner_id === $user->owner->id;
    }

    public function create(User $user): bool
    {
        return $user->canManageMoney();
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->canManageMoney();
    }
}
