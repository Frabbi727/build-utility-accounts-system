<?php

namespace Database\Factories;

use App\Enums\PeriodStatus;
use App\Models\AccountingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountingPeriod> */
class AccountingPeriodFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'status' => PeriodStatus::Open,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn (): array => [
            'status' => PeriodStatus::Locked,
            'locked_at' => now(),
        ]);
    }
}
