<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notification\FcmClient;
use App\Services\Notification\PushNotificationService;

class PushChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly FcmClient $fcmClient,
        private readonly PushNotificationService $pushService,
    ) {}

    public function name(): string
    {
        return 'push';
    }

    public function send(User $user, Notification $notification): bool
    {
        $devices = UserDevice::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        if ($devices->isNotEmpty()) {
            foreach ($devices as $device) {
                $this->fcmClient->send(
                    $device,
                    $notification->title,
                    $notification->body,
                    $notification->data ?? []
                );
            }

            return true;
        }

        // Fallback for legacy user_device_tokens if any exist
        $this->pushService->sendToUser($user, $notification->title, $notification->body, $notification->data ?? []);

        return true;
    }
}

