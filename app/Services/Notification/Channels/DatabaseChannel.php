<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;
use App\Models\User;

class DatabaseChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'in_app';
    }

    public function send(User $user, Notification $notification): bool
    {
        // In-app notifications are stored in database and available immediately
        return true;
    }
}
