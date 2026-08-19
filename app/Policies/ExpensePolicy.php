<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

/**
 * Recording and viewing spending. Committee members read; the money-handling
 * roles record. Posted expenses are never edited — they are reversed.
 */
class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->canManageMoney();
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->canManageMoney();
    }
}
