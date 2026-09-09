<?php

namespace Tests\Feature;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\Role;
use App\Livewire\Dashboard;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Notice;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ResidentPortalTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Owner $owner;

    private User $ownerUser;

    private Flat $flat1;

    private Flat $flat2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3500.00')->create(['due_day_of_month' => 10]);
        app(CurrentBuilding::class)->set($this->building->id);

        $this->ownerUser = User::factory()->create(['name' => 'John Owner']);
        $this->ownerUser->assignRole(Role::Owner->value);

        $this->owner = Owner::factory()->create([
            'user_id' => $this->ownerUser->id,
            'name' => 'John Owner',
        ]);

        $this->flat1 = Flat::factory()->for($this->building)->create([
            'owner_id' => $this->owner->id,
            'number' => '101',
        ]);

        $this->flat2 = Flat::factory()->for($this->building)->create([
            'owner_id' => $this->owner->id,
            'number' => '102',
        ]);
    }

    public function test_owner_sees_resident_portal_with_flats_financials_and_latest_bill(): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-06-01'));

        Livewire::actingAs($this->ownerUser)
            ->test(Dashboard::class)
            ->assertSee('John Owner')
            ->assertSee('101')
            ->assertSee('102')
            ->assertSee('3,500.00')
            ->assertSee(__('dashboard.total_due'))
            ->assertSee(__('dashboard.latest_bill'))
            ->assertSee(__('dashboard.print_bill'));
    }

    public function test_owner_can_switch_between_flats(): void
    {
        Livewire::actingAs($this->ownerUser)
            ->test(Dashboard::class)
            ->assertSet('selectedFlatId', $this->flat1->id)
            ->call('selectFlat', $this->flat2->id)
            ->assertSet('selectedFlatId', $this->flat2->id);

        // Attempting to select an unowned flat should be ignored
        $otherFlat = Flat::factory()->for($this->building)->create(['number' => '999']);

        Livewire::actingAs($this->ownerUser)
            ->test(Dashboard::class)
            ->set('selectedFlatId', $this->flat1->id)
            ->call('selectFlat', $otherFlat->id)
            ->assertSet('selectedFlatId', $this->flat1->id);
    }

    public function test_tenant_sees_portal_for_rented_flat(): void
    {
        $tenantUser = User::factory()->create(['name' => 'Alice Tenant']);
        $tenantUser->assignRole(Role::Tenant->value);

        $tenantFlat = Flat::factory()->for($this->building)->create(['number' => '303']);
        Tenant::factory()->create([
            'flat_id' => $tenantFlat->id,
            'user_id' => $tenantUser->id,
            'name' => 'Alice Tenant',
        ]);

        Livewire::actingAs($tenantUser)
            ->test(Dashboard::class)
            ->assertSee('Alice Tenant')
            ->assertSee('303')
            ->assertSet('selectedFlatId', $tenantFlat->id);
    }

    public function test_resident_can_submit_maintenance_ticket_from_portal(): void
    {
        Livewire::actingAs($this->ownerUser)
            ->test(Dashboard::class)
            ->call('openTicketModal')
            ->assertSet('showTicketModal', true)
            ->set('ticketTitle', 'Elevator button stuck on 4th floor')
            ->set('ticketCategory', MaintenanceCategory::Elevator->value)
            ->set('ticketPriority', MaintenancePriority::High->value)
            ->set('ticketDescription', 'The button for floor 4 remains pressed down and does not release.')
            ->call('submitTicket')
            ->assertHasNoErrors()
            ->assertSet('showTicketModal', false);

        $this->assertDatabaseHas('maintenance_requests', [
            'building_id' => $this->building->id,
            'flat_id' => $this->flat1->id,
            'user_id' => $this->ownerUser->id,
            'title' => 'Elevator button stuck on 4th floor',
            'category' => MaintenanceCategory::Elevator->value,
            'priority' => MaintenancePriority::High->value,
            'status' => MaintenanceStatus::Open->value,
        ]);
    }

    public function test_resident_sees_emergency_notice_and_can_open_notice_modal(): void
    {
        $notice = Notice::factory()->emergency()->create([
            'building_id' => $this->building->id,
            'title' => 'Urgent Lift Outage',
            'content' => 'Emergency repairs on lift 2 starting immediately.',
        ]);

        Livewire::actingAs($this->ownerUser)
            ->test(Dashboard::class)
            ->assertSee('Urgent Lift Outage')
            ->call('openNoticeModal', $notice->id)
            ->assertSet('showNoticeModal', true)
            ->assertSet('viewingNotice.id', $notice->id);
    }
}
