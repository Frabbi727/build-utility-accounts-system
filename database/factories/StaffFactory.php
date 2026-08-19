<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Staff> */
class StaffFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'building_id' => null,
            'name' => fake()->name(),
            'designation' => fake()->randomElement(['Guard', 'Cleaner', 'Caretaker', 'Lift Operator']),
            'phone' => fake()->phoneNumber(),
            'monthly_salary' => '12000.00',
            'expense_account_id' => null,
            'joined_on' => now()->subYear(),
            'is_active' => true,
        ];
    }
}
