# Payment tracking, carried-forward dues and printable bills

Date: 2026-08-20
Status: approved, ready for implementation planning

## Problem

The accounting core already handles money correctly, but an operator cannot see it.

- Partial payments work, yet no screen lists who paid what. The only building-wide view of
  receipts is the Collection Report, which is a period summary, not a payment history.
- Dues do carry forward in substance — an unpaid bill stays open and the receivable
  accumulates — but a generated bill shows only that month's charges. An owner with
  arrears receives a bill whose total is less than what they owe, with nothing on it
  explaining the difference.
- There is a printable receipt but no printable **bill**. There is no document to hand an
  owner, so the meter readings and unit rates already stored on every bill line are never
  seen by the person being billed.
- There is no path from "this flat owes money" to "take money from this flat". The dues
  report and the flat statement both dead-end.

The result is a system that is accurate and unreadable. Every item below is presentation
over data that already exists.

## What already works, and is not changed

Stated explicitly so the implementation does not rebuild it:

- `App\Services\Billing\RecordPayment` records a receipt, allocates it oldest-bill-first
  via `AllocationPlanner`, and credits any excess to Advance from Owners.
- `App\Services\Billing\ApplyAdvances` draws an outstanding advance down against a newly
  generated bill.
- `App\Services\Billing\ApplyLateFees` accrues a late fee onto both the bill and the ledger.
- `bill_items` stores `quantity` (scale 3), `unit_rate` (scale 4) and `unit_label`
  denormalised, plus a `source` morph pointing at whatever produced the line.
- For a metered line that source is a `MeterReading`, carrying `previous_reading`,
  `current_reading`, `consumption`, `reading_date` and `is_estimated`.
- `LedgerReports::agingByFlat()` ages dues; `ReceiptController` prints a receipt.

## Decisions

### 1. Carry-forward is display-only

An unpaid balance is **not** re-charged onto the next bill. The old bill stays open, the
new bill still totals only its own month's charges, and the printed document shows:

```
Previous dues (b/f)   +  This month's charges  −  Paid  =  TOTAL PAYABLE NOW
```

Rejected alternative: adding an "arrears brought forward" line item to the new bill and
closing the old one. It risks double-posting the receivable, and it destroys aging — every
due would look current the month after it was raised.

Consequence: no new journal entries, no schema change, and `agingByFlat()` keeps telling
the truth about how old each due is.

### 2. One place computes what is owed

A single service answers "what does this flat owe, and how does that break down" for every
consumer: the printable bill, the batch print, the flat statement, the dues report and the
collect-due form. This mirrors the discipline `ChargeCalculator` already enforces for
charges — bill generation and the on-screen preview call the same code, so they cannot
drift.

Rejected alternatives: computing arrears inside each Livewire component (guaranteed to
drift, and the drift surfaces in front of an owner holding a printed bill); denormalising
`arrears_brought_forward` / `payable_now` onto `service_charge_bills` (violates the
standing rule that balances are never cached, and the figure would go stale the moment a
payment landed — which is precisely when it must not).

### 3. Payment reversal is out of scope

The payments screen is read-only. Voiding a payment has to unwind allocations, restore
advances, re-open bills and post a contra entry; folding it into this work would double
the risk of the part actually being asked for. It is a separate piece of work.

## Design

### `BillSummary`

New service `App\Services\Billing\BillSummary`, returning a readonly
`App\Support\BillSummaryData` (following the `BillLineData` / `JournalLineData` precedent
in `app/Support`).

For a `ServiceChargeBill` it answers:

| Field | Derivation |
|---|---|
| `monthCharges` | `bill->total_amount` |
| `paidOnThisBill` | `bill->allocatedAmount()` |
| `thisBillOutstanding` | `bill->outstandingAmount()` |
| `totalDue` | `JournalService::balanceFor(ServiceChargeReceivable, flat_id)` |
| `broughtForward` | `bcsub(totalDue, thisBillOutstanding, 2)` |
| `priorOpenBills` | the flat's earlier bills with `outstandingAmount() > 0`, oldest first |
| `paymentsReceived` | payments allocated to this bill, via `PaymentAllocation` |
| `advanceHeld` | `balanceFor(AdvanceFromOwners, flat_id)` |

**`broughtForward` is derived by subtraction, not by summing the prior bills.** That makes
`broughtForward + thisBillOutstanding == totalDue` true by construction, so a printed bill
can never disagree with the dues report or the trial balance — including for a ledger
charge posted with no bill behind it, which summing bills would silently omit.
`agingByFlat()` already reconciles to the ledger this same way.

`priorOpenBills` is therefore a **detail listing, not the source** of the b/f figure. Where
the listed bills sum to less than `broughtForward`, the remainder is a ledger charge with
no bill; the sheet shows it as a single "other charges" line so the column still adds up.

All arithmetic uses `bcadd` / `bcsub` / `bccomp` at scale 2. Quantities and rates are never
put through scale-2 rules.

### Printable bill

`App\Http\Controllers\BillController`, a plain controller like `ReceiptController` — there
is nothing interactive on a print page and it wants its own bare layout.

- `GET bills/{bill}/print` → route `bills.print`. Registered alongside `flats.statement`,
  outside the `role:` groups and guarded by `Gate::authorize('view', $bill)` alone, so an
  owner reaches their own bill — the same pattern `payments.receipt` uses.
- `GET billing/bills/print?month=YYYY-MM` → route `bills.print-month`. Scoped to
  `CurrentBuilding`, role-gated `admin|accountant|committee`, renders every bill for that
  month with a page break between sheets.

Both render the same `x-bill-sheet` component, so single and batch cannot diverge. The
print layout copies `resources/views/receipts/payment.blade.php`: `.no-print` toolbar,
`window.print()`, `@media print` rules.

Sheet contents:

1. Building header (`displayName()`, address), bill no, billing month, due date
2. Flat number, owner name
3. **Previous dues (b/f)** — each prior open bill by number and month with its outstanding,
   plus the reconciling "other charges" line if needed; suppressed entirely when zero
4. **This month's charges** — every `BillItem`, and for a metered line the full working:
   previous reading → current reading, consumption × unit rate = amount, with the unit
   label and an "estimated" marker when `is_estimated`
5. Month total
6. **Payments received** against this bill — receipt no, date, method, amount
7. **TOTAL PAYABLE NOW**, and advance held when non-zero

`x-bill-lines` is extended (not duplicated) to render the meter-reading detail, since the
flat statement already uses it.

**Policy fix:** `ServiceChargeBillPolicy::view()` currently returns `isStaff()` only. It
gains the owner branch that `PaymentPolicy::view()` already has, so an owner can print
their own bill and no one else's.

### Screens

**New — `App\Livewire\PaymentList`** at `GET payments`, route `payments.index`, role-gated
`admin|accountant|committee`, with `$this->authorize('viewAny', Payment::class)` at the top
of `render()`. Columns: received date, receipt no (linking to the printable receipt), flat
(linking to the statement), owner, amount, method, reference, received by, and what the
payment settled — the bill numbers, or "held as advance". Filters: flat, date range,
method, and a search over receipt no and reference. Paginated. Menu entry under Billing;
`Navigation` picks it up automatically once the route exists.

**`FlatStatement`** — each bill card in the breakdown gains a status badge, its
total / paid / outstanding figures, the receipts that paid it, and a **Print bill** link.
A **Collect due** button appears for `canManageMoney()` users, linking to the payment form
with the flat prefilled.

**`Reports\OwnerDues`** — a **Collect** action per row, same target.

**`RecordPaymentForm`** — accepts a `flat` query parameter, and on flat selection prefills
`amount` with that flat's ledger outstanding and shows outstanding + advance held. The
amount stays editable, so a partial payment is one keystroke. The existing oldest-first
allocation confirmation is unchanged.

**`GenerateBills`** — after a run, a "Print all bills" link for the month just generated.

### Localization

Every new string lands in `lang/en` and `lang/bn` in the same change; the two trees are at
exact parity and stay that way. New keys go in `billing.php` (bill document, brought
forward, payable now, meter reading labels, print actions, collect due), `nav.php`
(payments) and `reports.php` (collect action).

## Data and ledger impact

None. No migrations, no new journal entries, no cached balance columns, no change to how
money is posted. Every figure on every new screen and document is derived from
`journal_lines`, `service_charge_bills`, `bill_items` and `payment_allocations` as they
stand today.

## Testing

Feature tests (PHPUnit, real PostgreSQL, model factories), money compared with `bccomp`:

**`BillSummary`**
- `broughtForward + thisBillOutstanding == totalDue` for: a first bill with no history; a
  flat with one prior unpaid bill; a flat with a partially paid prior bill; a flat holding
  an advance; a flat with a receivable charge that has no bill behind it
- a fully paid bill reports `thisBillOutstanding` of `0.00` and a `broughtForward` equal to
  whatever remains elsewhere
- `priorOpenBills` excludes settled bills and orders oldest first

**Printable bill**
- staff may view any bill; the owning owner may view theirs; a different owner is forbidden
- a metered line renders previous reading, current reading, consumption, unit rate and unit
  label; an estimated reading is marked
- the b/f section is absent when the flat has no arrears
- batch print includes only the current building's bills for the requested month

**`PaymentList`**
- lists payments with their allocations, and marks an unallocated payment as advance
- each filter narrows correctly; a non-staff user is forbidden

**Collect due**
- the link prefills the flat and defaults the amount to the ledger outstanding
- an edited, smaller amount still allocates oldest-first and leaves the bill partially paid

`composer check` (Pint, Larastan level 5, full test suite) must pass, and its real output
reported, before the work is called done.

## Out of scope

- Voiding or editing a recorded payment
- Emailing or SMS-ing bills
- An owner-facing self-service portal beyond the existing statement and receipt
- Any change to how charges are calculated or posted
