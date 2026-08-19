<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Models\Building;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\FlatChargeOverride;
use App\Models\JournalEntry;
use App\Models\ServiceChargeBill;
use App\Services\Billing\ChargeCalculator;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ChargeHeadBillingTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);
    }

    private function chargeHead(Building $building, string $name, string $basis, string $amount, ?AccountCode $code = null): ChargeHead
    {
        return ChargeHead::factory()->create([
            'building_id' => $building->id,
            'account_id' => $this->journal->account($code ?? AccountCode::ServiceChargeIncome)->id,
            'name' => $name,
            'basis' => $basis,
            'amount' => $amount,
        ]);
    }

    /** @return Collection<int, ServiceChargeBill> */
    private function generate(Building $building): Collection
    {
        return app(GenerateMonthlyBills::class)->handle($building, Carbon::parse('2026-08-01'));
    }

    public function test_a_bill_carries_one_item_per_active_charge_head(): void
    {
        $building = Building::factory()->create();
        $this->chargeHead($building, 'Guard Salary', 'per_flat', '1200.00');
        $this->chargeHead($building, 'Lift', 'per_flat', '500.00');
        $this->chargeHead($building, 'Water', 'per_sqft', '1.50');
        Flat::factory()->for($building)->create(['size_sqft' => '1000.00']);

        $bill = $this->generate($building)->firstOrFail();

        $this->assertCount(3, $bill->items);
        // 1200 + 500 + (1.50 x 1000)
        $this->assertSame('3200.00', (string) $bill->total_amount);
        $this->assertEqualsCanonicalizing(
            ['1200.00', '500.00', '1500.00'],
            $bill->items->map(fn ($item): string => (string) $item->amount)->all(),
        );
    }

    public function test_an_inactive_charge_head_is_not_billed(): void
    {
        $building = Building::factory()->create();
        $this->chargeHead($building, 'Guard Salary', 'per_flat', '1200.00');
        ChargeHead::factory()->inactive()->create([
            'building_id' => $building->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
            'name' => 'Retired Head',
            'amount' => '999.00',
        ]);
        Flat::factory()->for($building)->create();

        $bill = $this->generate($building)->firstOrFail();

        $this->assertCount(1, $bill->items);
        $this->assertSame('1200.00', (string) $bill->total_amount);
    }

    public function test_an_equal_share_head_splits_evenly_and_the_remainder_lands_on_one_flat(): void
    {
        $building = Building::factory()->create();
        // 8000 / 3 = 2666.666..., so 2666.66 each leaves 0.02 to place.
        $this->chargeHead($building, 'Cleaning', 'equal_share', '8000.00');
        $flats = Flat::factory()->count(3)->for($building)->create()->sortBy('id')->values();

        $bills = $this->generate($building)->keyBy('flat_id');

        $this->assertSame('2666.68', (string) $bills[$flats[0]->id]->total_amount);
        $this->assertSame('2666.66', (string) $bills[$flats[1]->id]->total_amount);
        $this->assertSame('2666.66', (string) $bills[$flats[2]->id]->total_amount);

        // The point of placing the remainder: the heads sum to the contract total.
        $sum = $bills->reduce(fn (string $carry, ServiceChargeBill $bill): string => bcadd($carry, (string) $bill->total_amount, 2), '0.00');
        $this->assertSame('8000.00', $sum);
    }

    public function test_an_equal_share_head_ignores_inactive_flats_when_splitting(): void
    {
        $building = Building::factory()->create();
        $this->chargeHead($building, 'Cleaning', 'equal_share', '9000.00');
        Flat::factory()->count(3)->for($building)->create();
        Flat::factory()->for($building)->create(['is_active' => false]);

        $bills = $this->generate($building);

        $this->assertCount(3, $bills);
        $this->assertSame(['3000.00', '3000.00', '3000.00'], $bills->map(fn ($b): string => (string) $b->total_amount)->all());
    }

    public function test_a_per_flat_override_replaces_the_computed_amount(): void
    {
        $building = Building::factory()->create();
        $head = $this->chargeHead($building, 'Guard Salary', 'per_flat', '1200.00');
        $flat = Flat::factory()->for($building)->create();
        FlatChargeOverride::factory()->create([
            'flat_id' => $flat->id,
            'charge_head_id' => $head->id,
            'amount' => '750.00',
        ]);

        $bill = $this->generate($building)->firstOrFail();

        $this->assertSame('750.00', (string) $bill->total_amount);
    }

    public function test_an_exempt_flat_is_not_billed_for_that_head(): void
    {
        $building = Building::factory()->create();
        $exemptHead = $this->chargeHead($building, 'Lift', 'per_flat', '500.00');
        $this->chargeHead($building, 'Guard Salary', 'per_flat', '1200.00');
        $flat = Flat::factory()->for($building)->create();
        FlatChargeOverride::factory()->exempt()->create([
            'flat_id' => $flat->id,
            'charge_head_id' => $exemptHead->id,
        ]);

        $bill = $this->generate($building)->firstOrFail();

        $this->assertCount(1, $bill->items);
        $this->assertSame('1200.00', (string) $bill->total_amount);
    }

    public function test_a_flat_exempt_from_every_head_is_not_billed_at_all(): void
    {
        $building = Building::factory()->create();
        $head = $this->chargeHead($building, 'Guard Salary', 'per_flat', '1200.00');
        $flat = Flat::factory()->for($building)->create();
        FlatChargeOverride::factory()->exempt()->create([
            'flat_id' => $flat->id,
            'charge_head_id' => $head->id,
        ]);

        $this->assertCount(0, $this->generate($building));
        $this->assertSame(0, ServiceChargeBill::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_the_accrual_debits_receivable_once_and_credits_each_head_account(): void
    {
        $building = Building::factory()->create();
        $this->chargeHead($building, 'Guard Salary', 'per_flat', '1200.00', AccountCode::ServiceChargeIncome);
        $this->chargeHead($building, 'Late Recovery', 'per_flat', '300.00', AccountCode::OtherIncome);
        $flat = Flat::factory()->for($building)->create();

        $bill = $this->generate($building)->firstOrFail();
        $entry = JournalEntry::with('lines')->firstOrFail();

        $this->assertCount(3, $entry->lines);

        $debits = $entry->lines->where('debit', '>', 0);
        $this->assertCount(1, $debits);
        $this->assertSame('1500.00', (string) $debits->first()->debit);
        $this->assertSame($flat->id, $debits->first()->flat_id);
        $this->assertSame($this->journal->account(AccountCode::ServiceChargeReceivable)->id, $debits->first()->account_id);

        $this->assertSame('1200.00', $this->journal->balanceFor($this->journal->account(AccountCode::ServiceChargeIncome)));
        $this->assertSame('300.00', $this->journal->balanceFor($this->journal->account(AccountCode::OtherIncome)));
        $this->assertSame('1500.00', (string) $bill->total_amount);
    }

    public function test_every_generated_entry_is_balanced(): void
    {
        $building = Building::factory()->create();
        $this->chargeHead($building, 'Guard Salary', 'per_flat', '1200.00');
        $this->chargeHead($building, 'Water', 'per_sqft', '1.50');
        $this->chargeHead($building, 'Cleaning', 'equal_share', '7000.00');
        Flat::factory()->count(3)->for($building)->create();

        $this->generate($building);

        foreach (JournalEntry::with('lines')->get() as $entry) {
            $this->assertSame(
                bcadd((string) $entry->lines->sum('debit'), '0', 2),
                bcadd((string) $entry->lines->sum('credit'), '0', 2),
                "Entry {$entry->ref_no} is unbalanced.",
            );
        }
    }

    public function test_regenerating_the_month_is_still_idempotent(): void
    {
        $building = Building::factory()->create();
        $this->chargeHead($building, 'Guard Salary', 'per_flat', '1200.00');
        $this->chargeHead($building, 'Water', 'per_sqft', '1.50');
        Flat::factory()->count(2)->for($building)->create();

        $this->generate($building);
        $second = $this->generate($building);

        $this->assertCount(0, $second);
        $this->assertSame(2, ServiceChargeBill::count());
        $this->assertSame(2, JournalEntry::count());
    }

    public function test_the_calculator_previews_what_a_flat_will_be_billed(): void
    {
        $building = Building::factory()->create();
        $this->chargeHead($building, 'Guard Salary', 'per_flat', '1200.00');
        $this->chargeHead($building, 'Water', 'per_sqft', '2.00');
        $flat = Flat::factory()->for($building)->create(['size_sqft' => '900.00']);

        $this->assertSame('3000.00', app(ChargeCalculator::class)->totalFor($flat->fresh()));
    }
}
