<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Flat> */
class FlatFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'floor_id' => null,
            'owner_id' => Owner::factory(),
            'number' => fake()->unique()->bothify('?-#'),
            'size_sqft' => '1200.00',
            'is_active' => true,
        ];
    }
}
