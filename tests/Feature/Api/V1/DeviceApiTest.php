<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_device_creates_new_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/devices/register', [
            'device_id' => 'test-device-001',
            'device_token' => 'fcm-token-abc123',
            'platform' => 'android',
            'device_model' => 'Pixel 8',
            'os_version' => '15.0',
            'app_version' => '1.0.0',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.device_id', 'test-device-001');

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'device_id' => 'test-device-001',
            'platform' => 'android',
            'is_active' => true,
        ]);
    }

    public function test_register_device_updates_existing_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_id' => 'existing-device',
            'device_token' => 'old-token',
        ]);

        $response = $this->postJson('/api/v1/devices/register', [
            'device_id' => 'existing-device',
            'device_token' => 'new-token-xyz',
            'platform' => 'ios',
        ]);

        $response->assertOk();

        $this->assertDatabaseCount('user_devices', 1);
        $this->assertDatabaseHas('user_devices', [
            'device_id' => 'existing-device',
            'device_token' => 'new-token-xyz',
            'platform' => 'ios',
        ]);
    }

    public function test_register_device_reassigns_on_different_user_login(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        UserDevice::factory()->create([
            'user_id' => $userA->id,
            'device_id' => 'shared-device',
            'is_active' => false,
        ]);

        Sanctum::actingAs($userB);

        $response = $this->postJson('/api/v1/devices/register', [
            'device_id' => 'shared-device',
            'device_token' => 'userb-token',
        ]);

        $response->assertOk();

        $device = UserDevice::where('device_id', 'shared-device')->first();
        $this->assertEquals($userB->id, $device->user_id);
        $this->assertTrue($device->is_active);
    }

    public function test_destroy_device_deactivates_it(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_id' => 'logout-device',
            'is_active' => true,
        ]);

        $response = $this->deleteJson('/api/v1/devices/logout-device');

        $response->assertOk()
            ->assertJsonPath('message', 'Device deactivated');

        $this->assertDatabaseHas('user_devices', [
            'device_id' => 'logout-device',
            'is_active' => false,
        ]);
    }

    public function test_destroy_device_owned_by_other_user_returns_404(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        UserDevice::factory()->create([
            'user_id' => $userA->id,
            'device_id' => 'not-yours',
        ]);

        Sanctum::actingAs($userB);

        $response = $this->deleteJson('/api/v1/devices/not-yours');

        $response->assertNotFound();
    }

    public function test_register_device_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/devices/register', [
            'device_id' => 'test',
            'device_token' => 'token',
        ]);

        $response->assertUnauthorized();
    }

    public function test_register_device_validation_errors(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/devices/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['device_id', 'device_token']);
    }
}
