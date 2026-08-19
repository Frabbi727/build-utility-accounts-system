<?php

namespace Database\Factories;

use App\Enums\AccountCode;
use App\Enums\AccountType;
use App\Enums\DistributionBasis;
use App\Enums\DistributionStatus;
use App\Models\Account;
use App\Models\Building;
use App\Models\CostDistribution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<CostDistribution> */
class CostDistributionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'recovery_account_id' => fn (): int => Account::firstOrCreate(
                ['code' => AccountCode::OtherIncome->value],
                ['name' => 'Other Income', 'type' => AccountType::Income],
            )->id,
            'source_expense_id' => null,
            'utility_id' => null,
            'title' => fake()->words(3, true),
            'description' => null,
            'total_amount' => '10000.00',
            'basis' => DistributionBasis::Equal,
            'billing_month' => Carbon::parse('2026-08-01'),
            'status' => DistributionStatus::Draft,
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function perSqft(): static
    {
        return $this->state(fn (): array => ['basis' => DistributionBasis::PerSqft]);
    }

    public function byConsumption(int $utilityId): static
    {
        return $this->state(fn (): array => [
            'basis' => DistributionBasis::ByConsumption,
            'utility_id' => $utilityId,
        ]);
    }

    /**
     * Approved without going through ApproveDistribution — use only where the lines are
     * being set up by hand. The DB constraint still requires the approval stamp.
     */
    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => DistributionStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
