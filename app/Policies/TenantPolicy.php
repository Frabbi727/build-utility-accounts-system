<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\ManagesMasterData;

class TenantPolicy
{
    use ManagesMasterData;

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->canManageMoney();
    }
}
