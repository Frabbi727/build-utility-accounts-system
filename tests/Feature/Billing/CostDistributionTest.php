<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Enums\DistributionStatus;
use App\Exceptions\InvalidDistributionException;
use App\Models\Building;
use App\Models\CostDistribution;
use App\Models\Flat;
use App\Models\JournalEntry;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\ServiceChargeBill;
use App\Models\Utility;
use App\Models\UtilityTariff;
use App\Services\Billing\ApproveDistribution;
use App\Services\Billing\ConfirmReading;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\Concerns\AssertsLedger;
use Tests\TestCase;

class CostDistributionTest extends TestCase
{
    use AssertsLedger, RefreshDatabase;

    private JournalService $journal;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);
        $this->building = Building::factory()->create();
    }

    private function distribution(string $total = '10000.00', array $attributes = []): CostDistribution
    {
        return CostDistribution::factory()->create([
            'building_id' => $this->building->id,
            'recovery_account_id' => $this->journal->account(AccountCode::OtherIncome)->id,
            'total_amount' => $total,
            'billing_month' => Carbon::parse('2026-08-01'),
            ...$attributes,
        ]);
    }

    /**
     * @param  list<string>  $sizes
     * @return Collection<int, Flat>
     */
    private function flats(array $sizes): Collection
    {
        return collect($sizes)
            ->map(fn (string $sqft): Flat => Flat::factory()->for($this->building)->create(['size_sqft' => $sqft]))
            ->sortBy('id')
            ->values();
    }

    private function approve(CostDistribution $distribution): CostDistribution
    {
        return app(ApproveDistribution::class)->handle($distribution);
    }

    private function recalculate(CostDistribution $distribution): CostDistribution
    {
        return app(ApproveDistribution::class)->recalculate($distribution);
    }

    /** @return Collection<int, ServiceChargeBill> */
    private function generate(string $month = '2026-08-01'): Collection
    {
        return app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse($month));
    }

    public function test_an_equal_split_sums_to_the_total_exactly(): void
    {
        // 10000 / 7 truncates to 1428.57 each, which is 9999.99 -- the stray paisa goes
        // to the lowest-id unit so the lines add back up to the total exactly.
        $flats = $this->flats(array_fill(0, 7, '1000.00'));
        $distribution = $this->recalculate($this->distribution('10000.00'));

        $this->assertSame('10000.00', $distribution->allocatedAmount());

        $lines = $distribution->lines->keyBy('flat_id');
        $this->assertSame('1428.58', (string) $lines[$flats[0]->id]->amount);
        $this->assertSame('1428.57', (string) $lines[$flats[6]->id]->amount);
    }

    public function test_a_floor_area_split_sums_to_the_total_exactly(): void
    {
        $flats = $this->flats(['1000.00', '750.00', '1250.33']);
        $distribution = $this->recalculate($this->distribution('50000.00', ['basis' => 'per_sqft']));

        $this->assertSame('50000.00', $distribution->allocatedAmount());

        // The largest unit pays the most.
        $lines = $distribution->lines->keyBy('flat_id');
        $this->assertSame(1, bccomp(
            (string) $lines[$flats[2]->id]->amount,
            (string) $lines[$flats[1]->id]->amount,
            2,
        ));
    }

    public function test_a_split_across_units_that_all_weigh_nothing_is_refused(): void
    {
        // size_sqft defaults to 0, so a per-sqft split has no denominator.
        $this->flats(['0.00', '0.00']);

        $this->expectException(InvalidDistributionException::class);

        $this->recalculate($this->distribution('5000.00', ['basis' => 'per_sqft']));
    }

    public function test_a_split_with_no_active_units_is_refused(): void
    {
        $this->expectException(InvalidDistributionException::class);

        $this->recalculate($this->distribution());
    }

    public function test_approving_posts_nothing_to_the_ledger(): void
    {
        $this->flats(['1000.00', '1000.00']);
        $distribution = $this->approve($this->distribution('10000.00'));

        // Accruing here as well as at billing would double-count the income, and because
        // both entries would balance, no balance check would ever notice.
        $this->assertSame(DistributionStatus::Approved, $distribution->status);
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(0, ServiceChargeBill::count());
    }

    public function test_approval_freezes_the_lines_against_later_changes(): void
    {
        $flats = $this->flats(['1000.00', '1000.00']);
        $distribution = $this->approve($this->distribution('10000.00', ['basis' => 'per_sqft']));

        $before = $distribution->lines()->orderBy('flat_id')->pluck('amount')->all();

        // Resizing a unit and adding another must not move money already agreed.
        $flats[0]->update(['size_sqft' => '9000.00']);
        Flat::factory()->for($this->building)->create(['size_sqft' => '2000.00']);

        $this->assertSame($before, $distribution->refresh()->lines()->orderBy('flat_id')->pluck('amount')->all());
        $this->assertSame('10000.00', $distribution->allocatedAmount());
    }

    public function test_an_approved_distribution_cannot_be_recalculated(): void
    {
        $this->flats(['1000.00', '1000.00']);
        $distribution = $this->approve($this->distribution());

        $this->expectException(InvalidDistributionException::class);

        $this->recalculate($distribution);
    }

    public function test_an_edited_line_is_kept_as_long_as_the_total_still_matches(): void
    {
        $flats = $this->flats(['1000.00', '1000.00']);
        $distribution = $this->recalculate($this->distribution('10000.00'));

        // The operator overrides the split: one unit caused the damage.
        $lines = $distribution->lines->keyBy('flat_id');
        $lines[$flats[0]->id]->update(['amount' => '8000.00']);
        $lines[$flats[1]->id]->update(['amount' => '2000.00']);

        $approved = $this->approve($distribution->refresh());

        $this->assertSame(DistributionStatus::Approved, $approved->status);
        $this->assertSame('8000.00', (string) $approved->lines()->where('flat_id', $flats[0]->id)->firstOrFail()->amount);
    }

    public function test_lines_that_do_not_add_up_to_the_total_are_refused(): void
    {
        $flats = $this->flats(['1000.00', '1000.00']);
        $distribution = $this->recalculate($this->distribution('10000.00'));
        $distribution->lines()->where('flat_id', $flats[0]->id)->update(['amount' => '1.00']);

        $this->expectException(InvalidDistributionException::class);

        $this->approve($distribution->refresh());
    }

    public function test_an_approved_line_lands_on_the_bill_and_is_charged_once(): void
    {
        $flats = $this->flats(['1000.00', '1000.00']);
        $distribution = $this->approve($this->distribution('10000.00'));

        $bills = $this->generate()->keyBy('flat_id');

        $this->assertSame('5000.00', (string) $bills[$flats[0]->id]->total_amount);
        $this->assertNotNull($distribution->lines()->where('flat_id', $flats[0]->id)->firstOrFail()->applied_to_bill_id);
        $this->assertLedgerIsBalanced();

        // Re-running the month must not charge it again.
        $this->assertCount(0, $this->generate());
        $this->assertSame(2, ServiceChargeBill::count());
    }

    public function test_the_credit_lands_on_the_recovery_account_not_the_source_expense(): void
    {
        $this->flats(['1000.00', '1000.00']);
        $this->approve($this->distribution('10000.00'));

        $this->generate();

        // Crediting the expense account would understate expenses in every report, and
        // JournalService would accept it without complaint.
        $this->assertSame('10000.00', $this->journal->balanceFor($this->journal->account(AccountCode::OtherIncome)));
        $this->assertSame('0.00', $this->journal->balanceFor($this->journal->account(AccountCode::GeneratorFuel)));
        $this->assertEveryEntryIsBalanced();
    }

    public function test_a_draft_distribution_is_not_billed(): void
    {
        $this->flats(['1000.00', '1000.00']);
        $this->recalculate($this->distribution('10000.00'));

        $this->assertCount(0, $this->generate());
        $this->assertSame(0, ServiceChargeBill::count());
    }

    public function test_a_unit_that_joined_after_approval_gets_no_line(): void
    {
        $this->flats(['1000.00', '1000.00']);
        $distribution = $this->approve($this->distribution('10000.00'));

        $latecomer = Flat::factory()->for($this->building)->create();

        $bills = $this->generate()->keyBy('flat_id');

        $this->assertCount(2, $distribution->refresh()->lines);
        $this->assertArrayNotHasKey($latecomer->id, $bills->all());
    }

    public function test_a_distribution_approved_after_its_month_rides_the_next_bill(): void
    {
        $flats = $this->flats(['1000.00', '1000.00']);
        $distribution = $this->distribution('10000.00');

        // August is billed while the distribution is still a draft.
        $this->assertCount(0, $this->generate('2026-08-01'));

        $this->approve($distribution);
        $bills = $this->generate('2026-09-01')->keyBy('flat_id');

        $this->assertSame('5000.00', (string) $bills[$flats[0]->id]->total_amount);
        // The line names August, not September, or the owner cannot check it.
        $this->assertStringContainsString('August 2026', $bills[$flats[0]->id]->items->firstOrFail()->description);
    }

    public function test_a_billed_distribution_cannot_be_sent_back_to_draft(): void
    {
        $this->flats(['1000.00', '1000.00']);
        $distribution = $this->approve($this->distribution('10000.00'));
        $this->generate();

        $this->expectException(InvalidDistributionException::class);

        app(ApproveDistribution::class)->revert($distribution->refresh());
    }

    public function test_a_consumption_split_weights_by_what_each_unit_used(): void
    {
        $flats = $this->flats(['1000.00', '1000.00']);
        $utility = $this->meteredUtility($flats, ['300.000', '100.000']);

        $distribution = $this->recalculate(
            $this->distribution('8000.00', ['basis' => 'by_consumption', 'utility_id' => $utility->id])
        );

        $lines = $distribution->lines->keyBy('flat_id');

        // 300 : 100, so 6000 : 2000.
        $this->assertSame('6000.00', (string) $lines[$flats[0]->id]->amount);
        $this->assertSame('2000.00', (string) $lines[$flats[1]->id]->amount);
        $this->assertSame('8000.00', $distribution->allocatedAmount());
    }

    public function test_a_consumption_split_refuses_while_a_reading_is_unconfirmed(): void
    {
        $flats = $this->flats(['1000.00', '1000.00']);
        $utility = $this->meteredUtility($flats, ['300.000', '100.000'], confirmAll: false);

        // A partial denominator overcharges every unit proportionally and looks fine.
        $this->expectException(InvalidDistributionException::class);

        $this->recalculate(
            $this->distribution('8000.00', ['basis' => 'by_consumption', 'utility_id' => $utility->id])
        );
    }

    public function test_a_consumption_split_with_no_consumption_at_all_is_refused(): void
    {
        $flats = $this->flats(['1000.00', '1000.00']);
        $utility = $this->meteredUtility($flats, ['0.000', '0.000']);

        $this->expectException(InvalidDistributionException::class);

        $this->recalculate(
            $this->distribution('8000.00', ['basis' => 'by_consumption', 'utility_id' => $utility->id])
        );
    }

    /**
     * @param  Collection<int, Flat>  $flats
     * @param  list<string>  $consumptions
     */
    private function meteredUtility(Collection $flats, array $consumptions, bool $confirmAll = true): Utility
    {
        $utility = Utility::factory()->electricity()->create([
            'building_id' => $this->building->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
        ]);
        UtilityTariff::factory()->flatRate('8.5000')->create([
            'utility_id' => $utility->id,
            'effective_from' => Carbon::parse('2026-01-01'),
        ]);

        foreach ($flats as $index => $flat) {
            $meter = Meter::factory()->create([
                'building_id' => $this->building->id,
                'utility_id' => $utility->id,
                'flat_id' => $flat->id,
            ]);

            $reading = MeterReading::factory()->create([
                'meter_id' => $meter->id,
                'billing_month' => Carbon::parse('2026-08-01'),
                'reading_date' => Carbon::parse('2026-08-28'),
                'previous_reading' => '0.000',
                'current_reading' => $consumptions[$index],
                'consumption' => $consumptions[$index],
            ]);

            if ($confirmAll || $index === 0) {
                app(ConfirmReading::class)->handle($reading);
            }
        }

        return $utility;
    }
}
