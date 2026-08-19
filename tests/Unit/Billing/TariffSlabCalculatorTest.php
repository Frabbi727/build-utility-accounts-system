<?php

namespace Tests\Unit\Billing;

use App\Models\UtilityTariff;
use App\Models\UtilityTariffSlab;
use App\Services\Billing\TariffSlabCalculator;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Pure arithmetic, so these run without a database: the tariff is built in memory and
 * its slab relation set directly.
 */
class TariffSlabCalculatorTest extends TestCase
{
    private TariffSlabCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new TariffSlabCalculator;
    }

    /**
     * @param  list<array{0: string, 1: string|null, 2: string}>  $bands  [from, to, rate]
     */
    private function tariff(array $bands, string $fixedCharge = '0.00'): UtilityTariff
    {
        $tariff = new UtilityTariff(['fixed_charge' => $fixedCharge]);

        $tariff->setRelation('slabs', new Collection(array_map(
            fn (array $band): UtilityTariffSlab => new UtilityTariffSlab([
                'from_unit' => $band[0],
                'to_unit' => $band[1],
                'rate_per_unit' => $band[2],
            ]),
            $bands,
        )));

        return $tariff;
    }

    private function slabbed(string $fixedCharge = '0.00'): UtilityTariff
    {
        return $this->tariff([
            ['0.000', '75.000', '4.8500'],
            ['75.000', '200.000', '6.6300'],
            ['200.000', null, '10.0700'],
        ], $fixedCharge);
    }

    public function test_a_flat_rate_is_consumption_times_the_rate(): void
    {
        $tariff = $this->tariff([['0.000', null, '8.5000']]);

        // 120 x 8.50
        $this->assertSame('1020.00', $this->calculator->totalFor($tariff, '120.000'));
    }

    public function test_consumption_inside_the_first_band_uses_only_that_rate(): void
    {
        // 50 x 4.85
        $this->assertSame('242.50', $this->calculator->totalFor($this->slabbed(), '50.000'));
    }

    public function test_consumption_exactly_on_a_boundary_fills_one_band_and_leaves_the_next_empty(): void
    {
        $result = $this->calculator->amountFor($this->slabbed(), '75.000');

        // 75 x 4.85 = 363.75, and the second band is never reached.
        $this->assertSame('363.75', $result['total']);
        $this->assertCount(1, $result['slabs']);
        $this->assertSame('75.000', $result['slabs'][0]['units']);
    }

    public function test_consumption_spanning_three_bands_charges_each_at_its_own_rate(): void
    {
        $result = $this->calculator->amountFor($this->slabbed(), '250.000');

        // 75 x 4.85 = 363.75, 125 x 6.63 = 828.75, 50 x 10.07 = 503.50
        $this->assertSame(
            ['75.000', '125.000', '50.000'],
            array_column($result['slabs'], 'units'),
        );
        $this->assertSame(['363.75', '828.75', '503.50'], array_column($result['slabs'], 'amount'));
        $this->assertSame('1696.00', $result['total']);
    }

    public function test_zero_consumption_still_costs_the_fixed_charge(): void
    {
        $result = $this->calculator->amountFor($this->slabbed('125.00'), '0.000');

        $this->assertSame('0.00', $result['energy']);
        $this->assertSame('125.00', $result['fixed']);
        $this->assertSame('125.00', $result['total']);
        $this->assertSame([], $result['slabs']);
    }

    public function test_the_fixed_charge_is_added_on_top_of_the_energy(): void
    {
        $result = $this->calculator->amountFor($this->slabbed('125.00'), '250.000');

        $this->assertSame('1696.00', $result['energy']);
        $this->assertSame('1821.00', $result['total']);
    }

    public function test_a_fractional_consumption_is_priced_at_full_precision(): void
    {
        $tariff = $this->tariff([['0.000', null, '8.5000']]);

        // 123.456 x 8.5 = 1049.376 -> 1049.38 half-up.
        $this->assertSame('1049.38', $this->calculator->totalFor($tariff, '123.456'));
    }

    public function test_the_energy_total_is_rounded_once_rather_than_per_band(): void
    {
        // Each band costs x.005 on its own; rounding per band would give 3 x 0.01 = 0.03
        // more than rounding the exact sum 0.015 once.
        $tariff = $this->tariff([
            ['0.000', '1.000', '0.0050'],
            ['1.000', '2.000', '0.0050'],
            ['2.000', null, '0.0050'],
        ]);

        $result = $this->calculator->amountFor($tariff, '3.000');

        // Exact energy is 0.015, which rounds half-up to 0.02 -- not the 0.03 that
        // per-band rounding would produce.
        $this->assertSame('0.02', $result['energy']);
        $this->assertSame(['0.01', '0.01', '0.01'], array_column($result['slabs'], 'amount'));
    }

    public function test_the_total_is_the_exact_sum_of_its_reported_components(): void
    {
        $result = $this->calculator->amountFor($this->slabbed('125.00'), '317.250');

        $this->assertSame(
            $result['total'],
            bcadd($result['energy'], $result['fixed'], 2),
            'A printed bill must reconcile to its own line total.',
        );
    }

    public function test_consumption_beyond_every_band_lands_in_the_open_ended_one(): void
    {
        $result = $this->calculator->amountFor($this->slabbed(), '1000.000');

        $this->assertSame('800.000', $result['slabs'][2]['units']);
        // 363.75 + 828.75 + (800 x 10.07 = 8056.00)
        $this->assertSame('9248.50', $result['total']);
    }

    public function test_the_effective_rate_is_blended_across_the_bands(): void
    {
        $result = $this->calculator->amountFor($this->slabbed(), '250.000');

        // 1696.00 / 250 -- between the lowest and highest band rates, equal to neither.
        $this->assertSame('6.7840', $this->calculator->effectiveRate($result['energy'], '250.000'));
    }

    public function test_a_zero_consumption_has_no_effective_rate(): void
    {
        $this->assertSame('0.0000', $this->calculator->effectiveRate('0.00', '0.000'));
    }
}
