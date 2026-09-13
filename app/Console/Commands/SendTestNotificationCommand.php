<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

class SendTestNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:send-test
                            {user? : The ID of the recipient user (default: first user)}
                            {--title=Test Notification : The notification title}
                            {--body=This is a test notification from Building Utility System. : The notification body}
                            {--type=ADMIN_NOTIFICATION : The notification type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a test push notification to a user device';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $service): int
    {
        $userId = $this->argument('user');

        $user = $userId
            ? User::find($userId)
            : User::first();

        if (! $user) {
            $this->error('Recipient user not found.');

            return self::FAILURE;
        }

        $typeString = (string) $this->option('type');
        $type = NotificationType::tryFrom($typeString) ?? NotificationType::AdminNotification;
        $title = (string) $this->option('title');
        $body = (string) $this->option('body');

        $this->info("Dispatching test notification to User #{$user->id} ({$user->name} / {$user->email})...");

        $notifications = $service->send(
            recipients: $user,
            type: $type,
            title: $title,
            body: $body,
            data: [
                'screen' => $type->screen(),
                'source' => 'artisan_command',
            ],
            notificationKey: 'test-'.uniqid(),
        );

        $notification = $notifications->first();

        if ($notification) {
            $this->info("✓ Notification created (ID: {$notification->id}, Type: {$notification->type}).");
            $this->info("Push delivery job queued for {$user->userDevices()->where('is_active', true)->count()} active device(s).");
        } else {
            $this->warn('Notification was skipped (idempotency key matched).');
        }

        return self::SUCCESS;
    }
}
