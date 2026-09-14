<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Models\Building;
use App\Models\Flat;
use App\Models\User;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\Billing\ReversePayment;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReversePaymentTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journal;

    private Building $building;

    private Flat $flat;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);
        $this->building = Building::factory()->flatRate('3000.00')->create();
        $this->flat = Flat::factory()->for($this->building)->create();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function generate(string $month): void
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse($month));
    }

    public function test_reversing_payment_reopens_bill_and_posts_balanced_contra_entry(): void
    {
        $this->generate('2026-08-01');

        $payment = app(RecordPayment::class)->handle(
            $this->flat,
            '3000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-20')
        );

        $bill = $this->flat->serviceChargeBills()->firstOrFail();
        $this->assertSame(BillStatus::Paid, $bill->status);
        $this->assertSame('0.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::ServiceChargeReceivable),
            $this->flat->id
        ));

        // Now reverse the payment
        $reversed = app(ReversePayment::class)->handle($payment, 'Cheque bounced', Carbon::parse('2026-08-22'));

        $this->assertTrue($reversed->isReversed());
        $this->assertSame('Cheque bounced', $reversed->reversal_reason);
        $this->assertSame($this->user->id, $reversed->reversed_by);

        // Bill should be back to Unpaid
        $bill->refresh();
        $this->assertSame(BillStatus::Unpaid, $bill->status);
        $this->assertSame('3000.00', $bill->outstandingAmount());
        $this->assertCount(0, $reversed->allocations);

        // Sub-ledger receivable should be back to 3000.00
        $this->assertSame('3000.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::ServiceChargeReceivable),
            $this->flat->id
        ));

        // Cash account should be 0.00
        $this->assertSame('0.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::CashInHand)
        ));
    }

    public function test_reversing_payment_with_advance_and_allocation_restores_both_accounts(): void
    {
        $this->generate('2026-08-01');

        // Pay 5000.00 (3000 allocated to bill, 2000 as advance)
        $payment = app(RecordPayment::class)->handle(
            $this->flat,
            '5000.00',
            PaymentMethod::Bank,
            Carbon::parse('2026-08-20')
        );

        $this->assertSame('0.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::ServiceChargeReceivable),
            $this->flat->id
        ));
        $this->assertSame('2000.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::AdvanceFromOwners),
            $this->flat->id
        ));
        $this->assertSame('5000.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::Bank)
        ));

        // Reverse payment
        app(ReversePayment::class)->handle($payment, 'Wrong account deposit');

        $this->assertSame('3000.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::ServiceChargeReceivable),
            $this->flat->id
        ));
        $this->assertSame('0.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::AdvanceFromOwners),
            $this->flat->id
        ));
        $this->assertSame('0.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::Bank)
        ));
    }

    public function test_reversing_pure_advance_payment_clears_advance(): void
    {
        // No dues, pay 3000 advance
        $payment = app(RecordPayment::class)->handle(
            $this->flat,
            '3000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-20')
        );

        $this->assertSame('3000.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::AdvanceFromOwners),
            $this->flat->id
        ));

        app(ReversePayment::class)->handle($payment, 'Customer requested refund');

        $this->assertSame('0.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::AdvanceFromOwners),
            $this->flat->id
        ));
        $this->assertSame('0.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::CashInHand)
        ));
    }

    public function test_cannot_reverse_already_reversed_payment(): void
    {
        $this->generate('2026-08-01');

        $payment = app(RecordPayment::class)->handle(
            $this->flat,
            '3000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-20')
        );

        app(ReversePayment::class)->handle($payment, 'First reversal');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This payment has already been reversed.');

        app(ReversePayment::class)->handle($payment, 'Second reversal attempt');
    }
}
