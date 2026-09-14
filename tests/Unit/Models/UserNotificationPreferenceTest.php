<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\UserNotificationPreference;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_preference_defaults_to_true_when_not_set(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->prefersChannel('push', 'bills'));
        $this->assertTrue($user->prefersChannel('sms', 'payments'));
    }

    public function test_user_preference_respects_opt_out(): void
    {
        $user = User::factory()->create();

        UserNotificationPreference::factory()->create([
            'user_id' => $user->id,
            'channel' => 'sms',
            'category' => 'bills',
            'is_enabled' => false,
        ]);

        $user->load('notificationPreferences');

        $this->assertFalse($user->prefersChannel('sms', 'bills'));
        $this->assertTrue($user->prefersChannel('push', 'bills'));
    }
}
