<?php

namespace App\Policies;

use App\Models\Owner;
use App\Models\User;
use App\Policies\Concerns\ManagesMasterData;

class OwnerPolicy
{
    use ManagesMasterData;

    public function view(User $user, Owner $owner): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Owner $owner): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, Owner $owner): bool
    {
        return $user->canManageMoney();
    }
}
