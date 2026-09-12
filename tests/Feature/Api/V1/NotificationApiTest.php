<?php

namespace Tests\Feature\Api\V1;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_notifications(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Notification::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data');
    }

    public function test_index_filters_by_is_read(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Notification::factory()->count(3)->create(['user_id' => $user->id, 'is_read' => false]);
        Notification::factory()->count(2)->read()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/v1/notifications?is_read=0');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_does_not_show_other_users_notifications(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Notification::factory()->count(3)->create(['user_id' => $userA->id]);
        Notification::factory()->count(2)->create(['user_id' => $userB->id]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_unread_count_returns_correct_count(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Notification::factory()->count(4)->create(['user_id' => $user->id, 'is_read' => false]);
        Notification::factory()->count(2)->read()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('data.unread_count', 4);
    }

    public function test_mark_single_notification_as_read(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'is_read' => false,
        ]);

        $response = $this->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonPath('data.is_read', true);

        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_read_returns_404_for_other_users_notification(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $notification = Notification::factory()->create(['user_id' => $userA->id]);

        Sanctum::actingAs($userB);

        $response = $this->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertNotFound();
    }

    public function test_mark_all_read(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Notification::factory()->count(5)->create(['user_id' => $user->id, 'is_read' => false]);

        $response = $this->patchJson('/api/v1/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('data.updated_count', 5);

        $this->assertEquals(0, Notification::where('user_id', $user->id)->where('is_read', false)->count());
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
        $this->patchJson('/api/v1/notifications/1/read')->assertUnauthorized();
        $this->patchJson('/api/v1/notifications/read-all')->assertUnauthorized();
    }
}
