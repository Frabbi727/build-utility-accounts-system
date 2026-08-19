<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;
use App\Policies\Concerns\ManagesMasterData;

class VendorPolicy
{
    use ManagesMasterData;

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->canManageMoney();
    }
}
