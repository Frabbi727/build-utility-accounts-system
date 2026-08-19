<?php

namespace Tests\Feature\Expenses;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Enums\VendorBillStatus;
use App\Exceptions\InvalidJournalEntryException;
use App\Models\JournalLine;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Services\Expenses\PayVendorBill;
use App\Services\Expenses\RecordVendorBill;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VendorBillTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journal;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);
        $this->vendor = Vendor::factory()->create();
    }

    /**
     * @param  list<array{account_id: int, description: string, amount: string}>|null  $items
     */
    private function recordBill(?array $items = null): VendorBill
    {
        return app(RecordVendorBill::class)->handle(
            $this->vendor,
            Carbon::parse('2026-08-05'),
            Carbon::parse('2026-09-05'),
            'August lift servicing',
            $items ?? [[
                'account_id' => $this->journal->account(AccountCode::LiftMaintenance)->id,
                'description' => 'Quarterly service',
                'amount' => '8000.00',
            ]],
        );
    }

    public function test_a_vendor_bill_debits_the_expense_and_credits_payable(): void
    {
        $bill = $this->recordBill();

        $this->assertSame('8000.00', (string) $bill->total_amount);
        $this->assertSame('8000.00', $this->journal->balanceFor($this->journal->account(AccountCode::LiftMaintenance)));
        $this->assertSame('8000.00', $this->journal->balanceFor($this->journal->account(AccountCode::AccountsPayable)));
        $this->assertLedgerIsBalanced();
    }

    public function test_a_bill_can_span_several_expense_accounts(): void
    {
        $bill = $this->recordBill([
            [
                'account_id' => $this->journal->account(AccountCode::Cleaning)->id,
                'description' => 'Cleaning',
                'amount' => '3000.00',
            ],
            [
                'account_id' => $this->journal->account(AccountCode::RepairsMaintenance)->id,
                'description' => 'Repairs',
                'amount' => '2500.00',
            ],
        ]);

        $this->assertSame('5500.00', (string) $bill->total_amount);
        $this->assertSame('3000.00', $this->journal->balanceFor($this->journal->account(AccountCode::Cleaning)));
        $this->assertSame('2500.00', $this->journal->balanceFor($this->journal->account(AccountCode::RepairsMaintenance)));
        $this->assertSame('5500.00', $this->journal->balanceFor($this->journal->account(AccountCode::AccountsPayable)));
        $this->assertLedgerIsBalanced();
    }

    public function test_a_bill_with_no_items_is_rejected(): void
    {
        $this->expectException(InvalidJournalEntryException::class);

        $this->recordBill([]);
    }

    public function test_paying_a_bill_in_full_clears_the_payable(): void
    {
        $bill = $this->recordBill();

        app(PayVendorBill::class)->handle($bill, '8000.00', PaymentMethod::Bank, Carbon::parse('2026-09-01'));

        $this->assertSame('0.00', $this->journal->balanceFor($this->journal->account(AccountCode::AccountsPayable)));
        // Money left the bank; the expense itself stays recognised.
        $this->assertSame('-8000.00', $this->journal->balanceFor($this->journal->account(AccountCode::Bank)));
        $this->assertSame('8000.00', $this->journal->balanceFor($this->journal->account(AccountCode::LiftMaintenance)));
        $this->assertSame(VendorBillStatus::Paid, $bill->fresh()->status);
        $this->assertLedgerIsBalanced();
    }

    public function test_a_partial_payment_leaves_the_remainder_payable(): void
    {
        $bill = $this->recordBill();

        app(PayVendorBill::class)->handle($bill, '3000.00', PaymentMethod::Cash, Carbon::parse('2026-09-01'));

        $this->assertSame('5000.00', $this->journal->balanceFor($this->journal->account(AccountCode::AccountsPayable)));
        $this->assertSame('5000.00', $bill->fresh()->outstandingAmount());
        $this->assertSame(VendorBillStatus::PartiallyPaid, $bill->fresh()->status);
        $this->assertLedgerIsBalanced();
    }

    public function test_successive_payments_settle_the_bill(): void
    {
        $bill = $this->recordBill();

        app(PayVendorBill::class)->handle($bill, '3000.00', PaymentMethod::Cash, Carbon::parse('2026-09-01'));
        app(PayVendorBill::class)->handle($bill, '5000.00', PaymentMethod::Bank, Carbon::parse('2026-09-10'));

        $this->assertSame(VendorBillStatus::Paid, $bill->fresh()->status);
        $this->assertSame('0.00', $bill->fresh()->outstandingAmount());
        $this->assertLedgerIsBalanced();
    }

    public function test_overpaying_a_bill_is_refused(): void
    {
        $bill = $this->recordBill();

        $this->expectException(InvalidJournalEntryException::class);

        app(PayVendorBill::class)->handle($bill, '9000.00', PaymentMethod::Bank, Carbon::parse('2026-09-01'));
    }

    public function test_a_non_positive_payment_is_refused(): void
    {
        $bill = $this->recordBill();

        $this->expectException(InvalidJournalEntryException::class);

        app(PayVendorBill::class)->handle($bill, '0', PaymentMethod::Bank, Carbon::parse('2026-09-01'));
    }

    public function test_the_bill_and_payment_are_linked_to_their_journal_entries(): void
    {
        $bill = $this->recordBill();
        $payment = app(PayVendorBill::class)->handle($bill, '8000.00', PaymentMethod::Bank, Carbon::parse('2026-09-01'));

        $this->assertSame($bill->id, $bill->journalEntries()->firstOrFail()->source_id);
        $this->assertSame($payment->id, $payment->journalEntries()->firstOrFail()->source_id);
    }

    private function assertLedgerIsBalanced(): void
    {
        $debits = (string) (JournalLine::sum('debit') ?: '0');
        $credits = (string) (JournalLine::sum('credit') ?: '0');

        $this->assertSame(0, bccomp($debits, $credits, 2), "Ledger out of balance: {$debits} vs {$credits}.");
    }
}
