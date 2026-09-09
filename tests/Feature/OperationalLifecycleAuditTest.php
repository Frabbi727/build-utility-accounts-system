<?php

namespace Tests\Feature;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\NoticeType;
use App\Enums\Role;
use App\Livewire\Admin\MaintenanceRequestList;
use App\Livewire\Dashboard;
use App\Livewire\Masters\NoticeList;
use App\Livewire\Masters\OwnerList;
use App\Livewire\RecordPaymentForm;
use App\Models\Building;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
use App\Models\Notice;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Billing\GenerateMonthlyBills;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalLifecycleAuditTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private User $admin;

    private Staff $staffMember;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('4000.00')->create([
            'name' => 'Rose Valley Tower',
            'due_day_of_month' => 10,
        ]);
        app(CurrentBuilding::class)->set($this->building->id);

        $this->admin = User::factory()->create(['name' => 'Admin Officer']);
        $this->admin->assignRole(Role::Admin->value);

        $this->staffMember = Staff::factory()->create(['building_id' => $this->building->id, 'name' => 'Karim Technician']);
        $this->vendor = Vendor::factory()->create(['name' => 'CoolTech HVAC Solutions']);
    }

    public function test_complete_end_to_end_operational_lifecycle(): void
    {
        // 1. Owner & Flat setup
        $owner = Owner::factory()->create([
            'name' => 'Tariq Rahman',
            'email' => 'tariq@example.com',
            'user_id' => null,
        ]);

        $flat1 = Flat::factory()->for($this->building)->create([
            'owner_id' => $owner->id,
            'number' => 'A-101',
        ]);

        $flat2 = Flat::factory()->for($this->building)->create([
            'owner_id' => $owner->id,
            'number' => 'A-102',
        ]);

        // 2. Admin provisions user login via OwnerList 1-click modal
        Livewire::actingAs($this->admin)
            ->test(OwnerList::class)
            ->call('openCreateUserModal', $owner->id)
            ->assertSet('showUserModal', true)
            ->set('newUserName', 'Tariq Rahman')
            ->set('newUserEmail', 'tariq.login@example.com')
            ->set('newUserPassword', 'SecurePass123!')
            ->call('provisionUser')
            ->assertHasNoErrors();

        $residentUser = User::where('email', 'tariq.login@example.com')->firstOrFail();
        $this->assertTrue($residentUser->hasRole(Role::Owner->value));
        $this->assertSame($residentUser->id, $owner->fresh()->user_id);

        // 3. Admin publishes emergency circular on Notice Board
        Livewire::actingAs($this->admin)
            ->test(NoticeList::class)
            ->call('create')
            ->set('title', 'Emergency Water Line Maintenance')
            ->set('content', 'Main line valve replacement scheduled for tomorrow morning.')
            ->set('type', NoticeType::Emergency->value)
            ->set('isPinned', true)
            ->call('save')
            ->assertHasNoErrors();

        $notice = Notice::firstOrFail();
        $this->assertSame('Emergency Water Line Maintenance', $notice->title);

        // 4. Monthly bill generation
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));

        // 5. Resident portal experience
        Livewire::actingAs($residentUser)
            ->test(Dashboard::class)
            ->assertSee('Tariq Rahman')
            ->assertSee('A-101')
            ->assertSee('A-102')
            ->assertSee('4,000.00')
            ->assertSee('Emergency Water Line Maintenance')
            // Switch to flat A-102 and submit a service request
            ->call('selectFlat', $flat2->id)
            ->assertSet('selectedFlatId', $flat2->id)
            ->call('openTicketModal')
            ->assertSet('showTicketModal', true)
            ->set('ticketTitle', 'AC unit leaking water')
            ->set('ticketCategory', MaintenanceCategory::Other->value)
            ->set('ticketPriority', MaintenancePriority::High->value)
            ->set('ticketDescription', 'The indoor unit in master bedroom is dripping water on the floor.')
            ->call('submitTicket')
            ->assertHasNoErrors();

        $ticket = MaintenanceRequest::where('flat_id', $flat2->id)->firstOrFail();
        $this->assertSame('AC unit leaking water', $ticket->title);
        $this->assertSame(MaintenanceStatus::Open, $ticket->status);

        // 6. Staff dispatches and resolves ticket
        Livewire::actingAs($this->admin)
            ->test(MaintenanceRequestList::class)
            ->call('edit', $ticket->id)
            ->set('status', MaintenanceStatus::Resolved->value)
            ->set('assignedStaffId', $this->staffMember->id)
            ->set('assignedVendorId', $this->vendor->id)
            ->set('resolutionNotes', 'Replaced drain hose and recharged refrigerant.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(MaintenanceStatus::Resolved, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);

        // 7. Staff collects payment for flat A-101 using deep-link
        Livewire::actingAs($this->admin)
            ->test(RecordPaymentForm::class, ['flat_id' => $flat1->id])
            ->assertSet('flatId', $flat1->id)
            ->set('amount', '4000.00')
            ->set('method', 'cash')
            ->call('askSave')
            ->call('save')
            ->assertHasNoErrors();

        $payment = Payment::where('flat_id', $flat1->id)->firstOrFail();
        $this->assertSame('4000.00', $payment->amount);

        // 8. Resident portal verification after payment & ticket resolution
        Livewire::actingAs($residentUser)
            ->test(Dashboard::class)
            // Flat A-101 balance should now be 0.00
            ->set('selectedFlatId', $flat1->id)
            ->assertSee('0.00')
            ->assertSee($payment->receipt_no)
            // Flat A-102 should display the resolved ticket with notes
            ->call('selectFlat', $flat2->id)
            ->assertSee('AC unit leaking water')
            ->assertSee('Replaced drain hose and recharged refrigerant.');
    }
}
