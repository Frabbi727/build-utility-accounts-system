<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitType> */
class UnitTypeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'name' => fake()->unique()->word(),
            'name_bn' => null,
            'sort_order' => 0,
        ];
    }

    public function shop(): static
    {
        return $this->state(fn (): array => ['name' => 'Shop', 'name_bn' => 'দোকান']);
    }

    public function residential(): static
    {
        return $this->state(fn (): array => ['name' => 'Flat', 'name_bn' => 'ফ্ল্যাট']);
    }
}
