<?php

namespace Tests\Feature\Notification;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Notification\ChannelManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationChannelManagerTest extends TestCase
{
    use RefreshDatabase;

    private ChannelManager $channelManager;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->channelManager = app(ChannelManager::class);
        $this->user = User::factory()->create();
    }

    public function test_it_dispatches_to_enabled_channels(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Notification',
            'body' => 'Test Body',
        ]);

        $results = $this->channelManager->dispatch(
            $this->user,
            $notification,
            ['in_app', 'sms', 'email'],
            'bills'
        );

        $this->assertTrue($results['in_app']);
        $this->assertTrue($results['email']);
    }

    public function test_it_skips_opted_out_channels(): void
    {
        UserNotificationPreference::factory()->create([
            'user_id' => $this->user->id,
            'channel' => 'sms',
            'category' => 'bills',
            'is_enabled' => false,
        ]);

        $this->user->load('notificationPreferences');

        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Notification',
            'body' => 'Test Body',
        ]);

        $results = $this->channelManager->dispatch(
            $this->user,
            $notification,
            ['in_app', 'sms'],
            'bills'
        );

        $this->assertTrue($results['in_app']);
        $this->assertFalse($results['sms']);
    }
}
