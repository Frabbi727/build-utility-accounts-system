<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;
use App\Models\User;

interface NotificationChannelInterface
{
    public function name(): string;

    public function send(User $user, Notification $notification): bool;
}
