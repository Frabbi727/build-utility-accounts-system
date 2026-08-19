<?php

namespace Database\Factories;

use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Building> */
class BuildingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->streetName().' Tower',
            'name_bn' => null,
            'address' => fake()->address(),
            'service_charge_mode' => Building::MODE_FLAT_RATE,
            'service_charge_rate' => '3000.00',
            'due_day_of_month' => 10,
        ];
    }

    public function perSqft(string $rate = '3.50'): static
    {
        return $this->state(fn (): array => [
            'service_charge_mode' => Building::MODE_PER_SQFT,
            'service_charge_rate' => $rate,
        ]);
    }

    public function flatRate(string $rate = '3000.00'): static
    {
        return $this->state(fn (): array => [
            'service_charge_mode' => Building::MODE_FLAT_RATE,
            'service_charge_rate' => $rate,
        ]);
    }
}
