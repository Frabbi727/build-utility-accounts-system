<?php

namespace Tests\Unit\Services;

use App\Models\UserDevice;
use App\Services\Notification\FcmClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FcmClientTest extends TestCase
{
    #[Test]
    public function it_sends_via_mock_when_no_credentials_configured(): void
    {
        config(['services.fcm.key' => null, 'services.fcm.project_id' => null, 'services.fcm.credentials_path' => null]);

        Log::shouldReceive('info')->once()->withArgs(fn (string $msg): bool => str_contains($msg, 'FCM mock send'));

        $device = new UserDevice([
            'device_id' => 'test-device',
            'device_token' => 'fake-token-123',
            'platform' => 'android',
            'is_active' => true,
        ]);

        $client = new FcmClient;
        $result = $client->send($device, 'Test Title', 'Test Body', ['key' => 'value']);

        $this->assertTrue($result['success']);
        $this->assertEquals('mock', $result['status']);
        $this->assertNull($result['error']);
        $this->assertFalse($result['invalid_token']);
    }

    #[Test]
    public function it_sends_via_legacy_when_server_key_is_set(): void
    {
        config(['services.fcm.key' => 'test-server-key', 'services.fcm.project_id' => null, 'services.fcm.credentials_path' => null]);

        Http::fake([
            'fcm.googleapis.com/fcm/send' => Http::response([
                'multicast_id' => 123,
                'success' => 1,
                'failure' => 0,
                'results' => [['message_id' => 'msg-001']],
            ]),
        ]);

        $device = new UserDevice([
            'device_id' => 'test-device',
            'device_token' => 'fake-token-456',
            'platform' => 'android',
            'is_active' => true,
        ]);

        $client = new FcmClient;
        $result = $client->send($device, 'Bill Generated', 'Your bill is ready');

        $this->assertTrue($result['success']);
        $this->assertEquals('sent', $result['status']);
        $this->assertFalse($result['invalid_token']);
    }

    #[Test]
    public function it_detects_invalid_token_on_legacy_not_registered(): void
    {
        config(['services.fcm.key' => 'test-server-key', 'services.fcm.project_id' => null, 'services.fcm.credentials_path' => null]);

        Http::fake([
            'fcm.googleapis.com/fcm/send' => Http::response([
                'multicast_id' => 123,
                'success' => 0,
                'failure' => 1,
                'results' => [['error' => 'NotRegistered']],
            ]),
        ]);

        $device = new UserDevice([
            'device_id' => 'dead-device',
            'device_token' => 'expired-token',
            'platform' => 'android',
            'is_active' => true,
        ]);

        $client = new FcmClient;
        $result = $client->send($device, 'Test', 'Body');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['invalid_token']);
    }

    #[Test]
    public function it_handles_legacy_http_error(): void
    {
        config(['services.fcm.key' => 'test-server-key', 'services.fcm.project_id' => null, 'services.fcm.credentials_path' => null]);

        Http::fake([
            'fcm.googleapis.com/fcm/send' => Http::response('UNREGISTERED', 404),
        ]);

        $device = new UserDevice([
            'device_id' => 'gone-device',
            'device_token' => 'gone-token',
            'platform' => 'ios',
            'is_active' => true,
        ]);

        $client = new FcmClient;
        $result = $client->send($device, 'Test', 'Body');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['invalid_token']);
    }

    #[Test]
    public function it_normalizes_data_values_to_strings(): void
    {
        config(['services.fcm.key' => null, 'services.fcm.project_id' => null, 'services.fcm.credentials_path' => null]);

        Log::shouldReceive('info')->once();

        $device = new UserDevice([
            'device_id' => 'test-device',
            'device_token' => 'fake-token',
            'platform' => 'android',
            'is_active' => true,
        ]);

        $client = new FcmClient;
        $result = $client->send($device, 'Title', 'Body', ['num' => 42, 'str' => 'hello']);

        $this->assertTrue($result['success']);
    }
}
