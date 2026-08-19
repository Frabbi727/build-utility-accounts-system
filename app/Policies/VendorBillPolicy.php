<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorBill;

/**
 * Supplier payables. Reading is open to any staff role; recording a bill or
 * settling one is a money-handling action.
 */
class VendorBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, VendorBill $vendorBill): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->canManageMoney();
    }

    public function update(User $user, VendorBill $vendorBill): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, VendorBill $vendorBill): bool
    {
        return $user->canManageMoney();
    }
}
