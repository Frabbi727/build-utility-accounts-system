<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountCode;
use App\Models\Building;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\ServiceChargeBill;
use App\Models\UnitType;
use App\Services\Billing\ChargeCalculator;
use App\Services\Billing\GenerateMonthlyBills;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\Concerns\AssertsLedger;
use Tests\TestCase;

class ChargeHeadApplicabilityTest extends TestCase
{
    use AssertsLedger, RefreshDatabase;

    private JournalService $journal;

    private Building $building;

    private UnitType $shop;

    private UnitType $residential;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->journal = app(JournalService::class);

        $this->building = Building::factory()->create();
        $this->shop = UnitType::factory()->shop()->create(['building_id' => $this->building->id]);
        $this->residential = UnitType::factory()->residential()->create(['building_id' => $this->building->id]);
    }

    private function chargeHead(string $name, string $basis, string $amount, ?UnitType $onlyFor = null): ChargeHead
    {
        $head = ChargeHead::factory()->create([
            'building_id' => $this->building->id,
            'account_id' => $this->journal->account(AccountCode::ServiceChargeIncome)->id,
            'name' => $name,
            'basis' => $basis,
            'amount' => $amount,
        ]);

        if ($onlyFor !== null) {
            $head->unitTypes()->attach($onlyFor);
        }

        return $head;
    }

    private function flat(?UnitType $type, string $sqft = '1000.00'): Flat
    {
        return Flat::factory()->for($this->building)->create([
            'unit_type_id' => $type?->id,
            'size_sqft' => $sqft,
        ]);
    }

    /** @return Collection<int, ServiceChargeBill> */
    private function generate(): Collection
    {
        return app(GenerateMonthlyBills::class)->handle($this->building, Carbon::parse('2026-08-01'));
    }

    public function test_a_head_with_no_restriction_applies_to_every_unit(): void
    {
        $this->chargeHead('Guard Salary', 'per_flat', '1200.00');
        $this->flat($this->shop);
        $this->flat($this->residential);
        $this->flat(null);

        $bills = $this->generate();

        $this->assertCount(3, $bills);
        $this->assertSame(['1200.00', '1200.00', '1200.00'], $bills->map(fn ($b): string => (string) $b->total_amount)->all());
    }

    public function test_a_restricted_head_is_skipped_for_a_non_matching_unit(): void
    {
        $this->chargeHead('Guard Salary', 'per_flat', '1200.00');
        $this->chargeHead('Shutter Cleaning', 'per_flat', '500.00', $this->shop);

        $shop = $this->flat($this->shop);
        $home = $this->flat($this->residential);

        $bills = $this->generate()->keyBy('flat_id');

        $this->assertSame('1700.00', (string) $bills[$shop->id]->total_amount);
        $this->assertSame('1200.00', (string) $bills[$home->id]->total_amount);
    }

    public function test_a_unit_with_no_type_is_excluded_from_a_restricted_head(): void
    {
        // Deliberate: "no type yet" is not "every type". A head restricted to shops must
        // not reach a unit nobody has classified.
        $this->chargeHead('Shutter Cleaning', 'per_flat', '500.00', $this->shop);
        $untyped = $this->flat(null);

        $this->assertSame('0.00', app(ChargeCalculator::class)->totalFor($untyped));
        $this->assertCount(0, $this->generate());
    }

    public function test_an_equal_share_head_divides_across_only_the_units_it_applies_to(): void
    {
        // 9000 across the two shops, not across all four units.
        $this->chargeHead('Market Security', 'equal_share', '9000.00', $this->shop);
        $shopOne = $this->flat($this->shop);
        $shopTwo = $this->flat($this->shop);
        $this->flat($this->residential);
        $this->flat($this->residential);

        $bills = $this->generate()->keyBy('flat_id');

        $this->assertSame('4500.00', (string) $bills[$shopOne->id]->total_amount);
        $this->assertSame('4500.00', (string) $bills[$shopTwo->id]->total_amount);
        $this->assertCount(2, $bills);
    }

    public function test_a_restricted_equal_share_head_still_sums_to_its_contract_total(): void
    {
        // 8000 / 3 = 2666.666..., so 0.02 has to land somewhere.
        $this->chargeHead('Market Security', 'equal_share', '8000.00', $this->shop);
        $shops = collect([$this->flat($this->shop), $this->flat($this->shop), $this->flat($this->shop)])
            ->sortBy('id')->values();
        $this->flat($this->residential);

        $bills = $this->generate()->keyBy('flat_id');

        $this->assertSame('2666.68', (string) $bills[$shops[0]->id]->total_amount);
        $this->assertSame('2666.66', (string) $bills[$shops[1]->id]->total_amount);
        $this->assertSame('2666.66', (string) $bills[$shops[2]->id]->total_amount);

        $sum = $shops->reduce(
            fn (string $carry, Flat $flat): string => bcadd($carry, (string) $bills[$flat->id]->total_amount, 2),
            '0.00',
        );

        $this->assertSame('8000.00', $sum);
    }

    public function test_two_heads_with_different_applicability_each_get_their_own_divisor_and_absorber(): void
    {
        // The trap this whole phase risks: the per-head context used to be cached per
        // building, so the second head would silently reuse the first head's divisor and
        // the first head's remainder absorber.
        $this->chargeHead('Market Security', 'equal_share', '8000.00', $this->shop);
        $this->chargeHead('Lift', 'equal_share', '5000.00', $this->residential);

        $shops = collect([$this->flat($this->shop), $this->flat($this->shop), $this->flat($this->shop)])
            ->sortBy('id')->values();
        $homes = collect([$this->flat($this->residential), $this->flat($this->residential)])
            ->sortBy('id')->values();

        $bills = $this->generate()->keyBy('flat_id');

        // 8000 / 3 with 0.02 on the lowest-id shop.
        $this->assertSame('2666.68', (string) $bills[$shops[0]->id]->total_amount);
        $this->assertSame('2666.66', (string) $bills[$shops[2]->id]->total_amount);

        // 5000 / 2 divides evenly -- and critically, across the homes, not across five units.
        $this->assertSame('2500.00', (string) $bills[$homes[0]->id]->total_amount);
        $this->assertSame('2500.00', (string) $bills[$homes[1]->id]->total_amount);

        // Each head sums to its own contract total.
        $shopSum = $shops->reduce(fn (string $c, Flat $f): string => bcadd($c, (string) $bills[$f->id]->total_amount, 2), '0.00');
        $homeSum = $homes->reduce(fn (string $c, Flat $f): string => bcadd($c, (string) $bills[$f->id]->total_amount, 2), '0.00');

        $this->assertSame('8000.00', $shopSum);
        $this->assertSame('5000.00', $homeSum);
        $this->assertLedgerIsBalanced();
    }

    public function test_a_restricted_equal_share_head_ignores_inactive_units_of_that_type(): void
    {
        $this->chargeHead('Market Security', 'equal_share', '9000.00', $this->shop);
        $this->flat($this->shop);
        $this->flat($this->shop);
        $this->flat($this->shop);
        Flat::factory()->for($this->building)->create([
            'unit_type_id' => $this->shop->id,
            'is_active' => false,
        ]);

        $bills = $this->generate();

        $this->assertCount(3, $bills);
        $this->assertSame(['3000.00', '3000.00', '3000.00'], $bills->map(fn ($b): string => (string) $b->total_amount)->all());
    }

    public function test_a_head_may_apply_to_several_unit_types(): void
    {
        $head = $this->chargeHead('Corridor Lighting', 'per_flat', '300.00', $this->shop);
        $head->unitTypes()->attach($this->residential);

        $shop = $this->flat($this->shop);
        $home = $this->flat($this->residential);
        $garage = $this->flat(UnitType::factory()->create(['building_id' => $this->building->id, 'name' => 'Garage']));

        $bills = $this->generate()->keyBy('flat_id');

        $this->assertSame('300.00', (string) $bills[$shop->id]->total_amount);
        $this->assertSame('300.00', (string) $bills[$home->id]->total_amount);
        $this->assertArrayNotHasKey($garage->id, $bills->all());
    }

    public function test_the_preview_matches_what_generation_bills(): void
    {
        $this->chargeHead('Guard Salary', 'per_flat', '1200.00');
        $this->chargeHead('Shutter Cleaning', 'per_sqft', '2.00', $this->shop);
        $shop = $this->flat($this->shop, '900.00');

        // The screen preview and bill generation must never disagree: both go through
        // the same calculator.
        $this->assertSame('3000.00', app(ChargeCalculator::class)->totalFor($shop));
        $this->assertSame('3000.00', (string) $this->generate()->firstOrFail()->total_amount);
    }
}
