<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserNotificationPreference;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
    }

    public function test_authenticated_user_can_retrieve_preference_matrix(): void
    {
        UserNotificationPreference::factory()->create([
            'user_id' => $this->user->id,
            'channel' => 'sms',
            'category' => 'bills',
            'is_enabled' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications/preferences');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'preferences',
                    'available_channels',
                    'available_categories',
                ],
            ]);

        $preferences = collect($response->json('data.preferences'));
        $smsBills = $preferences->first(fn ($p) => $p['channel'] === 'sms' && $p['category'] === 'bills');
        $this->assertFalse($smsBills['is_enabled']);

        $pushBills = $preferences->first(fn ($p) => $p['channel'] === 'push' && $p['category'] === 'bills');
        $this->assertTrue($pushBills['is_enabled']);
    }

    public function test_authenticated_user_can_update_preferences(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/v1/notifications/preferences', [
                'preferences' => [
                    [
                        'channel' => 'sms',
                        'category' => 'bills',
                        'is_enabled' => false,
                    ],
                    [
                        'channel' => 'email',
                        'category' => 'maintenance',
                        'is_enabled' => true,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $this->user->id,
            'channel' => 'sms',
            'category' => 'bills',
            'is_enabled' => false,
        ]);

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $this->user->id,
            'channel' => 'email',
            'category' => 'maintenance',
            'is_enabled' => true,
        ]);
    }
}
