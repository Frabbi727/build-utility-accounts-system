<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_owner_can_login_with_email_and_receive_token_and_flats(): void
    {
        $building = Building::factory()->create(['name' => 'Rose Villa']);
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('secret123'),
        ]);
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id, 'phone' => '01711000001']);
        $flat = Flat::factory()->create([
            'building_id' => $building->id,
            'owner_id' => $owner->id,
            'number' => '3A',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'is_owner', 'is_tenant'],
                    'flats' => [
                        ['id', 'number', 'building_name'],
                    ],
                ],
            ]);
    }

    public function test_owner_can_login_with_phone_number(): void
    {
        $user = User::factory()->create([
            'email' => 'owner2@example.com',
            'password' => Hash::make('secret123'),
        ]);
        $user->assignRole(Role::Owner->value);
        Owner::factory()->create(['user_id' => $user->id, 'phone' => '01711223344']);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '01711223344',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_tenant_can_login_and_see_leased_flat(): void
    {
        $building = Building::factory()->create();
        $flat = Flat::factory()->create(['building_id' => $building->id, 'number' => '5B']);
        $user = User::factory()->create([
            'email' => 'tenant@example.com',
            'password' => Hash::make('secret123'),
        ]);
        $user->assignRole(Role::Tenant->value);
        Tenant::factory()->create([
            'user_id' => $user->id,
            'flat_id' => $flat->id,
            'phone' => '01811556677',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '01811556677',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.flats.0.number', '5B');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'nobody@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_resident_can_get_profile_me(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_authenticated_resident_can_register_fcm_token(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/fcm-token', [
            'token' => 'device-fcm-sample-token-12345',
            'device_type' => 'android',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('user_device_tokens', [
            'user_id' => $user->id,
            'token' => 'device-fcm-sample-token-12345',
            'device_type' => 'android',
        ]);
    }

    public function test_resident_can_logout_and_revoke_token(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
