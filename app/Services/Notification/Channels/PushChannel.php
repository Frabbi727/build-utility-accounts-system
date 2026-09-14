<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\PushNotificationService;

class PushChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly PushNotificationService $pushService,
    ) {}

    public function name(): string
    {
        return 'push';
    }

    public function send(User $user, Notification $notification): bool
    {
        $this->pushService->sendToUser($user, $notification->title, $notification->body, $notification->data ?? []);

        return true;
    }
}
