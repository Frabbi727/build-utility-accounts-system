<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Enums\PaymentMethod;
use App\Models\Building;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Services\Billing\BillSummary;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\JournalService;
use App\Support\JournalLineData;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillSummaryTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->flatRate('3000.00')->create(['due_day_of_month' => 10]);
        $this->flat = Flat::factory()->for($this->building)->create(['number' => 'A-4']);
    }

    private function generate(string $month): ServiceChargeBill
    {
        app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse($month));

        return ServiceChargeBill::where('flat_id', $this->flat->id)
            ->whereDate('billing_month', Carbon::parse($month)->startOfMonth())
            ->firstOrFail();
    }

    private function pay(string $amount, string $on): void
    {
        app(RecordPayment::class)->handle(
            $this->flat,
            $amount,
            PaymentMethod::Cash,
            Carbon::parse($on),
        );
    }

    public function test_a_first_bill_has_nothing_brought_forward(): void
    {
        $bill = $this->generate('2026-06-01');

        $summary = app(BillSummary::class)->for($bill);

        $this->assertSame(0, bccomp($summary->broughtForward, '0.00', 2));
        $this->assertSame(0, bccomp($summary->monthCharges, '3000.00', 2));
        $this->assertSame(0, bccomp($summary->paidOnThisBill, '0.00', 2));
        $this->assertSame(0, bccomp($summary->thisBillOutstanding, '3000.00', 2));
        $this->assertSame(0, bccomp($summary->totalDue, '3000.00', 2));
        $this->assertSame([], $summary->priorOpenBills);
    }

    public function test_an_unpaid_prior_bill_is_carried_forward_and_listed(): void
    {
        $june = $this->generate('2026-06-01');
        $july = $this->generate('2026-07-01');

        $summary = app(BillSummary::class)->for($july);

        $this->assertSame(0, bccomp($summary->broughtForward, '3000.00', 2));
        $this->assertSame(0, bccomp($summary->monthCharges, '3000.00', 2));
        $this->assertSame(0, bccomp($summary->totalDue, '6000.00', 2));
        $this->assertCount(1, $summary->priorOpenBills);
        $this->assertSame($june->id, $summary->priorOpenBills[0]['bill']->id);
        $this->assertSame(0, bccomp($summary->priorOpenBills[0]['outstanding'], '3000.00', 2));
    }

    public function test_a_partial_payment_reduces_the_bill_it_settles(): void
    {
        $june = $this->generate('2026-06-01');
        $this->pay('1200.00', '2026-06-12');

        $summary = app(BillSummary::class)->for($june->fresh());

        $this->assertSame(0, bccomp($summary->paidOnThisBill, '1200.00', 2));
        $this->assertSame(0, bccomp($summary->thisBillOutstanding, '1800.00', 2));
        $this->assertSame(0, bccomp($summary->totalDue, '1800.00', 2));
        $this->assertSame(0, bccomp($summary->broughtForward, '0.00', 2));
        $this->assertCount(1, $summary->paymentsReceived);
        $this->assertSame(0, bccomp($summary->paymentsReceived[0]->amount, '1200.00', 2));
    }

    public function test_brought_forward_plus_this_bill_always_equals_the_ledger_due(): void
    {
        $this->generate('2026-06-01');
        $this->pay('1000.00', '2026-06-12');
        $july = $this->generate('2026-07-01');

        $summary = app(BillSummary::class)->for($july);

        $this->assertSame(
            0,
            bccomp(bcadd($summary->broughtForward, $summary->thisBillOutstanding, 2), $summary->totalDue, 2),
            'brought forward plus this bill must reconcile to the ledger receivable',
        );
    }

    public function test_an_overpayment_is_reported_as_an_advance(): void
    {
        $june = $this->generate('2026-06-01');
        $this->pay('4000.00', '2026-06-12');

        $summary = app(BillSummary::class)->for($june->fresh());

        $this->assertSame(0, bccomp($summary->thisBillOutstanding, '0.00', 2));
        $this->assertSame(0, bccomp($summary->totalDue, '0.00', 2));
        $this->assertSame(0, bccomp($summary->advanceHeld, '1000.00', 2));
    }

    public function test_a_settled_prior_bill_is_not_listed(): void
    {
        $this->generate('2026-06-01');
        $this->pay('3000.00', '2026-06-12');
        $july = $this->generate('2026-07-01');

        $summary = app(BillSummary::class)->for($july);

        $this->assertSame([], $summary->priorOpenBills);
        $this->assertSame(0, bccomp($summary->broughtForward, '0.00', 2));
    }

    public function test_unbilled_arrears_absorb_a_receivable_with_no_bill_behind_it(): void
    {
        // An opening balance posts a receivable with no bill; the sheet must still add up.
        $this->postOpeningReceivable('500.00');

        $june = $this->generate('2026-06-01');

        $summary = app(BillSummary::class)->for($june);

        $this->assertSame(0, bccomp($summary->totalDue, '3500.00', 2));
        $this->assertSame(0, bccomp($summary->broughtForward, '500.00', 2));
        $this->assertSame([], $summary->priorOpenBills);
        $this->assertSame(0, bccomp($summary->unbilledArrears, '500.00', 2));
    }

    private function postOpeningReceivable(string $amount): void
    {
        $journal = app(JournalService::class);

        $journal->post(
            Carbon::parse('2026-05-31'),
            'Opening receivable',
            [
                JournalLineData::debit(
                    $journal->account(AccountCode::ServiceChargeReceivable),
                    $amount,
                    $this->flat->id,
                ),
                JournalLineData::credit(
                    $journal->account(AccountCode::GeneralFund),
                    $amount,
                ),
            ],
        );
    }
}
