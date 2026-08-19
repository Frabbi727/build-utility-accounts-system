<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Account> */
class AccountFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numberBetween(6000, 9999),
            'name' => fake()->words(2, true),
            'name_bn' => null,
            'type' => fake()->randomElement(AccountType::cases()),
            'parent_id' => null,
            'is_postable' => true,
            'is_active' => true,
        ];
    }

    public function ofType(AccountType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
