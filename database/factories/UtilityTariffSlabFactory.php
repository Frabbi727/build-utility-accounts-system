<?php

namespace Database\Factories;

use App\Models\UtilityTariff;
use App\Models\UtilityTariffSlab;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UtilityTariffSlab> */
class UtilityTariffSlabFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'utility_tariff_id' => UtilityTariff::factory(),
            'from_unit' => '0.000',
            'to_unit' => null,
            'rate_per_unit' => '8.5000',
        ];
    }
}
