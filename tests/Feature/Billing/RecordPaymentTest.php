<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Models\Building;
use App\Models\Flat;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecordPaymentTest extends TestCase
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

    public function test_it_allocates_to_the_oldest_due_first(): void
    {
        $this->generate('2026-06-01');
        $this->generate('2026-07-01');
        $this->generate('2026-08-01');

        // Enough for the first bill and half the second.
        app(RecordPayment::class)->handle($this->flat, '4500.00', PaymentMethod::Cash, Carbon::parse('2026-08-20'));

        $bills = $this->flat->serviceChargeBills()->orderBy('billing_month')->get();

        $this->assertSame(BillStatus::Paid, $bills[0]->status);
        $this->assertSame(BillStatus::PartiallyPaid, $bills[1]->status);
        $this->assertSame(BillStatus::Unpaid, $bills[2]->status);
        $this->assertSame('1500.00', $bills[1]->outstandingAmount());
    }

    public function test_a_partial_payment_leaves_the_correct_outstanding_balance(): void
    {
        $this->generate('2026-08-01');

        app(RecordPayment::class)->handle($this->flat, '1200.00', PaymentMethod::Bank, Carbon::parse('2026-08-20'));

        $this->assertSame('1800.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::ServiceChargeReceivable), $this->flat->id
        ));
        $this->assertSame(BillStatus::PartiallyPaid, $this->flat->serviceChargeBills()->first()->status);
    }

    public function test_mobile_wallet_payments_land_in_the_bank_account(): void
    {
        $this->generate('2026-08-01');

        app(RecordPayment::class)->handle($this->flat, '3000.00', PaymentMethod::Nagad, Carbon::parse('2026-08-20'));

        $this->assertSame('3000.00', $this->journal->balanceFor($this->journal->account(AccountCode::Bank)));
        $this->assertSame('0.00', $this->journal->balanceFor($this->journal->account(AccountCode::CashInHand)));
    }

    public function test_successive_payments_settle_a_bill_incrementally(): void
    {
        $this->generate('2026-08-01');

        app(RecordPayment::class)->handle($this->flat, '1000.00', PaymentMethod::Cash, Carbon::parse('2026-08-10'));
        app(RecordPayment::class)->handle($this->flat, '2000.00', PaymentMethod::Cash, Carbon::parse('2026-08-20'));

        $this->assertSame(BillStatus::Paid, $this->flat->serviceChargeBills()->first()->status);
        $this->assertSame('0.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::ServiceChargeReceivable), $this->flat->id
        ));
    }

    public function test_a_payment_with_no_dues_is_held_entirely_as_advance(): void
    {
        $payment = app(RecordPayment::class)->handle(
            $this->flat, '3000.00', PaymentMethod::Cash, Carbon::parse('2026-08-20')
        );

        $this->assertCount(0, $payment->allocations);
        $this->assertSame('3000.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::AdvanceFromOwners), $this->flat->id
        ));
    }

    public function test_the_payment_is_linked_to_its_journal_entry_as_the_source(): void
    {
        $this->generate('2026-08-01');

        $payment = app(RecordPayment::class)->handle(
            $this->flat, '3000.00', PaymentMethod::Cash, Carbon::parse('2026-08-20')
        );

        $this->assertSame($payment->id, $payment->journalEntries()->firstOrFail()->source_id);
    }

    public function test_one_flats_payment_never_touches_another_flats_sub_ledger(): void
    {
        $other = Flat::factory()->for($this->building)->create();
        $this->generate('2026-08-01');

        app(RecordPayment::class)->handle($this->flat, '3000.00', PaymentMethod::Cash, Carbon::parse('2026-08-20'));

        $receivable = $this->journal->account(AccountCode::ServiceChargeReceivable);

        $this->assertSame('0.00', $this->journal->balanceFor($receivable, $this->flat->id));
        $this->assertSame('3000.00', $this->journal->balanceFor($receivable, $other->id));
    }
}
