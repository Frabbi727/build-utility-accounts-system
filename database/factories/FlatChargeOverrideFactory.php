<?php

namespace Database\Factories;

use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\FlatChargeOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlatChargeOverride> */
class FlatChargeOverrideFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'flat_id' => Flat::factory(),
            'charge_head_id' => ChargeHead::factory(),
            'amount' => '500.00',
            'is_exempt' => false,
        ];
    }

    public function exempt(): static
    {
        return $this->state(fn (): array => ['amount' => null, 'is_exempt' => true]);
    }
}
