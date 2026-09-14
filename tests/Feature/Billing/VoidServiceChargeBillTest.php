<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Enums\PeriodStatus;
use App\Exceptions\CannotVoidBillException;
use App\Models\AccountingPeriod;
use App\Models\AdHocCharge;
use App\Models\Building;
use App\Models\CostDistribution;
use App\Models\CostDistributionLine;
use App\Models\Flat;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Utility;
use App\Models\UtilityTariff;
use App\Services\Billing\ConfirmReading;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\RecordPayment;
use App\Services\Billing\VoidServiceChargeBill;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AssertsLedger;
use Tests\TestCase;

class VoidServiceChargeBillTest extends TestCase
{
    use AssertsLedger, RefreshDatabase;

    private JournalService $journal;

    private Building $building;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);

        $this->building = Building::factory()->flatRate('2500.00')->create();
        $this->flat = Flat::factory()->for($this->building)->create();
    }

    public function test_voiding_an_unpaid_bill_posts_contra_entry_sets_status_to_voided_and_restores_receivable_to_zero(): void
    {
        $bill = app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'))->firstOrFail();

        $receivableAccount = $this->journal->account(AccountCode::ServiceChargeReceivable);
        $this->assertSame('2500.00', $this->journal->balanceFor($receivableAccount, $this->flat->id));
        $this->assertSame(BillStatus::Unpaid, $bill->status);

        $voidDate = Carbon::parse('2026-08-15');
        $voidedBill = app(VoidServiceChargeBill::class)->handle($bill, 'Billed in error', $voidDate);

        $this->assertSame(BillStatus::Voided, $voidedBill->status);
        $this->assertSame(BillStatus::Voided, $bill->fresh()->status);
        $this->assertSame('0.00', $this->journal->balanceFor($receivableAccount, $this->flat->id));
        $this->assertLedgerIsBalanced();
        $this->assertEveryEntryIsBalanced();

        $originalEntry = $bill->journalEntries()->firstOrFail();
        $this->assertNotNull($originalEntry->reversed_by_id);
    }

    public function test_voiding_a_bill_releases_meter_readings_ad_hoc_charges_and_distributions(): void
    {
        $utility = Utility::factory()->electricity()->create([
            'building_id' => $this->building->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
        ]);
        UtilityTariff::factory()->flatRate('10.0000')->create([
            'utility_id' => $utility->id,
            'effective_from' => Carbon::parse('2026-01-01'),
        ]);
        $meter = Meter::factory()->create([
            'building_id' => $this->building->id,
            'utility_id' => $utility->id,
            'flat_id' => $this->flat->id,
        ]);
        $reading = MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'billing_month' => Carbon::parse('2026-08-01'),
            'reading_date' => Carbon::parse('2026-08-28'),
            'previous_reading' => '0.000',
            'current_reading' => '50.000',
            'consumption' => '50.000',
        ]);
        app(ConfirmReading::class)->handle($reading);

        $adHoc = AdHocCharge::factory()->create([
            'flat_id' => $this->flat->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
            'effective_month' => Carbon::parse('2026-08-01'),
            'amount' => '300.00',
        ]);

        $distribution = CostDistribution::factory()->approved()->create([
            'building_id' => $this->building->id,
            'billing_month' => Carbon::parse('2026-08-01'),
        ]);
        $distLine = CostDistributionLine::factory()->create([
            'cost_distribution_id' => $distribution->id,
            'flat_id' => $this->flat->id,
            'amount' => '200.00',
        ]);

        $bill = app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'))->firstOrFail();

        $this->assertSame($bill->id, $reading->fresh()->applied_to_bill_id);
        $this->assertSame($bill->id, $adHoc->fresh()->applied_to_bill_id);
        $this->assertSame($bill->id, $distLine->fresh()->applied_to_bill_id);

        app(VoidServiceChargeBill::class)->handle($bill, 'Correction needed');

        $this->assertNull($reading->fresh()->applied_to_bill_id);
        $this->assertNull($adHoc->fresh()->applied_to_bill_id);
        $this->assertNull($distLine->fresh()->applied_to_bill_id);
    }

    public function test_voiding_a_bill_with_payment_allocations_throws_exception(): void
    {
        $bill = app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'))->firstOrFail();

        app(RecordPayment::class)->handle(
            $this->flat,
            '1000.00',
            PaymentMethod::Cash,
            Carbon::parse('2026-08-10'),
        );

        $this->assertSame(BillStatus::PartiallyPaid, $bill->fresh()->status);
        $this->assertTrue($bill->fresh()->allocations()->exists());

        $this->expectException(CannotVoidBillException::class);
        $this->expectExceptionMessage("Cannot void bill {$bill->bill_no} because it has received payments.");

        app(VoidServiceChargeBill::class)->handle($bill->fresh(), 'Cancel bill');
    }

    public function test_voiding_an_already_voided_bill_throws_exception(): void
    {
        $bill = app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'))->firstOrFail();

        app(VoidServiceChargeBill::class)->handle($bill, 'First void');

        $this->expectException(CannotVoidBillException::class);
        $this->expectExceptionMessage("Cannot void bill {$bill->bill_no} because it is already voided.");

        app(VoidServiceChargeBill::class)->handle($bill->fresh(), 'Second void');
    }

    public function test_voiding_in_a_locked_period_throws_exception(): void
    {
        $bill = app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'))->firstOrFail();

        $period = AccountingPeriod::firstOrCreate(['year' => 2026, 'month' => 8]);
        $period->update(['status' => PeriodStatus::Locked, 'locked_at' => now()]);

        $this->expectException(CannotVoidBillException::class);

        app(VoidServiceChargeBill::class)->handle($bill, 'Void in locked period', Carbon::parse('2026-08-15'));
    }
}
