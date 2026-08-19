<?php

namespace Database\Factories;

use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JournalEntry> */
class JournalEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'entry_date' => now()->toDateString(),
            'ref_no' => 'JE-'.fake()->unique()->numerify('######'),
            'description' => fake()->sentence(),
            'accounting_period_id' => AccountingPeriod::factory(),
            'created_by' => null,
        ];
    }
}
