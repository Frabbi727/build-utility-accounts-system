<?php

namespace Tests\Feature\Notification;

use App\Enums\NotificationType;
use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notification\FcmClient;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_creates_notification_and_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        UserDevice::factory()->create(['user_id' => $user->id]);

        $service = new NotificationService;
        $notifications = $service->send(
            $user,
            NotificationType::BillGenerated,
            'Bill Generated',
            'Your monthly bill has been generated.',
            ['bill_id' => 42],
            'bill',
            42,
            'bill-gen-42',
        );

        $this->assertCount(1, $notifications);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'BILL_GENERATED',
            'title' => 'Bill Generated',
            'status' => 'pending',
            'notification_key' => "bill-gen-42:{$user->id}",
        ]);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    public function test_send_to_multiple_users(): void
    {
        Queue::fake();

        $users = User::factory()->count(3)->create();

        $service = new NotificationService;
        $notifications = $service->send(
            $users,
            NotificationType::AdminNotification,
            'Admin Alert',
            'System update scheduled.',
        );

        $this->assertCount(3, $notifications);
        $this->assertDatabaseCount('notifications', 3);
    }

    public function test_idempotency_prevents_duplicate_notification(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $service = new NotificationService;

        // First call creates the notification
        $first = $service->send(
            $user,
            NotificationType::BillGenerated,
            'Bill',
            'First call',
            notificationKey: 'idempotent-key',
        );

        $this->assertCount(1, $first);

        // Second call with same key is skipped
        $second = $service->send(
            $user,
            NotificationType::BillGenerated,
            'Bill',
            'Second call',
            notificationKey: 'idempotent-key',
        );

        $this->assertCount(0, $second);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_send_to_user_ids_array(): void
    {
        Queue::fake();

        $users = User::factory()->count(2)->create();

        $service = new NotificationService;
        $notifications = $service->send(
            $users->pluck('id')->all(),
            NotificationType::NoticePublished,
            'New Notice',
            'A notice has been published.',
        );

        $this->assertCount(2, $notifications);
    }

    public function test_send_with_empty_recipients_returns_empty(): void
    {
        Queue::fake();

        $service = new NotificationService;
        $result = $service->send(
            [],
            NotificationType::AdminNotification,
            'Test',
            'No one to send to.',
        );

        $this->assertCount(0, $result);
        Queue::assertNothingPushed();
    }

    public function test_job_processes_notification_and_creates_logs(): void
    {
        config(['services.fcm.key' => null, 'services.fcm.project_id' => null, 'services.fcm.credentials_path' => null]);

        $user = User::factory()->create();
        $device = UserDevice::factory()->create(['user_id' => $user->id]);
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $job = new SendPushNotificationJob([$notification->id]);
        $job->handle(new FcmClient);

        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
        $this->assertNotNull($notification->sent_at);
        $this->assertDatabaseHas('notification_logs', [
            'notification_id' => $notification->id,
            'user_device_id' => $device->id,
            'status' => 'success',
        ]);
    }

    public function test_job_skips_when_no_active_devices(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $job = new SendPushNotificationJob([$notification->id]);
        $job->handle(new FcmClient);

        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
        $this->assertDatabaseHas('notification_logs', [
            'notification_id' => $notification->id,
            'status' => 'skipped',
            'error_message' => 'No active devices',
        ]);
    }
}
