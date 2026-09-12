<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\UserDevice;
use App\Services\Notification\FcmClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * @param  list<int>  $notificationIds
     */
    public function __construct(
        public array $notificationIds,
    ) {}

    public function handle(FcmClient $fcmClient): void
    {
        $notifications = Notification::with('user')
            ->whereIn('id', $this->notificationIds)
            ->where('status', 'pending')
            ->get();

        foreach ($notifications as $notification) {
            $this->processNotification($notification, $fcmClient);
        }
    }

    private function processNotification(Notification $notification, FcmClient $fcmClient): void
    {
        $devices = UserDevice::where('user_id', $notification->user_id)
            ->where('is_active', true)
            ->get();

        if ($devices->isEmpty()) {
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            NotificationLog::create([
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'user_device_id' => null,
                'status' => 'skipped',
                'error_message' => 'No active devices',
            ]);

            return;
        }

        $allSuccess = true;

        foreach ($devices as $device) {
            $result = $fcmClient->send(
                $device,
                $notification->title,
                $notification->body,
                array_merge($notification->data ?? [], [
                    'notification_id' => (string) $notification->id,
                    'type' => $notification->type,
                    'screen' => $this->resolveScreen($notification),
                    'reference_id' => (string) ($notification->reference_id ?? ''),
                ]),
            );

            NotificationLog::create([
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'user_device_id' => $device->id,
                'status' => $result['success'] ? 'success' : 'failed',
                'error_message' => $result['error'],
                'response_payload' => $result['response'] ?: null,
            ]);

            // Deactivate dead tokens
            if ($result['invalid_token']) {
                $device->update(['is_active' => false]);
                Log::info("Deactivated device {$device->device_id} for user {$notification->user_id}: invalid token");
            }

            if (! $result['success']) {
                $allSuccess = false;
            }
        }

        $notification->update([
            'status' => $allSuccess ? 'sent' : 'failed',
            'sent_at' => now(),
        ]);
    }

    /**
     * Resolve the screen target for the notification data payload.
     */
    private function resolveScreen(Notification $notification): string
    {
        $type = NotificationType::tryFrom($notification->type);

        return $type?->screen() ?? 'notifications';
    }
}
