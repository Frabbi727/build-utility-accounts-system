<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Notification $notification): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $notification->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Notification $notification): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $notification->user_id === $user->id;
    }

    public function delete(User $user, Notification $notification): bool
    {
        return $user->isStaff();
    }
}
