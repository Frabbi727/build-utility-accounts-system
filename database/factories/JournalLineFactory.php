<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JournalLine> */
class JournalLineFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'debit' => '100.00',
            'credit' => '0.00',
            'flat_id' => null,
            'memo' => null,
        ];
    }

    public function credit(string $amount = '100.00'): static
    {
        return $this->state(fn (): array => ['debit' => '0.00', 'credit' => $amount]);
    }

    public function debit(string $amount = '100.00'): static
    {
        return $this->state(fn (): array => ['debit' => $amount, 'credit' => '0.00']);
    }
}
