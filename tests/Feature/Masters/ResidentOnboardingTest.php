<?php

namespace Tests\Feature\Masters;

use App\Enums\Role;
use App\Livewire\Masters\OwnerList;
use App\Livewire\Masters\TenantList;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentBuilding;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResidentOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_provision_owner_login(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);
        $owner = Owner::factory()->create(['name' => 'Akram Khan', 'email' => 'akram@example.com', 'user_id' => null]);

        Livewire::actingAs($admin)
            ->test(OwnerList::class)
            ->call('openCreateUserModal', $owner->id)
            ->assertSet('showUserModal', true)
            ->assertSet('newUserName', 'Akram Khan')
            ->assertSet('newUserEmail', 'akram@example.com')
            ->set('newUserPassword', 'secret123')
            ->call('provisionUser')
            ->assertHasNoErrors();

        $owner->refresh();
        $this->assertNotNull($owner->user_id);
        $this->assertEquals('akram@example.com', $owner->user->email);
        $this->assertTrue($owner->user->hasRole(Role::Owner->value));
    }

    public function test_admin_can_provision_tenant_login(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);
        $building = Building::factory()->create();
        app(CurrentBuilding::class)->set($building->id);
        $flat = Flat::factory()->create(['building_id' => $building->id]);
        $tenant = Tenant::factory()->create([
            'flat_id' => $flat->id,
            'name' => 'Salma Begum',
            'email' => 'salma@example.com',
            'user_id' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(TenantList::class)
            ->call('openCreateUserModal', $tenant->id)
            ->assertSet('showUserModal', true)
            ->assertSet('newUserName', 'Salma Begum')
            ->assertSet('newUserEmail', 'salma@example.com')
            ->set('newUserPassword', 'secret123')
            ->call('provisionUser')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertNotNull($tenant->user_id);
        $this->assertEquals('salma@example.com', $tenant->user->email);
        $this->assertTrue($tenant->user->hasRole(Role::Tenant->value));
    }
}
