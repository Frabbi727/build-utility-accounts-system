<?php

namespace App\Support;

use App\Models\Account;
use Illuminate\Database\Eloquent\Model;

/**
 * One line destined for a monthly bill, as handed to GenerateMonthlyBills.
 *
 * Every `BillLineSource` speaks in these, which is what lets bill generation treat a
 * charge head, a meter reading and a distributed cost identically: it creates the
 * BillItem, adds to the total and posts the credit once, in one place, for all of them.
 *
 * Amounts are normalised to scale 2 on construction; quantities are scale 3 and rates
 * scale 4, because neither is money (see `.ai/rules/money-arithmetic.md`).
 */
final readonly class BillLineData
{
    private function __construct(
        public int $accountId,
        public string $description,
        public string $amount,
        public ?string $quantity = null,
        public ?string $unitRate = null,
        public ?string $unitLabel = null,
        public ?Model $source = null,
    ) {}

    /**
     * A flat amount with no quantity behind it — a charge head, an ad-hoc charge.
     */
    public static function make(
        Account|int $account,
        string $description,
        string|float|int $amount,
        ?Model $source = null,
    ): self {
        return new self(
            $account instanceof Account ? $account->id : $account,
            $description,
            bcadd((string) $amount, '0', 2),
            source: $source,
        );
    }

    /**
     * A metered line, carrying what it was computed from so the bill can show its working.
     */
    public static function metered(
        Account|int $account,
        string $description,
        string|float|int $amount,
        string $quantity,
        string $unitRate,
        string $unitLabel,
        ?Model $source = null,
    ): self {
        return new self(
            $account instanceof Account ? $account->id : $account,
            $description,
            bcadd((string) $amount, '0', 2),
            bcadd($quantity, '0', 3),
            bcadd($unitRate, '0', 4),
            $unitLabel,
            $source,
        );
    }

    /**
     * Whether this line carries any money. Zero-amount lines are dropped before the
     * ledger sees them: JournalService requires each line to be a debit or a credit,
     * so a zero would be rejected as neither.
     */
    public function isZero(): bool
    {
        return bccomp($this->amount, '0', 2) <= 0;
    }

    /**
     * @return array{
     *     account_id: int, description: string, amount: string, quantity: string|null,
     *     unit_rate: string|null, unit_label: string|null,
     *     source_type: string|null, source_id: int|null
     * }
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'description' => $this->description,
            'amount' => $this->amount,
            'quantity' => $this->quantity,
            'unit_rate' => $this->unitRate,
            'unit_label' => $this->unitLabel,
            'source_type' => $this->source?->getMorphClass(),
            'source_id' => $this->source?->getKey(),
        ];
    }
}
