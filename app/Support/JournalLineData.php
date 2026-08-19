<?php

namespace App\Support;

use App\Models\Account;

/**
 * One side of a journal entry, as handed to the JournalService.
 */
final readonly class JournalLineData
{
    private function __construct(
        public int $accountId,
        public string $debit,
        public string $credit,
        public ?int $flatId = null,
        public ?string $memo = null,
    ) {}

    public static function debit(Account|int $account, string|float|int $amount, ?int $flatId = null, ?string $memo = null): self
    {
        return new self(
            $account instanceof Account ? $account->id : $account,
            bcadd((string) $amount, '0', 2),
            '0.00',
            $flatId,
            $memo,
        );
    }

    public static function credit(Account|int $account, string|float|int $amount, ?int $flatId = null, ?string $memo = null): self
    {
        return new self(
            $account instanceof Account ? $account->id : $account,
            '0.00',
            bcadd((string) $amount, '0', 2),
            $flatId,
            $memo,
        );
    }

    /**
     * @return array{account_id: int, debit: string, credit: string, flat_id: int|null, memo: string|null}
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'flat_id' => $this->flatId,
            'memo' => $this->memo,
        ];
    }
}
