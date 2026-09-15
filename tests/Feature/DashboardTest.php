<?php

namespace Tests\Feature;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Livewire\Dashboard;
use App\Models\Building;
use App\Models\Expense;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use App\Support\JournalLineData;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_staff_dashboard_renders_metrics_and_actions(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Admin->value);

        $building = Building::factory()->create();
        app(CurrentBuilding::class)->set($building->id);

        $owner = Owner::factory()->create();
        Flat::factory()->create([
            'building_id' => $building->id,
            'owner_id' => $owner->id,
            'number' => 'A-101',
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee(__('dashboard.financial_overview'))
            ->assertSee(__('dashboard.operational_pulse'))
            ->assertSee(__('dashboard.collection_rate'))
            ->assertSee(__('dashboard.liquid_funds'))
            ->assertSee(__('dashboard.defaulter_watchlist'));
    }

    public function test_staff_dashboard_calculates_collections_and_expenses_accurately(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Admin->value);

        $building = Building::factory()->create();
        app(CurrentBuilding::class)->set($building->id);

        $owner = Owner::factory()->create();
        $flat = Flat::factory()->create([
            'building_id' => $building->id,
            'owner_id' => $owner->id,
            'number' => 'A-101',
        ]);

        // Create a bill for current month
        ServiceChargeBill::factory()->create([
            'flat_id' => $flat->id,
            'billing_month' => now()->startOfMonth(),
            'total_amount' => '5000.00',
            'status' => BillStatus::Unpaid,
        ]);

        // Create a payment for current month
        Payment::factory()->create([
            'flat_id' => $flat->id,
            'amount' => '3000.00',
            'received_on' => now(),
        ]);

        // Create an expense for current month
        $cashAccount = app(JournalService::class)->account(AccountCode::CashInHand);
        Expense::create([
            'building_id' => $building->id,
            'account_id' => $cashAccount->id,
            'voucher_no' => 'V-001',
            'amount' => '1000.00',
            'method' => PaymentMethod::Cash,
            'spent_on' => now()->toDateString(),
            'description' => 'Cleaning supplies',
        ]);

        // Create an active meter and reading
        $meter = Meter::factory()->create([
            'building_id' => $building->id,
            'is_active' => true,
        ]);
        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'billing_month' => now()->startOfMonth(),
            'reading_date' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertViewHas('collectedThisMonth', '3000.00')
            ->assertViewHas('billedThisMonth', '5000.00')
            ->assertViewHas('collectionPercentage', 60)
            ->assertViewHas('expensesThisMonth', '1000.00')
            ->assertViewHas('netCashflow', '2000.00')
            ->assertViewHas('isSurplus', true)
            ->assertViewHas('totalMetersCount', 1)
            ->assertViewHas('recordedMetersCount', 1)
            ->assertViewHas('isMeterReadingComplete', true);
    }

    public function test_resident_dashboard_renders_hero_status_and_bill(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);

        $building = Building::factory()->create();
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        Flat::factory()->create([
            'building_id' => $building->id,
            'owner_id' => $owner->id,
            'number' => 'Flat 4A',
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Flat 4A')
            ->assertSee(__('dashboard.latest_bill'))
            ->assertSee(__('dashboard.recent_payments'));
    }

    public function test_resident_can_submit_payment_proof(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);

        $building = Building::factory()->create();
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create([
            'building_id' => $building->id,
            'owner_id' => $owner->id,
            'number' => 'B-202',
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('openPaymentModal', '2500.00')
            ->set('submissionAmount', '2500.00')
            ->set('submissionMethod', PaymentMethod::Bkash->value)
            ->set('submissionReference', 'TRX998877')
            ->set('submissionDate', now()->toDateString())
            ->call('submitPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payment_submissions', [
            'flat_id' => $flat->id,
            'amount' => '2500.00',
            'reference_number' => 'TRX998877',
        ]);
    }

    public function test_resident_can_report_maintenance_ticket(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);

        $building = Building::factory()->create();
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->create([
            'building_id' => $building->id,
            'owner_id' => $owner->id,
            'number' => 'C-303',
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('openTicketModal')
            ->set('ticketTitle', 'Water tap leaking')
            ->set('ticketCategory', MaintenanceCategory::Plumbing->value)
            ->set('ticketPriority', MaintenancePriority::High->value)
            ->set('ticketDescription', 'The bathroom tap is continuously leaking.')
            ->call('submitTicket')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('maintenance_requests', [
            'flat_id' => $flat->id,
            'title' => 'Water tap leaking',
            'category' => MaintenanceCategory::Plumbing->value,
            'priority' => MaintenancePriority::High->value,
        ]);
    }
}
