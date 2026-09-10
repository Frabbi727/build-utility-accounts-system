<?php

namespace Tests\Feature\Api;

use App\Enums\NoticeType;
use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Notice;
use App\Models\Owner;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentNoticeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_resident_sees_building_notices_with_pinned_priority(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        Notice::factory()->create([
            'building_id' => $building->id,
            'title' => 'Routine notice',
            'is_pinned' => false,
            'published_at' => now()->subDay(),
        ]);
        Notice::factory()->create([
            'building_id' => $building->id,
            'title' => 'Urgent generator repair',
            'is_pinned' => true,
            'published_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/resident/notices');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.title', 'Urgent generator repair')
            ->assertJsonPath('data.1.title', 'Routine notice');
    }

    public function test_resident_can_view_single_notice(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        $notice = Notice::factory()->create([
            'building_id' => $building->id,
            'title' => 'Emergency Meeting',
            'content' => 'General body meeting on Friday at 8 PM in the rooftop hall.',
            'type' => NoticeType::Emergency,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/resident/notices/'.$notice->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Emergency Meeting')
            ->assertJsonPath('data.content', 'General body meeting on Friday at 8 PM in the rooftop hall.');
    }

    public function test_resident_cannot_access_notice_from_another_building(): void
    {
        $buildingA = Building::factory()->create();
        $userA = User::factory()->create();
        $userA->assignRole(Role::Owner->value);
        $ownerA = Owner::factory()->create(['user_id' => $userA->id]);
        Flat::factory()->create(['building_id' => $buildingA->id, 'owner_id' => $ownerA->id]);

        $buildingB = Building::factory()->create();
        $noticeB = Notice::factory()->create(['building_id' => $buildingB->id]);

        $response = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/resident/notices/'.$noticeB->id);

        $response->assertStatus(403);
    }
}
