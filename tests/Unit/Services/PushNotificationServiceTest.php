<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\UserDeviceToken;
use App\Services\Notification\PushNotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_dispatches_fcm_notification_to_user_devices(): void
    {
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/mock/messages/123'], 200),
        ]);

        config()->set('services.fcm.key', 'mock-fcm-server-key');

        $user = User::factory()->create();
        UserDeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-device-token-111',
            'device_type' => 'android',
        ]);

        $service = new PushNotificationService;
        $sentCount = $service->sendToUser($user, 'New Bill Generated', 'Your bill for September is ready', [
            'type' => 'bill',
            'bill_id' => '101',
        ]);

        $this->assertSame(1, $sentCount);
    }

    public function test_removes_unregistered_stale_device_tokens(): void
    {
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => 'Requested entity was not found.',
                    'status' => 'UNREGISTERED',
                ],
            ], 404),
        ]);

        config()->set('services.fcm.key', 'mock-fcm-server-key');

        $user = User::factory()->create();
        UserDeviceToken::create([
            'user_id' => $user->id,
            'token' => 'stale-token-to-delete',
            'device_type' => 'android',
        ]);

        $service = new PushNotificationService;
        $service->sendToUser($user, 'Test Alert', 'Test Message');

        $this->assertDatabaseMissing('user_device_tokens', [
            'token' => 'stale-token-to-delete',
        ]);
    }
}
