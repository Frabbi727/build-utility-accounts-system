<?php

namespace Tests\Feature\Livewire;

use App\Enums\AccountCode;
use App\Enums\PeriodStatus;
use App\Enums\Role;
use App\Livewire\Accounting\PeriodList;
use App\Livewire\ExpenseList;
use App\Livewire\GenerateBills;
use App\Livewire\Masters\VendorList;
use App\Livewire\RecordPaymentForm;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Expense;
use App\Models\Flat;
use App\Models\Payment;
use App\Models\ServiceChargeBill;
use App\Models\User;
use App\Models\Vendor;
use App\Services\JournalService;
use App\Support\CurrentBuilding;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every irreversible action is two-step: asking must change nothing, and only the
 * confirmed call may mutate. These tests pin both halves.
 */
class ConfirmationGuardsTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journal;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->journal = app(JournalService::class);
        $this->building = Building::factory()->flatRate('3000.00')->create();

        app(CurrentBuilding::class)->set($this->building->id);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    public function test_asking_to_delete_a_vendor_does_not_delete_it(): void
    {
        $vendor = Vendor::factory()->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(VendorList::class)
            ->call('confirmDelete', $vendor->id)
            ->assertSet('deletingId', $vendor->id);

        $this->assertDatabaseHas('vendors', ['id' => $vendor->id]);
    }

    public function test_confirming_the_delete_removes_the_vendor(): void
    {
        $vendor = Vendor::factory()->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(VendorList::class)
            ->call('confirmDelete', $vendor->id)
            ->call('delete', $vendor->id)
            ->assertSet('deletingId', null);

        $this->assertDatabaseMissing('vendors', ['id' => $vendor->id]);
    }

    public function test_cancelling_a_delete_leaves_the_record_alone(): void
    {
        $vendor = Vendor::factory()->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(VendorList::class)
            ->call('confirmDelete', $vendor->id)
            ->call('cancelDelete')
            ->assertSet('deletingId', null);

        $this->assertDatabaseHas('vendors', ['id' => $vendor->id]);
    }

    public function test_asking_to_unlock_a_period_does_not_unlock_it(): void
    {
        $period = AccountingPeriod::create([
            'year' => 2026, 'month' => 7, 'status' => PeriodStatus::Locked, 'locked_at' => now(),
        ]);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(PeriodList::class)
            ->call('askConfirm', 'unlock', $period->id);

        $this->assertSame(PeriodStatus::Locked, $period->fresh()->status);
    }

    public function test_confirming_unlocks_the_period_and_writes_an_audit_row(): void
    {
        $period = AccountingPeriod::create([
            'year' => 2026, 'month' => 7, 'status' => PeriodStatus::Locked, 'locked_at' => now(),
        ]);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(PeriodList::class)
            ->call('askConfirm', 'unlock', $period->id)
            ->call('runConfirmed')
            ->assertSet('pendingConfirm', null);

        $this->assertSame(PeriodStatus::Open, $period->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'accounting_period.unlocked',
            'subject_id' => $period->id,
        ]);
    }

    public function test_run_confirmed_refuses_an_action_outside_the_allowlist(): void
    {
        $vendor = Vendor::factory()->create();

        // A crafted request naming a method the screen never offered for confirmation.
        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(PeriodList::class)
            ->call('askConfirm', 'render', $vendor->id)
            ->assertSet('pendingConfirm', null);

        $this->assertDatabaseHas('vendors', ['id' => $vendor->id]);
    }

    public function test_asking_to_generate_bills_generates_nothing(): void
    {
        Flat::factory()->for($this->building)->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(GenerateBills::class)
            ->set('month', '2026-08')
            ->call('askGenerate')
            ->assertHasNoErrors();

        $this->assertSame(0, ServiceChargeBill::count());
    }

    public function test_confirming_generates_the_bills(): void
    {
        Flat::factory()->for($this->building)->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(GenerateBills::class)
            ->set('month', '2026-08')
            ->call('askGenerate')
            ->call('runConfirmed')
            ->assertHasNoErrors();

        $this->assertSame(1, ServiceChargeBill::count());
    }

    public function test_asking_to_record_a_payment_takes_no_money(): void
    {
        $flat = Flat::factory()->for($this->building)->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(RecordPaymentForm::class)
            ->set('flatId', $flat->id)
            ->set('amount', '500.00')
            ->call('askSave')
            ->assertHasNoErrors();

        $this->assertSame(0, Payment::count());
    }

    public function test_confirming_records_the_payment(): void
    {
        $flat = Flat::factory()->for($this->building)->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(RecordPaymentForm::class)
            ->set('flatId', $flat->id)
            ->set('amount', '500.00')
            ->call('askSave')
            ->call('runConfirmed')
            ->assertHasNoErrors();

        $this->assertSame(1, Payment::count());
    }

    public function test_a_payment_dated_in_the_future_is_rejected(): void
    {
        $flat = Flat::factory()->for($this->building)->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(RecordPaymentForm::class)
            ->set('flatId', $flat->id)
            ->set('amount', '500.00')
            ->set('receivedOn', now()->addMonth()->toDateString())
            ->call('askSave')
            ->assertHasErrors('receivedOn');

        $this->assertSame(0, Payment::count());
    }

    public function test_a_payment_cannot_land_on_a_flat_in_another_building(): void
    {
        $otherFlat = Flat::factory()->for(Building::factory()->flatRate('3000.00'))->create();

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(RecordPaymentForm::class)
            ->set('flatId', $otherFlat->id)
            ->set('amount', '500.00')
            ->call('save')
            ->assertHasErrors('flatId');

        $this->assertSame(0, Payment::count());
    }

    public function test_posting_into_a_locked_period_is_a_field_error_not_a_crash(): void
    {
        AccountingPeriod::create([
            'year' => 2026, 'month' => 8, 'status' => PeriodStatus::Locked, 'locked_at' => now(),
        ]);

        $account = $this->journal->account(AccountCode::Cleaning);

        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(ExpenseList::class)
            ->set('accountId', $account->id)
            ->set('amount', '250.00')
            ->set('spentOn', '2026-08-10')
            ->set('description', 'Backdated into a closed month')
            ->call('save')
            ->assertHasErrors('spentOn');

        $this->assertSame(0, Expense::count());
    }

    public function test_the_audit_log_records_a_period_lock(): void
    {
        $period = AccountingPeriod::create(['year' => 2026, 'month' => 6, 'status' => PeriodStatus::Open]);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(PeriodList::class)
            ->call('askConfirm', 'lock', $period->id)
            ->call('runConfirmed');

        $this->assertTrue(
            AuditLog::where('action', 'accounting_period.locked')->where('subject_id', $period->id)->exists()
        );
    }
}
