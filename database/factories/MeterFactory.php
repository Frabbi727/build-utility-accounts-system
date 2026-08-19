<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Meter;
use App\Models\Utility;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Meter> */
class MeterFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'utility_id' => Utility::factory(),
            'flat_id' => Flat::factory(),
            'meter_no' => fake()->unique()->numerify('MTR-#####'),
            'digits' => 6,
            'initial_reading' => '0.000',
            'installed_on' => null,
            'is_active' => true,
        ];
    }

    /**
     * A whole-building meter, billed to nobody.
     */
    public function common(): static
    {
        return $this->state(fn (): array => ['flat_id' => null]);
    }

    public function digits(int $digits): static
    {
        return $this->state(fn (): array => ['digits' => $digits]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
