<?php

namespace Database\Factories;

use App\Enums\AccountCode;
use App\Enums\AccountType;
use App\Enums\ChargeBasis;
use App\Enums\LateFeeType;
use App\Models\Account;
use App\Models\Building;
use App\Models\ChargeHead;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Building> */
class BuildingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->streetName().' Tower',
            'name_bn' => null,
            'address' => fake()->address(),
            'due_day_of_month' => 10,
        ];
    }

    /**
     * A building that charges a late fee on overdue bills.
     */
    public function lateFee(LateFeeType $type, string $amount, int $graceDays = 0): static
    {
        return $this->state(fn (): array => [
            'late_fee_type' => $type,
            'late_fee_amount' => $amount,
            'late_fee_grace_days' => $graceDays,
        ]);
    }

    /**
     * A building billing one flat-rate charge head — the simplest shape a test needs.
     */
    public function flatRate(string $amount = '3000.00'): static
    {
        return $this->withChargeHead(ChargeBasis::PerFlat, $amount, 'Service Charge');
    }

    /**
     * A building billing one per-square-foot charge head.
     */
    public function perSqft(string $rate = '3.50'): static
    {
        return $this->withChargeHead(ChargeBasis::PerSqft, $rate, 'Service Charge');
    }

    /**
     * A building billing one equal-share head, split across its active flats.
     */
    public function equalShare(string $monthlyTotal = '8000.00'): static
    {
        return $this->withChargeHead(ChargeBasis::EqualShare, $monthlyTotal, 'Cleaning Contract');
    }

    private function withChargeHead(ChargeBasis $basis, string $amount, string $name): static
    {
        return $this->afterCreating(function (Building $building) use ($basis, $amount, $name): void {
            ChargeHead::factory()->create([
                'building_id' => $building->id,
                'account_id' => $this->serviceChargeIncomeId(),
                'name' => $name,
                'basis' => $basis,
                'amount' => $amount,
            ]);
        });
    }

    /**
     * The seeded income account, created on the fly for tests that skip the seeder.
     */
    private function serviceChargeIncomeId(): int
    {
        return Account::firstOrCreate(
            ['code' => AccountCode::ServiceChargeIncome->value],
            ['name' => 'Service Charge Income', 'type' => AccountType::Income],
        )->id;
    }
}
