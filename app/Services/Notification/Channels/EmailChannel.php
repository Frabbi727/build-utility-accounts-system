<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class EmailChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'email';
    }

    public function send(User $user, Notification $notification): bool
    {
        if (! $user->email) {
            return false;
        }

        Log::info("Email notification queued for {$user->email}: [{$notification->title}] {$notification->body}");

        return true;
    }
}
