<?php

namespace Tests\Feature\Api;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
use App\Models\Owner;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentMaintenanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_resident_can_create_ticket(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/resident/maintenance-requests', [
            'flat_id' => $flat->id,
            'title' => 'Water leakage under kitchen sink',
            'description' => 'Continuous dripping from the main pipe valve.',
            'category' => MaintenanceCategory::Plumbing->value,
            'priority' => MaintenancePriority::High->value,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Water leakage under kitchen sink')
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('maintenance_requests', [
            'flat_id' => $flat->id,
            'title' => 'Water leakage under kitchen sink',
            'status' => 'open',
        ]);
    }

    public function test_resident_can_list_and_filter_tickets(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        MaintenanceRequest::factory()->create([
            'building_id' => $building->id,
            'flat_id' => $flat->id,
            'user_id' => $user->id,
            'title' => 'Open ticket',
            'category' => MaintenanceCategory::Plumbing,
            'status' => MaintenanceStatus::Open,
        ]);

        MaintenanceRequest::factory()->create([
            'building_id' => $building->id,
            'flat_id' => $flat->id,
            'user_id' => $user->id,
            'title' => 'Resolved ticket',
            'category' => MaintenanceCategory::Electrical,
            'status' => MaintenanceStatus::Resolved,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/resident/maintenance-requests?flat_id='.$flat->id.'&status=open');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Open ticket');
    }

    public function test_resident_can_view_single_ticket_details(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create(['building_id' => $building->id, 'owner_id' => $owner->id]);

        $ticket = MaintenanceRequest::factory()->create([
            'building_id' => $building->id,
            'flat_id' => $flat->id,
            'user_id' => $user->id,
            'title' => 'Main door lock broken',
            'resolution_notes' => 'Lock replaced with new Yale cylinder.',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/resident/maintenance-requests/'.$ticket->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.resolution_notes', 'Lock replaced with new Yale cylinder.');
    }

    public function test_resident_cannot_access_ticket_for_another_flat(): void
    {
        $userA = User::factory()->create();
        $userA->assignRole(Role::Owner->value);
        $ownerA = Owner::factory()->create(['user_id' => $userA->id]);
        Flat::factory()->create(['owner_id' => $ownerA->id]);

        $userB = User::factory()->create();
        $userB->assignRole(Role::Owner->value);
        $ownerB = Owner::factory()->create(['user_id' => $userB->id]);
        $flatB = Flat::factory()->create(['owner_id' => $ownerB->id]);

        $ticketB = MaintenanceRequest::factory()->create(['flat_id' => $flatB->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/resident/maintenance-requests/'.$ticketB->id);

        $response->assertStatus(403);
    }
}
