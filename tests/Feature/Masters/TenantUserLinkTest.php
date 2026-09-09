<?php

namespace Tests\Feature\Masters;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantUserLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_tenant_belongs_to_user_and_user_helpers(): void
    {
        $building = Building::factory()->create();
        $flat = Flat::factory()->create(['building_id' => $building->id]);
        $user = User::factory()->create();
        $user->assignRole(Role::Tenant);

        $tenant = Tenant::factory()->create([
            'flat_id' => $flat->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($tenant->user->is($user));
        $this->assertTrue($user->tenant->is($tenant));
        $this->assertTrue($user->isTenant());
        $this->assertTrue($user->isResident());
        $this->assertFalse($user->isOwner());
        $this->assertFalse($user->isStaff());
    }

    public function test_owner_user_helpers(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Owner);

        $this->assertTrue($user->isOwner());
        $this->assertTrue($user->isResident());
        $this->assertFalse($user->isTenant());
        $this->assertFalse($user->isStaff());
    }
}
