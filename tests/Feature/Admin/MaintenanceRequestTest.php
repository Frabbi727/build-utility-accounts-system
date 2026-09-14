<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountCode;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\Role;
use App\Livewire\Admin\MaintenanceRequestList;
use App\Models\Building;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
use App\Models\Owner;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vendor;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceRequestTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->building = Building::factory()->create();
        $this->flat = Flat::factory()->create(['building_id' => $this->building->id]);
        app(CurrentBuilding::class)->set($this->building->id);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    public function test_staff_can_view_and_create_maintenance_request(): void
    {
        $staff = $this->userWithRole(Role::Admin);

        Livewire::actingAs($staff)
            ->test(MaintenanceRequestList::class)
            ->assertSee(__('maintenance.maintenance_requests'))
            ->call('create')
            ->assertSet('showForm', true)
            ->set('flatId', $this->flat->id)
            ->set('title', 'Water leakage in bathroom')
            ->set('description', 'Pipe leaking heavily under the basin.')
            ->set('category', MaintenanceCategory::Plumbing->value)
            ->set('priority', MaintenancePriority::High->value)
            ->set('status', MaintenanceStatus::Open->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $request = MaintenanceRequest::firstOrFail();
        $this->assertSame('Water leakage in bathroom', $request->title);
        $this->assertSame(MaintenanceCategory::Plumbing, $request->category);
        $this->assertSame(MaintenancePriority::High, $request->priority);
        $this->assertSame(MaintenanceStatus::Open, $request->status);
        $this->assertSame($this->building->id, $request->building_id);
        $this->assertSame($this->flat->id, $request->flat_id);
    }

    public function test_staff_can_edit_assign_and_resolve_maintenance_request(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $staffMember = Staff::factory()->create(['building_id' => $this->building->id]);
        $vendor = Vendor::factory()->create();

        $request = MaintenanceRequest::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'status' => MaintenanceStatus::Open,
        ]);

        Livewire::actingAs($admin)
            ->test(MaintenanceRequestList::class)
            ->call('edit', $request->id)
            ->set('status', MaintenanceStatus::Resolved->value)
            ->set('assignedStaffId', $staffMember->id)
            ->set('assignedVendorId', $vendor->id)
            ->set('resolutionNotes', 'Replaced the faulty washer.')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $request->fresh();
        $this->assertSame(MaintenanceStatus::Resolved, $fresh->status);
        $this->assertSame($staffMember->id, $fresh->assigned_staff_id);
        $this->assertSame($vendor->id, $fresh->assigned_vendor_id);
        $this->assertSame('Replaced the faulty washer.', $fresh->resolution_notes);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_resident_cannot_access_admin_maintenance_screen(): void
    {
        $resident = $this->userWithRole(Role::Owner);

        $response = $this->actingAs($resident)->get(route('maintenance-requests.index'));
        $response->assertForbidden();
    }

    public function test_maintenance_request_policy_isolates_residents_flats(): void
    {
        $ownerUser = $this->userWithRole(Role::Owner);
        $owner = Owner::factory()->create(['user_id' => $ownerUser->id]);
        $ownerFlat = Flat::factory()->create([
            'building_id' => $this->building->id,
            'owner_id' => $owner->id,
        ]);

        $otherFlat = Flat::factory()->create(['building_id' => $this->building->id]);

        $ownRequest = MaintenanceRequest::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $ownerFlat->id,
            'user_id' => $ownerUser->id,
        ]);

        $otherRequest = MaintenanceRequest::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $otherFlat->id,
        ]);

        $this->assertTrue($ownerUser->can('view', $ownRequest));
        $this->assertFalse($ownerUser->can('view', $otherRequest));
    }

    public function test_staff_can_delete_maintenance_request(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $request = MaintenanceRequest::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
        ]);

        Livewire::actingAs($admin)
            ->test(MaintenanceRequestList::class)
            ->call('delete', $request->id);

        $this->assertDatabaseMissing('maintenance_requests', ['id' => $request->id]);
    }

    public function test_can_filter_by_sla_and_view_timeline(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $overdueTicket = MaintenanceRequest::factory()->overdue()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'title' => 'Overdue Lift Issue',
        ]);

        $onTrackTicket = MaintenanceRequest::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'title' => 'Routine Painting',
            'due_by' => now()->addDays(3),
        ]);

        Livewire::actingAs($admin)
            ->test(MaintenanceRequestList::class)
            ->set('slaFilter', 'overdue')
            ->assertSee('Overdue Lift Issue')
            ->assertDontSee('Routine Painting')
            ->call('openTimeline', $overdueTicket->id)
            ->assertSet('showTimelineModal', true)
            ->call('closeTimeline')
            ->assertSet('showTimelineModal', false);
    }

    public function test_can_generate_vendor_bill_from_maintenance_list(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $admin = $this->userWithRole(Role::Admin);
        $vendor = Vendor::factory()->create(['name' => 'Fast Cleaners']);
        $repairsAccount = app(JournalService::class)->account(AccountCode::RepairsMaintenance);

        $ticket = MaintenanceRequest::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'assigned_vendor_id' => $vendor->id,
            'status' => MaintenanceStatus::Open,
            'cost' => '0.00',
        ]);

        Livewire::actingAs($admin)
            ->test(MaintenanceRequestList::class)
            ->call('openBillModal', $ticket->id)
            ->assertSet('showBillModal', true)
            ->set('billAmount', '2500.00')
            ->set('billVendorId', $vendor->id)
            ->set('billExpenseAccountId', $repairsAccount->id)
            ->set('billDescription', 'Deep cleaning fee')
            ->call('generateVendorBill')
            ->assertSet('showBillModal', false);

        $this->assertDatabaseHas('vendor_bills', [
            'maintenance_request_id' => $ticket->id,
            'vendor_id' => $vendor->id,
            'total_amount' => '2500.00',
        ]);

        $this->assertSame('2500.00', $ticket->fresh()->cost);
    }
}
