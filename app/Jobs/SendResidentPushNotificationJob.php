<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Legacy job preserved for backward compatibility.
 *
 * Routes through the centralized NotificationService which persists
 * notification records and dispatches FCM via SendPushNotificationJob.
 */
class SendResidentPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $userIds,
        public string $title,
        public string $body,
        public array $data = [],
        public ?NotificationType $type = null,
        public ?string $referenceType = null,
        public ?int $referenceId = null,
        public ?string $notificationKey = null,
    ) {}

    public function handle(NotificationService $service): void
    {
        $notificationType = $this->type ?? $this->inferType();

        $service->send(
            $this->userIds,
            $notificationType,
            $this->title,
            $this->body,
            $this->data,
            $this->referenceType,
            $this->referenceId,
            $this->notificationKey,
        );
    }

    /**
     * Infer the NotificationType from the legacy data['type'] key.
     */
    private function inferType(): NotificationType
    {
        $legacyType = $this->data['type'] ?? '';

        return match ($legacyType) {
            'new_bill' => NotificationType::BillGenerated,
            'payment_approved' => NotificationType::PaymentApproved,
            'payment_rejected' => NotificationType::PaymentRejected,
            'ticket_updated' => NotificationType::MaintenanceUpdated,
            'notice' => NotificationType::NoticePublished,
            default => NotificationType::AdminNotification,
        };
    }
}
