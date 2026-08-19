<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Enums\LateFeeType;
use App\Enums\PaymentMethod;
use App\Models\Building;
use App\Models\Flat;
use App\Models\JournalEntry;
use App\Models\ServiceChargeBill;
use App\Services\Billing\ApplyLateFees;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A late fee is accrued income: the flat owes more and Late Fee Income goes up. The
 * bill must move with the ledger, and a bill may only be charged once a month.
 */
class ApplyLateFeesTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);
    }

    /**
     * A building with one flat and one 3000.00 bill for June, due on the 10th.
     */
    private function buildingWith(LateFeeType $type, string $amount, int $graceDays = 0): Building
    {
        $building = Building::factory()
            ->flatRate('3000.00')
            ->lateFee($type, $amount, $graceDays)
            ->create(['due_day_of_month' => 10]);

        Flat::factory()->for($building)->create();

        app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-06-01'));

        return $building;
    }

    private function bill(): ServiceChargeBill
    {
        return ServiceChargeBill::sole();
    }

    private function lateFeeIncome(): string
    {
        return $this->journal->balanceFor($this->journal->account(AccountCode::LateFeeIncome));
    }

    public function test_a_percentage_fee_is_charged_once_the_due_date_has_passed(): void
    {
        $building = $this->buildingWith(LateFeeType::Percent, '5.00');

        $charged = app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-15'));

        $this->assertCount(1, $charged);

        $bill = $this->bill();

        // 5% of the 3000.00 outstanding.
        $this->assertSame('3150.00', (string) $bill->total_amount);
        $this->assertSame('150.00', $this->lateFeeIncome());
        $this->assertSame('150.00', (string) $bill->items()->latest('id')->first()->amount);
    }

    public function test_a_fixed_fee_charges_the_flat_amount(): void
    {
        $building = $this->buildingWith(LateFeeType::Fixed, '200.00');

        app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-15'));

        $this->assertSame('3200.00', (string) $this->bill()->total_amount);
        $this->assertSame('200.00', $this->lateFeeIncome());
    }

    public function test_no_fee_is_charged_before_the_due_date(): void
    {
        $building = $this->buildingWith(LateFeeType::Fixed, '200.00');

        $charged = app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-09'));

        $this->assertCount(0, $charged);
        $this->assertSame('3000.00', (string) $this->bill()->total_amount);
        $this->assertSame('0.00', $this->lateFeeIncome());
    }

    public function test_grace_days_delay_the_charge(): void
    {
        $building = $this->buildingWith(LateFeeType::Fixed, '200.00', graceDays: 5);

        // Due on the 10th, five grace days, so the 14th is still free.
        $this->assertCount(0, app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-14')));
        $this->assertSame('0.00', $this->lateFeeIncome());

        $this->assertCount(1, app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-16')));
        $this->assertSame('200.00', $this->lateFeeIncome());
    }

    public function test_a_bill_is_not_charged_twice_in_the_same_month(): void
    {
        $building = $this->buildingWith(LateFeeType::Fixed, '200.00');

        app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-15'));

        $entries = JournalEntry::count();

        $this->assertCount(0, app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-20')));
        $this->assertSame('200.00', $this->lateFeeIncome());
        $this->assertSame('3200.00', (string) $this->bill()->total_amount);
        $this->assertSame($entries, JournalEntry::count());
    }

    public function test_a_still_unpaid_bill_is_charged_again_the_next_month(): void
    {
        $building = $this->buildingWith(LateFeeType::Fixed, '200.00');

        app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-15'));
        app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-07-15'));

        $this->assertSame('400.00', $this->lateFeeIncome());
        $this->assertSame('3400.00', (string) $this->bill()->total_amount);
    }

    public function test_a_paid_bill_is_never_charged(): void
    {
        $building = $this->buildingWith(LateFeeType::Fixed, '200.00');

        app(RecordPayment::class)->handle(
            Flat::sole(),
            '3000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-06-09'),
        );

        $this->assertSame(BillStatus::Paid, $this->bill()->status);

        $this->assertCount(0, app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-20')));
        $this->assertSame('0.00', $this->lateFeeIncome());
    }

    public function test_a_percentage_fee_follows_the_outstanding_balance_not_the_original_total(): void
    {
        $building = $this->buildingWith(LateFeeType::Percent, '10.00');

        // Half paid, so the fee is 10% of the remaining 1500.00.
        app(RecordPayment::class)->handle(
            Flat::sole(),
            '1500.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-06-09'),
        );

        app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-15'));

        $this->assertSame('150.00', $this->lateFeeIncome());
    }

    public function test_a_building_with_no_late_fee_policy_charges_nothing(): void
    {
        $building = $this->buildingWith(LateFeeType::None, '200.00');

        $this->assertCount(0, app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-20')));
        $this->assertSame('0.00', $this->lateFeeIncome());
    }

    public function test_a_zero_amount_policy_charges_nothing(): void
    {
        $building = $this->buildingWith(LateFeeType::Percent, '0.00');

        $this->assertCount(0, app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-20')));
        $this->assertSame('0.00', $this->lateFeeIncome());
    }

    public function test_the_fee_leaves_the_receivable_and_the_bill_in_agreement(): void
    {
        $building = $this->buildingWith(LateFeeType::Fixed, '250.00');

        app(ApplyLateFees::class)->handle($building, Carbon::parse('2026-06-15'));

        $bill = $this->bill();
        $receivable = $this->journal->balanceFor(
            $this->journal->account(AccountCode::ServiceChargeReceivable),
            $bill->flat_id,
        );

        // The ledger and the bill must never disagree about what is owed.
        $this->assertSame($bill->outstandingAmount(), $receivable);
        $this->assertSame('3250.00', $receivable);
    }
}
