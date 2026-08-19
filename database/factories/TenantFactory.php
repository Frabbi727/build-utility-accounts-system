<?php

namespace Database\Factories;

use App\Models\Flat;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'flat_id' => Flat::factory(),
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'lease_started_on' => now()->subYear(),
            'lease_ended_on' => null,
        ];
    }
}
