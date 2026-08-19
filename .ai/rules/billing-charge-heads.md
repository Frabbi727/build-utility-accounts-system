# Charge heads, not a rate column

What a building bills every month lives in `charge_heads` — one row per component (guard
salary, lift, water, cleaning), each with its own income account and a `ChargeBasis` of
`per_flat`, `per_sqft` or `equal_share`.

- `buildings` deliberately carries **no** `service_charge_rate`. That column was dropped in
  `2026_08_19_120300_convert_building_service_charge_to_charge_heads`. Do not reintroduce a
  bill amount on the building — it would be a second source of truth for money.
- Billing-*policy* parameters are a different thing and do belong on `buildings`:
  `due_day_of_month`, `late_fee_type`, `late_fee_amount`, `late_fee_grace_days`. These are
  expressed nowhere else, so they are not duplicating anything.
- `App\Services\Billing\ChargeCalculator` is the only place a flat's monthly recurring
  amount is computed. Bill generation and the on-screen previews both call it, so there is
  no second implementation to drift. Keep one-off charges out of it — those are ad-hoc
  charges, applied through a `BillLineSource`.
- Per-flat exceptions live in `flat_charge_overrides` (a negotiated amount, or an exemption).
- `equal_share` divides the head's monthly total across active flats; `bcdiv` truncates, so
  the leftover paisa is added to the **lowest-id active flat**. That keeps the split
  deterministic and makes the heads sum to the contract total exactly.

## Bills and the ledger must move together

A bill's `outstandingAmount()` is `total_amount` minus its allocations. If you post a
receivable to the ledger you must also change the bill, or the two disagree. Anything that
adds money to an existing bill (late fees, ad-hoc charges) has to **both** create a
`BillItem` and raise `total_amount`, then call `refreshStatus()`.

`payment_allocations` is unique on `(payment_id, service_charge_bill_id)`. When applying
money to a bill a payment may already have paid into, grow the existing allocation row —
do not insert a second one.


## A bill line comes from a BillLineSource, never from inline code

`GenerateMonthlyBills` does not know what a charge head, a meter reading or a distributed
cost is. Each is an `App\Services\Billing\LineSources\BillLineSource` returning
`App\Support\BillLineData`, and the generator does the same four things for all of them:
create the `BillItem`, add to `total_amount`, append the journal credit, call `markBilled()`.

- **Never create a `BillItem` inline in `GenerateMonthlyBills`.** That sequence of four
  steps is exactly what drifts when it is copy-pasted per charge kind, and the bill
  disagreeing with the ledger is the failure this whole rule file exists to prevent.
- `ChargeHeadLines` is a **pure adapter** over `ChargeCalculator` — there is not one `bc*`
  call in it, and there must never be, or the "only place" rule above has been broken by
  the refactor meant to protect it.
- **Sources do not filter zero amounts.** Generation drops them centrally, because
  `JournalService` requires every line to be a debit or a credit and would reject a zero
  as neither. A per-source guard is one source away from being forgotten.
- **A flat is billed if any source produced a line.** Do not reintroduce a check that
  gives up when charge heads are empty — a flat exempt from every head can still have a
  meter reading.
- Source order is declared explicitly in `AppServiceProvider`, because it is the order
  lines print on the bill. Container tag ordering is unspecified; do not rely on it.
- Anything picked up late must match on `<= billingMonth` with a null `applied_to_bill_id`,
  the way `AdHocChargeLines` does. Bills are unique per (flat, month) and generation skips
  flats already billed, so an exact-month match silently loses late entries forever. The
  consequence is that a line's own month may differ from the bill's — say so in the
  line description.

`ApplyLateFees` is deliberately not a source: it mutates bills that already exist rather
than contributing to generation.
