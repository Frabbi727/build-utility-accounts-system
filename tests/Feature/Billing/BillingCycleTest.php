<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Models\Building;
use App\Models\Flat;
use App\Models\JournalLine;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingCycleTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);
    }

    public function test_a_generated_bill_and_its_full_payment_leave_the_flat_ledger_at_zero(): void
    {
        $building = Building::factory()->flatRate('3000.00')->create();
        $flat = Flat::factory()->for($building)->create();

        $bills = app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-08-01'));
        $bill = $bills->first();

        $receivable = $this->journal->account(AccountCode::ServiceChargeReceivable);

        // Accrued when due, not when paid.
        $this->assertSame('3000.00', $this->journal->balanceFor($receivable, $flat->id));

        app(RecordPayment::class)->handle(
            $flat,
            '3000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-15'),
        );

        $this->assertSame('0.00', $this->journal->balanceFor($receivable, $flat->id));
        $this->assertSame('3000.00', $this->journal->balanceFor($this->journal->account(AccountCode::CashInHand)));
        $this->assertSame(BillStatus::Paid, $bill->fresh()->status);
        $this->assertLedgerIsBalanced();
    }

    public function test_the_whole_ledger_stays_balanced_across_the_cycle(): void
    {
        $building = Building::factory()->perSqft('3.50')->create();
        Flat::factory()->count(3)->for($building)->create(['size_sqft' => '1000.00']);

        app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-08-01'));

        foreach ($building->flats as $flat) {
            app(RecordPayment::class)->handle($flat, '2000.00', PaymentMethod::Bkash, Carbon::parse('2026-08-20'));
        }

        $this->assertLedgerIsBalanced();
    }

    public function test_overpayment_is_credited_to_advance_and_receivable_never_goes_negative(): void
    {
        $building = Building::factory()->flatRate('3000.00')->create();
        $flat = Flat::factory()->for($building)->create();

        app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-08-01'));

        app(RecordPayment::class)->handle($flat, '5000.00', PaymentMethod::Cash, Carbon::parse('2026-08-15'));

        $this->assertSame('0.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::ServiceChargeReceivable), $flat->id
        ));
        $this->assertSame('2000.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::AdvanceFromOwners), $flat->id
        ));
        $this->assertLedgerIsBalanced();
    }

    /**
     * The system-wide double-entry invariant: total debits equal total credits.
     */
    private function assertLedgerIsBalanced(): void
    {
        $debits = (string) (JournalLine::sum('debit') ?: '0');
        $credits = (string) (JournalLine::sum('credit') ?: '0');

        $this->assertSame(
            0,
            bccomp($debits, $credits, 2),
            "Ledger is out of balance: debits {$debits} vs credits {$credits}."
        );
    }
}
