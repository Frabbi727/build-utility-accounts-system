<?php

namespace App\Policies;

use App\Models\Backup;
use App\Models\User;

class BackupPolicy
{
    /**
     * Determine whether the user can view any backups.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the backup.
     */
    public function view(User $user, Backup $backup): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create backups.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can download the backup.
     */
    public function download(User $user, Backup $backup): bool
    {
        return $user->isAdmin() && $backup->isCompleted();
    }

    /**
     * Determine whether the user can restore the backup.
     */
    public function restore(User $user, Backup $backup): bool
    {
        return $user->isAdmin() && $backup->isCompleted();
    }

    /**
     * Determine whether the user can delete the backup.
     */
    public function delete(User $user, Backup $backup): bool
    {
        return $user->isAdmin() && ! $backup->is_protected;
    }
}
