<?php

namespace Database\Factories;

use App\Enums\AccountCode;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Building;
use App\Models\Utility;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Utility> */
class UtilityFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'account_id' => fn (): int => Account::firstOrCreate(
                ['code' => AccountCode::ServiceChargeIncome->value],
                ['name' => 'Service Charge Income', 'type' => AccountType::Income],
            )->id,
            'name' => fake()->unique()->words(2, true),
            'name_bn' => null,
            'unit_label' => 'kWh',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function electricity(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Electricity',
            'name_bn' => 'বিদ্যুৎ',
            'unit_label' => 'kWh',
        ]);
    }

    public function gas(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Gas',
            'name_bn' => 'গ্যাস',
            'unit_label' => 'm³',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
