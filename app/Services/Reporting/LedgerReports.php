<?php

namespace App\Services\Reporting;

use App\Enums\AccountCode;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Flat;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\ServiceChargeBill;
use App\Services\JournalService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the ledger.
 *
 * Every figure any report shows is summed from journal_lines here — nothing reads a
 * stored balance. If a report ever disagrees with the trial balance, something has
 * written to the ledger outside JournalService.
 */
class LedgerReports
{
    public function __construct(private readonly JournalService $journal) {}

    /**
     * Net movement of every postable account of a type, in the type's natural direction.
     *
     * Leave $from null for an as-of-date balance (the balance sheet); pass both dates
     * for movement within a period (income & expenditure, expense by category).
     *
     * @return Collection<int, array{account: Account, amount: numeric-string}>
     */
    public function balancesByType(AccountType $type, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $sums = $this->sumLines(
            fn ($query) => $query->where('accounts.type', $type->value),
            $from,
            $to,
        );

        return Account::query()
            ->where('type', $type->value)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($sums, $type): array {
                /** @var array{debit: numeric-string, credit: numeric-string} $sum */
                $sum = $sums[$account->id] ?? ['debit' => '0.00', 'credit' => '0.00'];

                return [
                    'account' => $account,
                    'amount' => $type->isDebitNormal()
                        ? bcsub($sum['debit'], $sum['credit'], 2)
                        : bcsub($sum['credit'], $sum['debit'], 2),
                ];
            })
            ->filter(fn (array $row): bool => bccomp($row['amount'], '0', 2) !== 0)
            ->values();
    }

    /**
     * Total of a set of rows produced by balancesByType().
     *
     * @param  Collection<int, array{account: Account, amount: numeric-string}>  $rows
     * @return numeric-string
     */
    public function total(Collection $rows): string
    {
        return $rows->reduce(fn (string $carry, array $row): string => bcadd($carry, $row['amount'], 2), '0.00');
    }

    /**
     * Journal lines touching an account within a date range, oldest first.
     *
     * @return Collection<int, JournalLine>
     */
    public function movements(Account $account, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return JournalLine::query()
            ->with('journalEntry')
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($entry) use ($from, $to): void {
                if ($from !== null) {
                    $entry->whereDate('entry_date', '>=', $from);
                }

                if ($to !== null) {
                    $entry->whereDate('entry_date', '<=', $to);
                }
            })
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->select('journal_lines.*')
            ->get();
    }

    /**
     * Account balance the day before $from — the opening figure of a cash or bank book.
     *
     * @return numeric-string
     */
    public function openingBalance(Account $account, Carbon $from): string
    {
        return $this->journal->balanceFor($account, null, $from->copy()->subDay());
    }

    /**
     * Outstanding receivable per flat, straight from the sub-ledger.
     *
     * One grouped query rather than a balanceFor() call per flat.
     *
     * @return Collection<int, numeric-string> keyed by flat id
     */
    public function outstandingByFlat(?Carbon $asOf = null): Collection
    {
        $receivable = $this->journal->account(AccountCode::ServiceChargeReceivable);

        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $receivable->id)
            ->whereNotNull('journal_lines.flat_id')
            ->when($asOf, fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $asOf))
            ->groupBy('journal_lines.flat_id')
            ->select('journal_lines.flat_id', DB::raw('SUM(journal_lines.debit) as debit'), DB::raw('SUM(journal_lines.credit) as credit'))
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->flat_id => bcsub((string) $row->debit, (string) $row->credit, 2),
            ]);
    }

    /**
     * Owner dues aged into buckets.
     *
     * The outstanding total per flat is the ledger figure and nothing else. The split
     * into buckets is an operational view laid on top: the ledger outstanding is applied
     * against the flat's bills oldest-due-first, so the buckets always add back up to the
     * ledger. Anything left over (a charge posted with no bill behind it) falls into the
     * current bucket.
     *
     * @return Collection<int, array{
     *     flat: Flat,
     *     outstanding: numeric-string,
     *     current: numeric-string,
     *     days_31_60: numeric-string,
     *     days_61_90: numeric-string,
     *     days_90_plus: numeric-string
     * }>
     */
    public function agingByFlat(?Carbon $asOf = null): Collection
    {
        $asOf = $asOf ?? Carbon::today();

        $outstanding = $this->outstandingByFlat($asOf)
            ->filter(fn (string $amount): bool => bccomp($amount, '0', 2) > 0);

        if ($outstanding->isEmpty()) {
            return collect();
        }

        $flats = Flat::with(['building', 'owner'])
            ->whereIn('id', $outstanding->keys())
            ->orderBy('number')
            ->get();

        $bills = ServiceChargeBill::with('allocations')
            ->whereIn('flat_id', $outstanding->keys())
            ->whereDate('billing_month', '<=', $asOf)
            ->orderBy('due_date')
            ->get()
            ->groupBy('flat_id');

        return $flats->map(function (Flat $flat) use ($outstanding, $bills, $asOf): array {
            $remaining = $outstanding[$flat->id];

            $buckets = ['current' => '0.00', 'days_31_60' => '0.00', 'days_61_90' => '0.00', 'days_90_plus' => '0.00'];

            foreach ($bills->get($flat->id, collect()) as $bill) {
                if (bccomp($remaining, '0', 2) <= 0) {
                    break;
                }

                $due = $bill->outstandingAmount();

                if (bccomp($due, '0', 2) <= 0) {
                    continue;
                }

                $take = bccomp($due, $remaining, 2) > 0 ? $remaining : $due;
                $bucket = $this->bucketFor($bill->due_date, $asOf);

                $buckets[$bucket] = bcadd($buckets[$bucket], $take, 2);
                $remaining = bcsub($remaining, $take, 2);
            }

            // Ledger charges with no bill behind them still belong to somebody.
            if (bccomp($remaining, '0', 2) > 0) {
                $buckets['current'] = bcadd($buckets['current'], $remaining, 2);
            }

            return ['flat' => $flat, 'outstanding' => $outstanding[$flat->id], ...$buckets];
        })->values();
    }

    /**
     * Receipts taken in a date range, newest first.
     *
     * @return Collection<int, Payment>
     */
    public function collections(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return Payment::query()
            ->with(['flat.owner', 'flat.building'])
            ->when($from, fn ($q) => $q->whereDate('received_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('received_on', '<=', $to))
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * What the ledger says landed in cash and bank from owner payments over the range.
     *
     * The collection report compares this against the receipts it lists; a gap means a
     * payment row exists without its journal entry, or the other way round.
     *
     * @return numeric-string
     */
    public function ledgerCollections(?Carbon $from = null, ?Carbon $to = null): string
    {
        $accountIds = [
            $this->journal->account(AccountCode::CashInHand)->id,
            $this->journal->account(AccountCode::Bank)->id,
        ];

        $sums = $this->sumLines(
            fn ($query) => $query
                ->whereIn('journal_lines.account_id', $accountIds)
                ->where('journal_entries.source_type', (new Payment)->getMorphClass()),
            $from,
            $to,
            groupByAccount: false,
        );

        /** @var array{debit: numeric-string, credit: numeric-string} $sum */
        $sum = $sums[0] ?? ['debit' => '0.00', 'credit' => '0.00'];

        return bcsub($sum['debit'], $sum['credit'], 2);
    }

    /**
     * Income less expenditure to date — the surplus the balance sheet carries as equity,
     * since the ledger has no year-end closing entries.
     *
     * @return numeric-string
     */
    public function accumulatedSurplus(?Carbon $asOf = null): string
    {
        return bcsub(
            $this->total($this->balancesByType(AccountType::Income, null, $asOf)),
            $this->total($this->balancesByType(AccountType::Expense, null, $asOf)),
            2,
        );
    }

    /**
     * Sum debits and credits over journal lines, grouped by account.
     *
     * @param  callable(Builder): mixed  $filter
     * @return array<int, array{debit: numeric-string, credit: numeric-string}>
     */
    private function sumLines(callable $filter, ?Carbon $from, ?Carbon $to, bool $groupByAccount = true): array
    {
        $query = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id');

        $filter($query);

        if ($from !== null) {
            $query->whereDate('journal_entries.entry_date', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('journal_entries.entry_date', '<=', $to);
        }

        $select = [DB::raw('SUM(journal_lines.debit) as debit'), DB::raw('SUM(journal_lines.credit) as credit')];

        if ($groupByAccount) {
            $query->groupBy('journal_lines.account_id');
            array_unshift($select, 'journal_lines.account_id');
        }

        return $query->select($select)->get()
            ->mapWithKeys(fn (object $row): array => [
                $groupByAccount ? (int) $row->account_id : 0 => [
                    'debit' => bcadd((string) $row->debit, '0', 2),
                    'credit' => bcadd((string) $row->credit, '0', 2),
                ],
            ])
            ->all();
    }

    /**
     * Which aging bucket a due date falls into as of a given day.
     *
     * @return 'current'|'days_31_60'|'days_61_90'|'days_90_plus'
     */
    private function bucketFor(Carbon $dueDate, Carbon $asOf): string
    {
        $daysOverdue = $dueDate->startOfDay()->diffInDays($asOf->copy()->startOfDay(), false);

        return match (true) {
            $daysOverdue > 90 => 'days_90_plus',
            $daysOverdue > 60 => 'days_61_90',
            $daysOverdue > 30 => 'days_31_60',
            default => 'current',
        };
    }
}
