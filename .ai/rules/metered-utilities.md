# Metered utilities and distributed costs

A monthly bill is assembled from four kinds of charge, each a `BillLineSource`: recurring
charge heads, metered utility consumption, shares of a distributed cost, and one-off
ad-hoc charges. See [[billing-charge-heads]] for the pipeline itself.

## Everything the operator can name is a table, not an enum

`utilities`, `unit_types` and `utility_tariffs` are operator-created rows. A market that
starts sub-metering water, or a building that invents a "Godown" unit type, must not need
a release. Only the *algorithms* stay enum-backed — `ChargeBasis`, `DistributionBasis` —
because each case is code.

## Quantities and rates are not money

Consumption is `decimal(15, 3)`, tariff rates are `decimal(15, 4)`. See
[[money-arithmetic]]: the product is accumulated at scale 4 and collapsed to taka exactly
once, through `App\Support\Money::round()`, which is the only half-up rounding in the
application. Rounding per slab sheds paisa at every band boundary a bill crosses.

## The tariff is stamped on the reading, not resolved at bill time

`ConfirmReading` writes `meter_readings.utility_tariff_id`. A reading confirmed in July may
not reach a bill until September, and resolving the rate then would price it at a tariff
that did not exist when it was taken.

- A tariff referenced by any confirmed reading is **history**. `UtilityTariff::isInUse()`
  is true and `UtilityTariffPolicy` refuses to edit or delete it. Raise a new tariff; the
  screen closes the previous one off the day before the new one starts.
- `Utility::tariffOn()` exists only to be called at confirm time. Do not call it from
  anything on the billing path.

## Consumption is stored, never recomputed

`meter_readings.consumption` is written once, at entry, by `MeterConsumption::between()`.
It is normally `current − previous`, but a meter that wraps past all-nines makes that
negative (hence `meters.digits`), and once a meter has been replaced the pair of dial
readings on a historic row no longer explains the consumption it produced. The calculator
reads `consumption` and nothing else.

Meter replacement is deactivate-and-recreate with a new `initial_reading` — never an edit,
or the old readings stop explaining the old bills.

## Common meters are billed to nobody

`meters.flat_id` null means the meter measures the whole building or a shared load. Its
consumption is a denominator and a system-loss figure (`main − Σ sub-meters`), not a
charge. The money the building owes is the utility's own invoice, recorded as an expense
and recovered through a cost distribution — which will never equal your own tariff
computation on your own reading, and must not be made to.

## Approving a distribution posts nothing to the ledger

The receivable is accrued when the bill is generated. Accruing at approval **as well**
would double-count the income, and because both entries balance on their own, no
ledger-balance check would catch it. There is a test pinning this; do not "fix" it.

- Draft lines are recomputed and editable. **Editing them is the feature** — it replaces
  any need for saved weighting rules, which is why `DistributionBasis` has only three
  cases.
- Approval **freezes** the lines. A flat added, resized or deactivated afterwards must not
  move money somebody has already agreed to pay.
- `CostDistributionLines` is a **pure consumer**. The moment bill generation can compute a
  split, re-running a month stops being idempotent.
- `by_consumption` refuses to compute while any active meter lacks a confirmed reading. A
  partial denominator overcharges every unit proportionally, the lines still sum to the
  total, and nothing looks wrong — it is the worst failure mode available here.
- Spreading over N months creates **N sibling distributions**, not instalment rows. One
  level of remainder arithmetic instead of two, and each month stays correctable.

## Recovery is credited to income, never back to the expense

`cost_distributions.recovery_account_id` must be an income account. `JournalService` never
inspects account types, so crediting the expense account the building was billed on would
be accepted silently and would understate expenses in every report.
`source_expense_id` is provenance only and is never read while posting.

## Corrections are adjustments, never edits

A misread meter found after billing is fixed by the next month's reading, whose difference
rides the next bill. Posted bills and journal entries are never edited — see
[[accounting-ledger]]. `MeterReadingPolicy` and `CostDistributionPolicy` both refuse to
touch anything that has reached a bill.

## Applicability changes the equal-share divisor

A charge head restricted through `charge_head_unit_type` divides an `equal_share` total
across only the units it applies to. **An empty pivot means every unit.** Each head
therefore has its own divisor and its own remainder absorber, which is why
`ChargeCalculator::$context` is keyed by head rather than by building — see
[[billing-charge-heads]]. A flat with no `unit_type_id` is excluded from a restricted head:
"not classified yet" is not "every type".

## Postgres aborts a transaction on a constraint violation

Catching a `QueryException` from a restricted foreign key leaves nothing usable to
re-render, because the surrounding transaction is already poisoned. Screens that must
refuse a delete check first and override `deleteRecord()` — see
`app/Livewire/Masters/UnitTypeList.php`.

Related: `Collection::offsetGet` **raises** on a missing key, and `??` cannot rescue it
because the error comes from inside the method. Use `->get($key)`, or convert to a plain
array first.
