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

    public function view(User $user, Payment $payment): bool
    {
        return $user->isStaff();
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
