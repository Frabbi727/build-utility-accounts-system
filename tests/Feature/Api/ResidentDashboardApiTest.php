<?php

namespace Tests\Feature\Api;

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

class ResidentDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_resident_can_fetch_their_flats_list(): void
    {
        $building = Building::factory()->create(['name' => 'Skyline Heights']);
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create([
            'building_id' => $building->id,
            'owner_id' => $owner->id,
            'number' => '10-A',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/resident/flats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', '10-A')
            ->assertJsonPath('data.0.building_name', 'Skyline Heights');
    }

    public function test_resident_gets_live_balances_and_dashboard_summary(): void
    {
        $building = Building::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create([
            'building_id' => $building->id,
            'owner_id' => $owner->id,
            'number' => '402',
        ]);

        Notice::factory()->create([
            'building_id' => $building->id,
            'title' => 'Water tank cleaning',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/resident/dashboard?flat_id='.$flat->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'flat' => ['id', 'number', 'building_name'],
                    'balances' => [
                        'total_due',
                        'advance_held',
                        'current_month_charges',
                        'arrears',
                    ],
                    'latest_bill',
                    'active_notices' => [
                        ['id', 'title'],
                    ],
                    'recent_payments',
                    'recent_submissions',
                    'my_tickets',
                ],
            ]);
    }

    public function test_resident_forbidden_from_viewing_unauthorized_flat_dashboard(): void
    {
        $userA = User::factory()->create();
        $userA->assignRole(Role::Owner->value);
        $ownerA = Owner::factory()->create(['user_id' => $userA->id]);
        Flat::factory()->create(['owner_id' => $ownerA->id]);

        $userB = User::factory()->create();
        $userB->assignRole(Role::Owner->value);
        $ownerB = Owner::factory()->create(['user_id' => $userB->id]);
        $flatB = Flat::factory()->create(['owner_id' => $ownerB->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/resident/dashboard?flat_id='.$flatB->id);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
