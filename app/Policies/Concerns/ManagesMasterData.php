<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * The default access shape for master data: any staff role may read it, and the
 * money-handling roles (admin, accountant) may change it.
 *
 * Users, the chart of accounts and accounting periods are deliberately not on this
 * trait — those are admin-only and carry their own rules.
 */
trait ManagesMasterData
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->canManageMoney();
    }
}
