<?php

namespace Database\Factories;

use App\Enums\AccountCode;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\AdHocCharge;
use App\Models\Flat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdHocCharge> */
class AdHocChargeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'flat_id' => Flat::factory(),
            'account_id' => fn (): int => Account::firstOrCreate(
                ['code' => AccountCode::OtherIncome->value],
                ['name' => 'Other Income', 'type' => AccountType::Income, 'is_postable' => true, 'is_active' => true],
            )->id,
            'description' => fake()->sentence(3),
            'amount' => '500.00',
            'effective_month' => now()->startOfMonth(),
            'applied_to_bill_id' => null,
        ];
    }
}
