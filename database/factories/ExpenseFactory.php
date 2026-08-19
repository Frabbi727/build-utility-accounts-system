<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'building_id' => null,
            'account_id' => Account::factory(),
            'vendor_id' => null,
            'staff_id' => null,
            'voucher_no' => 'EXP-'.fake()->unique()->numerify('######'),
            'amount' => '1500.00',
            'method' => PaymentMethod::Cash,
            'spent_on' => now()->toDateString(),
            'description' => fake()->sentence(),
            'reference' => null,
            'recorded_by' => null,
        ];
    }
}
