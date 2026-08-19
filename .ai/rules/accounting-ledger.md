# The ledger is the single source of truth for money

`App\Services\JournalService::post()` is the **only** writer of `journal_entries` and
`journal_lines`. Nothing else may insert into those tables — that is what makes the
ledger trustworthy.

- Every financial event posts a **balanced** entry. `post()` asserts at least two lines,
  debit XOR credit per line, no negatives, and `SUM(debit) = SUM(credit)` before the
  transaction opens. A Postgres CHECK constraint (`journal_lines_debit_xor_credit`)
  enforces the per-line half again at the database.
- Build lines with `App\Support\JournalLineData::debit()` / `::credit()`. Never construct
  a `JournalLine` directly.
- Pass the originating record as `$source` so the entry links back polymorphically.
- **Balances are never cached in columns.** Derive them with `JournalService::balanceFor()`
  or `App\Services\Reporting\LedgerReports`. If you are tempted to add a `balance` column,
  don't — it will drift.
- Corrections are made with `reverse()`, which posts a contra entry. Entries are never
  edited or deleted, and an entry cannot be reversed twice.
- Resolve accounts by `App\Enums\AccountCode`, never by hardcoded id, so seeded ledgers
  stay portable across environments.
- Posting into a locked `AccountingPeriod` throws `PeriodLockedException`.
- `post()` and `reverse()` each write an `AuditLog` row; `tests/Feature/Accounting/AuditLogTest.php`
  pins that behaviour.

Any new money event is a service under `app/Services/<Area>/` that composes
`JournalService` — see `Billing/RecordPayment`, `Billing/ApplyLateFees`,
`Expenses/RecordExpense` for the shape.
