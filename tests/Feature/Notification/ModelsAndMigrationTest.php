<?php

namespace Tests\Feature\Notification;

use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsAndMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_multiple_active_devices(): void
    {
        $user = User::factory()->create();

        UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_id' => 'device-111',
            'platform' => 'android',
            'device_model' => 'Pixel 8',
            'is_active' => true,
        ]);

        UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_id' => 'device-222',
            'platform' => 'ios',
            'device_model' => 'iPhone 15',
            'is_active' => true,
        ]);

        $user->refresh();

        $this->assertCount(2, $user->userDevices);
        $this->assertTrue($user->userDevices->every(fn (UserDevice $d): bool => $d->is_active));
    }

    public function test_notification_creation_and_logs(): void
    {
        $user = User::factory()->create();

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'type' => 'BillGenerated',
            'title' => 'New Bill',
            'body' => 'Your monthly bill has been generated.',
            'data' => ['bill_id' => 42],
            'reference_type' => 'App\\Models\\Bill',
            'reference_id' => 42,
            'notification_key' => 'bill-generated-42',
            'status' => 'pending',
            'is_read' => false,
        ]);

        $device = UserDevice::factory()->create(['user_id' => $user->id]);

        $log = NotificationLog::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'user_device_id' => $device->id,
            'status' => 'sent',
            'error_message' => null,
            'response_payload' => ['message_id' => 'fcm-msg-001'],
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'type' => 'BillGenerated',
            'notification_key' => 'bill-generated-42',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'id' => $log->id,
            'notification_id' => $notification->id,
            'status' => 'sent',
        ]);

        // Verify relationships
        $notification->refresh();
        $this->assertCount(1, $notification->logs);
        $this->assertEquals($log->id, $notification->logs->first()->id);
        $this->assertEquals($user->id, $log->user->id);
        $this->assertEquals($device->id, $log->userDevice->id);
    }

    public function test_notification_key_is_unique(): void
    {
        $user = User::factory()->create();

        Notification::factory()->create([
            'user_id' => $user->id,
            'notification_key' => 'unique-key-1',
        ]);

        $this->expectException(QueryException::class);

        Notification::factory()->create([
            'user_id' => $user->id,
            'notification_key' => 'unique-key-1',
        ]);
    }

    public function test_device_id_is_unique(): void
    {
        UserDevice::factory()->create(['device_id' => 'same-device']);

        $this->expectException(QueryException::class);

        UserDevice::factory()->create(['device_id' => 'same-device']);
    }

    public function test_cascade_delete_removes_devices_and_notifications(): void
    {
        $user = User::factory()->create();
        UserDevice::factory()->create(['user_id' => $user->id]);
        $notification = Notification::factory()->create(['user_id' => $user->id]);
        NotificationLog::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'status' => 'sent',
        ]);

        $user->delete();

        $this->assertDatabaseCount('user_devices', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('notification_logs', 0);
    }

    public function test_user_notifications_relationship(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->refresh()->notifications);
    }
}
