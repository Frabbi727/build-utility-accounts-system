<?php

namespace Database\Factories;

use App\Models\CostDistribution;
use App\Models\CostDistributionLine;
use App\Models\Flat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CostDistributionLine> */
class CostDistributionLineFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'cost_distribution_id' => CostDistribution::factory(),
            'flat_id' => Flat::factory(),
            'weight' => '1.0000',
            'amount' => '1000.00',
            'applied_to_bill_id' => null,
        ];
    }
}
