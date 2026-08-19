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
  charges, applied in `GenerateMonthlyBills`.
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
