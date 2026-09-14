<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SmsChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'sms';
    }

    public function send(User $user, Notification $notification): bool
    {
        $phone = $user->owner->phone ?? $user->tenant->phone ?? null;
        if (! $phone) {
            return false;
        }

        Log::info("SMS notification dispatched to {$phone}: [{$notification->title}] {$notification->body}");

        return true;
    }
}
