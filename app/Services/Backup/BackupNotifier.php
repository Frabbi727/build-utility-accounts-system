<?php

namespace App\Services\Backup;

use App\Enums\NotificationType;
use App\Models\Backup;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BackupNotifier
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Notify administrators of successful backup.
     */
    public function notifySuccess(Backup $backup): void
    {
        if (! config('backup.notifications.notify_on_success', true)) {
            return;
        }

        $title = 'Daily Backup Completed';
        $body = "Backup [{$backup->backup_name}] was created successfully ({$backup->formattedSize()}).";

        Log::info("Backup success: {$backup->backup_name}", [
            'backup_id' => $backup->id,
            'size' => $backup->file_size,
            'type' => $backup->backup_type->value,
            'checksum' => $backup->checksum,
        ]);

        try {
            $admins = User::role('admin')->get();
            if ($admins->isNotEmpty()) {
                $this->notificationService->send(
                    recipients: $admins,
                    type: NotificationType::AdminNotification,
                    title: $title,
                    body: $body,
                    data: [
                        'backup_id' => $backup->id,
                        'backup_name' => $backup->backup_name,
                        'status' => $backup->status->value,
                    ],
                    referenceType: Backup::class,
                    referenceId: $backup->id,
                    notificationKey: "backup_success_{$backup->id}",
                );
            }
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch in-app notification for backup success: '.$e->getMessage());
        }

        $email = config('backup.notifications.mail_to');
        if ($email) {
            try {
                Mail::raw($body, function ($msg) use ($email, $title): void {
                    $msg->to($email)->subject("[Utility Accounts] {$title}");
                });
            } catch (Throwable $e) {
                Log::warning('Failed to send backup success email: '.$e->getMessage());
            }
        }
    }

    /**
     * Notify administrators of backup failure (CRITICAL).
     */
    public function notifyFailure(string $reason, ?Backup $backup = null): void
    {
        if (! config('backup.notifications.notify_on_failure', true)) {
            return;
        }

        $backupName = $backup !== null ? $backup->backup_name : 'Unknown';
        $title = 'CRITICAL: Backup Failed';
        $body = "CRITICAL: Backup [{$backupName}] failed. Reason: {$reason}";

        Log::critical("CRITICAL: Backup failed [{$backupName}]: {$reason}", [
            'backup_id' => $backup?->id,
            'reason' => $reason,
        ]);

        try {
            $admins = User::role('admin')->get();
            if ($admins->isNotEmpty()) {
                $this->notificationService->send(
                    recipients: $admins,
                    type: NotificationType::AdminNotification,
                    title: $title,
                    body: $body,
                    data: [
                        'backup_id' => $backup?->id,
                        'backup_name' => $backupName,
                        'reason' => $reason,
                    ],
                    referenceType: Backup::class,
                    referenceId: $backup?->id,
                    notificationKey: 'backup_failed_'.($backup !== null ? (string) $backup->id : uniqid()),
                );
            }
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch in-app notification for backup failure: '.$e->getMessage());
        }

        $email = config('backup.notifications.mail_to');
        if ($email) {
            try {
                Mail::raw($body, function ($msg) use ($email, $title): void {
                    $msg->to($email)->subject("[Utility Accounts] {$title}");
                });
            } catch (Throwable $e) {
                Log::warning('Failed to send backup failure email: '.$e->getMessage());
            }
        }
    }
}
