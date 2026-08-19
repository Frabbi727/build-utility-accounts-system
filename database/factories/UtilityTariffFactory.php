<?php

namespace Database\Factories;

use App\Models\Utility;
use App\Models\UtilityTariff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<UtilityTariff> */
class UtilityTariffFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'utility_id' => Utility::factory(),
            'effective_from' => Carbon::parse('2026-01-01'),
            'effective_to' => null,
            'fixed_charge' => '0.00',
        ];
    }

    /**
     * A single flat rate — one slab from zero with no upper bound.
     */
    public function flatRate(string $rate = '8.5000', string $fixedCharge = '0.00'): static
    {
        return $this->state(fn (): array => ['fixed_charge' => $fixedCharge])
            ->afterCreating(function (UtilityTariff $tariff) use ($rate): void {
                $tariff->slabs()->create([
                    'from_unit' => '0.000',
                    'to_unit' => null,
                    'rate_per_unit' => $rate,
                ]);
            });
    }

    /**
     * A three-band progressive tariff in the shape a DESCO/DPDC bill uses.
     */
    public function slabbed(string $fixedCharge = '0.00'): static
    {
        return $this->state(fn (): array => ['fixed_charge' => $fixedCharge])
            ->afterCreating(function (UtilityTariff $tariff): void {
                $tariff->slabs()->createMany([
                    ['from_unit' => '0.000', 'to_unit' => '75.000', 'rate_per_unit' => '4.8500'],
                    ['from_unit' => '75.000', 'to_unit' => '200.000', 'rate_per_unit' => '6.6300'],
                    ['from_unit' => '200.000', 'to_unit' => null, 'rate_per_unit' => '10.0700'],
                ]);
            });
    }
}
