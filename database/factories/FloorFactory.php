<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Floor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Floor> */
class FloorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'name' => fake()->randomElement(['Ground', 'First', 'Second', 'Third']),
            'level' => fake()->unique()->numberBetween(0, 20),
        ];
    }
}
