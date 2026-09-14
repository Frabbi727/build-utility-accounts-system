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

class RecordPaymentCustomAllocationTest extends TestCase
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

    public function test_payment_can_be_allocated_to_specific_newer_bill_leaving_older_unpaid(): void
    {
        $this->generate('2026-06-01');
        $this->generate('2026-07-01');

        $bills = $this->flat->serviceChargeBills()->orderBy('billing_month')->get();
        $juneBill = $bills[0];
        $julyBill = $bills[1];

        // Operator explicitly pays the July bill instead of June
        $payment = app(RecordPayment::class)->handle(
            flat: $this->flat,
            amount: '3000.00',
            method: PaymentMethod::Cash,
            receivedOn: Carbon::parse('2026-07-15'),
            reference: 'July only',
            customAllocations: [
                $julyBill->id => '3000.00',
            ],
        );

        $juneBill->refresh();
        $julyBill->refresh();

        $this->assertSame(BillStatus::Unpaid, $juneBill->status);
        $this->assertSame(BillStatus::Paid, $julyBill->status);
        $this->assertCount(1, $payment->allocations);
        $this->assertSame($julyBill->id, $payment->allocations->first()->service_charge_bill_id);
    }

    public function test_custom_allocation_with_partial_amounts_and_excess_advance(): void
    {
        $this->generate('2026-06-01');
        $this->generate('2026-07-01');

        $bills = $this->flat->serviceChargeBills()->orderBy('billing_month')->get();

        // 1000 to June bill, 1500 to July bill, and 500 excess into Advance
        $payment = app(RecordPayment::class)->handle(
            flat: $this->flat,
            amount: '3000.00',
            method: PaymentMethod::Bank,
            receivedOn: Carbon::parse('2026-07-15'),
            customAllocations: [
                $bills[0]->id => '1000.00',
                $bills[1]->id => '1500.00',
            ],
        );

        $bills[0]->refresh();
        $bills[1]->refresh();

        $this->assertSame(BillStatus::PartiallyPaid, $bills[0]->status);
        $this->assertSame('2000.00', $bills[0]->outstandingAmount());

        $this->assertSame(BillStatus::PartiallyPaid, $bills[1]->status);
        $this->assertSame('1500.00', $bills[1]->outstandingAmount());

        $this->assertSame('500.00', $this->journal->balanceFor(
            $this->journal->account(AccountCode::AdvanceFromOwners),
            $this->flat->id
        ));
    }

    public function test_custom_allocation_throws_if_allocated_sum_exceeds_payment_amount(): void
    {
        $this->generate('2026-06-01');
        $bills = $this->flat->serviceChargeBills()->get();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceed total payment amount');

        app(RecordPayment::class)->handle(
            flat: $this->flat,
            amount: '1000.00',
            method: PaymentMethod::Cash,
            receivedOn: Carbon::parse('2026-06-15'),
            customAllocations: [
                $bills[0]->id => '2000.00',
            ],
        );
    }

    public function test_custom_allocation_throws_if_bill_belongs_to_another_flat(): void
    {
        $otherFlat = Flat::factory()->for($this->building)->create();
        $this->generate('2026-06-01');

        $otherBill = $otherFlat->serviceChargeBills()->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong to this flat');

        app(RecordPayment::class)->handle(
            flat: $this->flat,
            amount: '1000.00',
            method: PaymentMethod::Cash,
            receivedOn: Carbon::parse('2026-06-15'),
            customAllocations: [
                $otherBill->id => '1000.00',
            ],
        );
    }
}
