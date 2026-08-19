<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Enums\ReadingStatus;
use App\Exceptions\ReadingNotConfirmableException;
use App\Models\Building;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\JournalEntry;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\ServiceChargeBill;
use App\Models\Utility;
use App\Models\UtilityTariff;
use App\Services\Billing\ConfirmReading;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\Concerns\AssertsLedger;
use Tests\TestCase;

class MeteredBillingTest extends TestCase
{
    use AssertsLedger, RefreshDatabase;

    private JournalService $journal;

    private Building $building;

    private Utility $electricity;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);

        $this->building = Building::factory()->create();
        $this->flat = Flat::factory()->for($this->building)->create();
        $this->electricity = Utility::factory()->electricity()->create([
            'building_id' => $this->building->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
        ]);
    }

    private function tariff(string $rate = '8.5000', string $from = '2026-01-01', string $fixed = '0.00'): UtilityTariff
    {
        return UtilityTariff::factory()
            ->flatRate($rate, $fixed)
            ->create(['utility_id' => $this->electricity->id, 'effective_from' => Carbon::parse($from)]);
    }

    private function meter(?Flat $flat = null): Meter
    {
        return Meter::factory()->create([
            'building_id' => $this->building->id,
            'utility_id' => $this->electricity->id,
            'flat_id' => $flat === null ? $this->flat->id : $flat->id,
        ]);
    }

    private function reading(Meter $meter, string $units, string $month = '2026-08-01', string $date = '2026-08-28'): MeterReading
    {
        return MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'billing_month' => Carbon::parse($month),
            'reading_date' => Carbon::parse($date),
            'previous_reading' => '0.000',
            'current_reading' => $units,
            'consumption' => $units,
        ]);
    }

    /** @return Collection<int, ServiceChargeBill> */
    private function generate(string $month = '2026-08-01'): Collection
    {
        return app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse($month));
    }

    private function confirm(MeterReading $reading): MeterReading
    {
        return app(ConfirmReading::class)->handle($reading);
    }

    public function test_a_confirmed_reading_becomes_one_metered_bill_line(): void
    {
        $this->tariff();
        $this->confirm($this->reading($this->meter(), '120.000'));

        $bill = $this->generate()->firstOrFail();
        $item = $bill->items->firstOrFail();

        // 120 x 8.50
        $this->assertSame('1020.00', (string) $bill->total_amount);
        $this->assertSame('1020.00', (string) $item->amount);
        $this->assertSame('120.000', (string) $item->quantity);
        $this->assertSame('8.5000', (string) $item->unit_rate);
        $this->assertSame('kWh', $item->unit_label);
        $this->assertTrue($item->isMetered());
        $this->assertLedgerIsBalanced();
    }

    public function test_the_accrual_credits_the_utilitys_income_account(): void
    {
        $this->tariff();
        $this->confirm($this->reading($this->meter(), '120.000'));

        $bill = $this->generate()->firstOrFail();
        $entry = JournalEntry::with('lines')->firstOrFail();

        $debit = $entry->lines->firstWhere(fn ($line): bool => bccomp((string) $line->debit, '0', 2) > 0);
        $credit = $entry->lines->firstWhere(fn ($line): bool => bccomp((string) $line->credit, '0', 2) > 0);

        $this->assertSame('1020.00', (string) $debit->debit);
        $this->assertSame($this->flat->id, $debit->flat_id);
        $this->assertSame((string) $bill->total_amount, (string) $debit->debit);
        $this->assertSame($this->electricity->account_id, $credit->account_id);
        $this->assertEveryEntryIsBalanced();
    }

    public function test_a_draft_reading_is_not_billed(): void
    {
        $this->tariff();
        $reading = $this->reading($this->meter(), '120.000');

        $this->assertSame(ReadingStatus::Draft, $reading->status);
        $this->assertCount(0, $this->generate());
        $this->assertSame(0, ServiceChargeBill::count());
    }

    public function test_a_reading_is_billed_at_most_once(): void
    {
        $this->tariff();
        $reading = $this->confirm($this->reading($this->meter(), '120.000'));

        $bill = $this->generate()->firstOrFail();

        $this->assertSame($bill->id, $reading->refresh()->applied_to_bill_id);
        $this->assertCount(0, $this->generate());
        $this->assertSame(1, ServiceChargeBill::count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_a_reading_confirmed_after_its_month_rides_the_next_bill(): void
    {
        $this->tariff();
        ChargeHead::factory()->create([
            'building_id' => $this->building->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
            'name' => 'Guard Salary',
            'basis' => 'per_flat',
            'amount' => '1000.00',
        ]);

        // August is billed with the charge head only; the July reading is still a draft.
        $july = $this->reading($this->meter(), '100.000', '2026-07-01', '2026-07-28');
        $august = $this->generate('2026-08-01')->firstOrFail();
        $this->assertSame('1000.00', (string) $august->total_amount);

        // Confirmed late, it cannot join August's bill -- so it must join September's.
        $this->confirm($july);
        $september = $this->generate('2026-09-01')->firstOrFail();

        // 1000 charge head + (100 x 8.50)
        $this->assertSame('1850.00', (string) $september->total_amount);
        $this->assertSame($september->id, $july->refresh()->applied_to_bill_id);

        // The line names July, not September, or the owner cannot check it.
        $metered = $september->items->firstWhere(fn ($item): bool => $item->isMetered());
        $this->assertStringContainsString('July 2026', $metered->description);
    }

    public function test_a_zero_consumption_reading_produces_no_line_and_no_error(): void
    {
        $this->tariff();
        ChargeHead::factory()->create([
            'building_id' => $this->building->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
            'name' => 'Guard Salary',
            'basis' => 'per_flat',
            'amount' => '1000.00',
        ]);
        $this->confirm($this->reading($this->meter(), '0.000'));

        // A zero line would be neither a debit nor a credit, which JournalService rejects.
        $bill = $this->generate()->firstOrFail();

        $this->assertCount(1, $bill->items);
        $this->assertSame('1000.00', (string) $bill->total_amount);
        $this->assertLedgerIsBalanced();
    }

    public function test_a_zero_consumption_reading_still_bills_the_fixed_charge(): void
    {
        $this->tariff('8.5000', '2026-01-01', '125.00');
        $this->confirm($this->reading($this->meter(), '0.000'));

        $bill = $this->generate()->firstOrFail();

        $this->assertSame('125.00', (string) $bill->total_amount);
    }

    public function test_the_stamped_tariff_wins_over_a_newer_one_effective_at_bill_time(): void
    {
        $this->tariff('8.5000', '2026-01-01');
        $reading = $this->confirm($this->reading($this->meter(), '100.000', '2026-07-01', '2026-07-28'));

        // The rate goes up before the bill is ever generated.
        $this->tariff('12.0000', '2026-08-01');

        $bill = $this->generate('2026-09-01')->firstOrFail();

        // Priced at the July rate it was confirmed against, not September's.
        $this->assertSame('850.00', (string) $bill->total_amount);
        $this->assertSame('8.5000', (string) $bill->items->firstOrFail()->unit_rate);
        $this->assertSame('8.5000', (string) $reading->refresh()->tariff->slabs->firstOrFail()->rate_per_unit);
    }

    public function test_a_common_meter_is_billed_to_nobody(): void
    {
        $this->tariff();
        $common = Meter::factory()->common()->create([
            'building_id' => $this->building->id,
            'utility_id' => $this->electricity->id,
        ]);
        $this->confirm($this->reading($common, '5000.000'));

        $this->assertCount(0, $this->generate());
        $this->assertSame(0, ServiceChargeBill::count());
    }

    public function test_confirming_stamps_the_tariff_in_force_on_the_reading_date(): void
    {
        $old = $this->tariff('8.5000', '2026-01-01');
        $new = $this->tariff('12.0000', '2026-08-01');

        $july = $this->confirm($this->reading($this->meter(), '100.000', '2026-07-01', '2026-07-28'));
        $meterTwo = Meter::factory()->create([
            'building_id' => $this->building->id,
            'utility_id' => $this->electricity->id,
            'flat_id' => Flat::factory()->for($this->building)->create()->id,
        ]);
        $august = $this->confirm($this->reading($meterTwo, '100.000', '2026-08-01', '2026-08-28'));

        $this->assertSame($old->id, $july->utility_tariff_id);
        $this->assertSame($new->id, $august->utility_tariff_id);
    }

    public function test_confirming_without_a_tariff_is_refused(): void
    {
        $reading = $this->reading($this->meter(), '120.000');

        $this->expectException(ReadingNotConfirmableException::class);

        $this->confirm($reading);
    }

    public function test_a_billed_reading_cannot_be_sent_back_to_draft(): void
    {
        $this->tariff();
        $reading = $this->confirm($this->reading($this->meter(), '120.000'));
        $this->generate();

        $this->expectException(ReadingNotConfirmableException::class);

        app(ConfirmReading::class)->revert($reading->refresh());
    }

    public function test_an_unbilled_reading_can_be_corrected_before_it_reaches_a_bill(): void
    {
        $this->tariff();
        $reading = $this->confirm($this->reading($this->meter(), '120.000'));

        $reverted = app(ConfirmReading::class)->revert($reading);

        $this->assertSame(ReadingStatus::Draft, $reverted->status);
        $this->assertNull($reverted->utility_tariff_id);
        $this->assertCount(0, $this->generate());
    }

    public function test_a_slab_tariff_charges_each_band_at_its_own_rate(): void
    {
        UtilityTariff::factory()->slabbed()->create([
            'utility_id' => $this->electricity->id,
            'effective_from' => Carbon::parse('2026-01-01'),
        ]);
        $this->confirm($this->reading($this->meter(), '250.000'));

        $bill = $this->generate()->firstOrFail();

        // 75 x 4.85 + 125 x 6.63 + 50 x 10.07
        $this->assertSame('1696.00', (string) $bill->total_amount);
        $this->assertLedgerIsBalanced();
    }

    public function test_metered_and_recurring_lines_share_one_bill(): void
    {
        $this->tariff();
        ChargeHead::factory()->create([
            'building_id' => $this->building->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
            'name' => 'Guard Salary',
            'basis' => 'per_flat',
            'amount' => '1200.00',
        ]);
        $this->confirm($this->reading($this->meter(), '120.000'));

        $bill = $this->generate()->firstOrFail();

        $this->assertCount(2, $bill->items);
        $this->assertSame('2220.00', (string) $bill->total_amount);
        // Charge heads print before metered lines, per the registered source order.
        $this->assertSame('Guard Salary', $bill->items()->orderBy('id')->first()->description);
        $this->assertLedgerIsBalanced();
    }
}
