<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Models\AdHocCharge;
use App\Models\Building;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\FlatChargeOverride;
use App\Models\JournalEntry;
use App\Models\ServiceChargeBill;
use App\Services\Billing\ApplyAdvances;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\Billing\LineSources\BillLineSource;
use App\Services\Billing\LineSources\ChargeHeadLines;
use App\Services\JournalService;
use App\Support\BillLineData;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\Concerns\AssertsLedger;
use Tests\TestCase;

/**
 * The two invariants GenerateMonthlyBills owns on behalf of every line source.
 *
 * Both are easy to get wrong in a way no existing test would catch, which is why they
 * are pinned here rather than left to the per-source tests.
 */
class BillLineSourceTest extends TestCase
{
    use AssertsLedger, RefreshDatabase;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);
    }

    /**
     * A source that always contributes one line of the given amount, standing in for
     * meter readings and distributed costs before those exist.
     *
     * @return Collection<int, ServiceChargeBill>
     */
    private function generateWithExtraSource(Building $building, string $amount): Collection
    {
        $accountId = $this->journal->account(AccountCode::OtherIncome)->id;

        $extra = new class($accountId, $amount) implements BillLineSource
        {
            public function __construct(private readonly int $accountId, private readonly string $amount) {}

            public function linesFor(Flat $flat, Carbon $billingMonth): array
            {
                return [BillLineData::make($this->accountId, 'Standalone source', $this->amount)];
            }

            public function markBilled(array $lines, ServiceChargeBill $bill): void {}
        };

        $generator = new GenerateMonthlyBills(
            $this->journal,
            app(ApplyAdvances::class),
            [app(ChargeHeadLines::class), $extra],
        );

        return $generator->handle($building, Carbon::parse('2026-08-01'));
    }

    public function test_a_flat_with_no_charge_head_lines_but_another_source_is_still_billed(): void
    {
        $building = Building::factory()->create();
        $head = ChargeHead::factory()->create([
            'building_id' => $building->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
            'name' => 'Guard Salary',
            'basis' => 'per_flat',
            'amount' => '1200.00',
        ]);
        $flat = Flat::factory()->for($building)->create();

        // Exempt from every charge head. Before the line-source refactor this flat was
        // skipped outright, so a meter reading against it would never have been billed.
        FlatChargeOverride::factory()->exempt()->create([
            'flat_id' => $flat->id,
            'charge_head_id' => $head->id,
        ]);

        $bill = $this->generateWithExtraSource($building, '450.00')->firstOrFail();

        $this->assertCount(1, $bill->items);
        $this->assertSame('450.00', (string) $bill->total_amount);
        $this->assertSame('Standalone source', $bill->items->first()->description);
        $this->assertLedgerIsBalanced();
    }

    public function test_a_zero_amount_line_is_dropped_before_the_journal(): void
    {
        $building = Building::factory()->create();
        ChargeHead::factory()->create([
            'building_id' => $building->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
            'name' => 'Guard Salary',
            'basis' => 'per_flat',
            'amount' => '1200.00',
        ]);
        Flat::factory()->for($building)->create();

        // JournalService rejects a line that is neither a debit nor a credit, so a zero
        // reaching it would throw rather than merely print an odd line.
        $bill = $this->generateWithExtraSource($building, '0.00')->firstOrFail();

        $this->assertCount(1, $bill->items);
        $this->assertSame('1200.00', (string) $bill->total_amount);
        $this->assertSame(2, JournalEntry::firstOrFail()->lines->count());
        $this->assertLedgerIsBalanced();
    }

    public function test_a_flat_charged_nothing_by_any_source_is_not_billed(): void
    {
        $building = Building::factory()->create();
        Flat::factory()->for($building)->create();

        $this->assertCount(0, $this->generateWithExtraSource($building, '0.00'));
        $this->assertSame(0, ServiceChargeBill::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_lines_appear_in_the_order_their_sources_are_registered(): void
    {
        $building = Building::factory()->create();
        ChargeHead::factory()->create([
            'building_id' => $building->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
            'name' => 'Guard Salary',
            'basis' => 'per_flat',
            'amount' => '1200.00',
        ]);
        $flat = Flat::factory()->for($building)->create();
        AdHocCharge::factory()->create([
            'flat_id' => $flat->id,
            'account_id' => $this->journal->account(AccountCode::OtherIncome)->id,
            'description' => 'Balcony repair',
            'amount' => '300.00',
            'effective_month' => Carbon::parse('2026-08-01'),
        ]);

        $bill = app(GenerateMonthlyBills::class)
            ->handle($building, Carbon::parse('2026-08-01'))
            ->firstOrFail();

        $this->assertSame(
            ['Guard Salary', 'Balcony repair'],
            $bill->items()->orderBy('id')->pluck('description')->all(),
        );
    }

    public function test_a_billed_line_records_what_produced_it(): void
    {
        $building = Building::factory()->create();
        $head = ChargeHead::factory()->create([
            'building_id' => $building->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
            'name' => 'Guard Salary',
            'basis' => 'per_flat',
            'amount' => '1200.00',
        ]);
        Flat::factory()->for($building)->create();

        $item = app(GenerateMonthlyBills::class)
            ->handle($building, Carbon::parse('2026-08-01'))
            ->firstOrFail()
            ->items
            ->firstOrFail();

        $this->assertTrue($item->source->is($head));
        $this->assertNull($item->quantity);
        $this->assertFalse($item->isMetered());
    }
}
