<?php

namespace Database\Factories;

use App\Enums\AccountCode;
use App\Enums\AccountType;
use App\Enums\ChargeBasis;
use App\Models\Account;
use App\Models\Building;
use App\Models\ChargeHead;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChargeHead> */
class ChargeHeadFactory extends Factory
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
            'basis' => ChargeBasis::PerFlat,
            'amount' => '1000.00',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function perFlat(string $amount = '1000.00'): static
    {
        return $this->state(fn (): array => ['basis' => ChargeBasis::PerFlat, 'amount' => $amount]);
    }

    public function perSqft(string $rate = '3.50'): static
    {
        return $this->state(fn (): array => ['basis' => ChargeBasis::PerSqft, 'amount' => $rate]);
    }

    public function equalShare(string $monthlyTotal = '8000.00'): static
    {
        return $this->state(fn (): array => ['basis' => ChargeBasis::EqualShare, 'amount' => $monthlyTotal]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
