<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Models\Building;
use App\Models\Flat;
use App\Models\JournalEntry;
use App\Models\PaymentAllocation;
use App\Services\Billing\ApplyAdvances;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * An owner who pays more than they owe is in credit. The next bill generated for
 * their flat must absorb that credit automatically.
 */
class ApplyAdvancesTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journal;

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);
        $this->building = Building::factory()->flatRate('3000.00')->create();
        $this->flat = Flat::factory()->for($this->building)->create();
    }

    private function generate(string $month): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse($month));
    }

    private function advanceBalance(): string
    {
        return $this->journal->balanceFor(
            $this->journal->account(AccountCode::AdvanceFromOwners),
            $this->flat->id,
        );
    }

    private function receivableBalance(): string
    {
        return $this->journal->balanceFor(
            $this->journal->account(AccountCode::ServiceChargeReceivable),
            $this->flat->id,
        );
    }

    public function test_an_advance_is_applied_to_the_next_bill_generated(): void
    {
        $this->generate('2026-06-01');

        // Pays 5000 against a 3000 bill: 2000 becomes an advance.
        app(RecordPayment::class)->handle($this->flat, '5000.00', PaymentMethod::Cash, Carbon::parse('2026-06-10'));

        $this->assertSame('2000.00', $this->advanceBalance());

        $this->generate('2026-07-01');

        $july = $this->flat->serviceChargeBills()->orderByDesc('billing_month')->first();

        // 2000 of the 3000 July bill is covered by the advance.
        $this->assertSame(BillStatus::PartiallyPaid, $july->status);
        $this->assertSame('1000.00', $july->outstandingAmount());
        $this->assertSame('0.00', $this->advanceBalance());
        $this->assertSame('1000.00', $this->receivableBalance());
    }

    public function test_an_advance_covering_the_whole_bill_leaves_it_paid_and_the_ledger_at_zero(): void
    {
        $this->generate('2026-06-01');

        // 3000 for June plus 3000 of credit.
        app(RecordPayment::class)->handle($this->flat, '6000.00', PaymentMethod::Cash, Carbon::parse('2026-06-10'));

        $this->generate('2026-07-01');

        $july = $this->flat->serviceChargeBills()->orderByDesc('billing_month')->first();

        $this->assertSame(BillStatus::Paid, $july->status);
        $this->assertSame('0.00', $july->outstandingAmount());
        $this->assertSame('0.00', $this->advanceBalance());
        $this->assertSame('0.00', $this->receivableBalance());
    }

    public function test_a_flat_with_no_advance_bills_exactly_as_before(): void
    {
        $this->generate('2026-06-01');
        $this->generate('2026-07-01');

        $this->assertSame('6000.00', $this->receivableBalance());
        $this->assertSame('0.00', $this->advanceBalance());

        foreach ($this->flat->serviceChargeBills as $bill) {
            $this->assertSame(BillStatus::Unpaid, $bill->status);
        }
    }

    public function test_applying_advances_twice_neither_double_allocates_nor_double_posts(): void
    {
        $this->generate('2026-06-01');

        app(RecordPayment::class)->handle($this->flat, '5000.00', PaymentMethod::Cash, Carbon::parse('2026-06-10'));

        $this->generate('2026-07-01');

        $july = $this->flat->serviceChargeBills()->orderByDesc('billing_month')->first();

        $entriesBefore = JournalEntry::count();
        $allocationsBefore = PaymentAllocation::count();

        // Re-running against the same bill must find nothing left to apply.
        $this->assertSame('0.00', app(ApplyAdvances::class)->handle($july));

        $this->assertSame($entriesBefore, JournalEntry::count());
        $this->assertSame($allocationsBefore, PaymentAllocation::count());
        $this->assertSame('1000.00', $july->fresh()->outstandingAmount());
        $this->assertSame('0.00', $this->advanceBalance());
    }

    public function test_a_reopened_bill_grows_the_existing_allocation_instead_of_duplicating_it(): void
    {
        $this->generate('2026-06-01');

        // 3000 for June plus 3000 of credit, so July is fully covered by the advance.
        app(RecordPayment::class)->handle($this->flat, '9000.00', PaymentMethod::Cash, Carbon::parse('2026-06-10'));

        $this->generate('2026-07-01');

        $july = $this->flat->serviceChargeBills()->orderByDesc('billing_month')->first();
        $this->assertSame(BillStatus::Paid, $july->status);

        $payment = $this->flat->payments()->sole();
        $this->assertSame(1, $payment->allocations()->where('service_charge_bill_id', $july->id)->count());

        // A late fee re-opens a bill this payment has already paid into. Because
        // payment_allocations is unique on (payment_id, bill_id), a second allocation
        // row here would violate the constraint.
        $july->total_amount = bcadd((string) $july->total_amount, '500.00', 2);
        $july->save();

        $this->assertSame('500.00', app(ApplyAdvances::class)->handle($july->fresh()));

        $allocations = $payment->allocations()->where('service_charge_bill_id', $july->id)->get();

        $this->assertCount(1, $allocations, 'The existing allocation should grow, not duplicate.');
        $this->assertSame('3500.00', (string) $allocations->first()->amount);
        $this->assertSame('0.00', $july->fresh()->outstandingAmount());
        $this->assertSame(BillStatus::Paid, $july->fresh()->status);
    }
}
