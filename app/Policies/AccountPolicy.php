<?php

namespace App\Policies;

use App\Enums\AccountCode;
use App\Models\Account;
use App\Models\User;

/**
 * The chart of accounts is structural: changing it changes what every posting
 * service can resolve, so only administrators may edit it. Staff read it.
 */
class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Account $account): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Account $account): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Account $account): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        // Codes the posting services resolve by name must keep existing.
        if (self::isReserved($account)) {
            return false;
        }

        return ! $account->journalLines()->exists();
    }

    /**
     * Whether a posting service resolves this account by its code.
     */
    public static function isReserved(Account $account): bool
    {
        return AccountCode::tryFrom($account->code) !== null;
    }
}
