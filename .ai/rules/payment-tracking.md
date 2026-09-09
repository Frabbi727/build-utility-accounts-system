# Payment Tracking and Bill Presentation Invariants

## One place computes what is owed
- `App\Services\Billing\BillSummary` is the **only** place "what is owed on a bill" is computed. It returns a readonly `App\Support\BillSummaryData`.
- All display surfaces (single printable bill, batch printable bills, flat statement cards, dues reports, collect-due prefill forms) must consume `BillSummary::for($bill)` rather than calculating dues, paid allocations, or outstanding amounts inline.

## Carry-forward is display-only
- Arrears are **never** re-charged onto a new bill (e.g. by creating a new `BillItem` or line for previous dues). Re-charging arrears destroys aging and risks double-posting the ledger receivable.
- The printable bill and statements calculate previous dues (brought forward) dynamically:
  `broughtForward = totalDue - thisBillOutstanding`
- `totalDue` is the flat's ledger outstanding balance for `ServiceChargeReceivable`.
- `thisBillOutstanding` is the current bill's total minus allocations against it.
- This subtraction approach guarantees `broughtForward + thisBillOutstanding == totalDue` by construction, reconciling with the Trial Balance and ledger exactly.

## Payments and Allocations
- Payments must be recorded via `RecordPayment::handle()`, which allocates them oldest-bill-first via `AllocationPlanner`.
- Excess cash is credited to the `Advance from Owners` account and automatically drawn down against newly generated bills via `ApplyAdvances`.
- A payment allocation row in `payment_allocations` is unique per `(payment_id, service_charge_bill_id)`. When paying into a bill that already has allocations, update the existing row's amount instead of inserting a new row.

## Authorization and Print Routes
- `bills.print` (`GET bills/{bill}/print`) allows owners to print only their own flat's bill via `ServiceChargeBillPolicy::view()`, while staff (Admin, Accountant, Committee) can print any bill.
- `bills.print-month` (`GET billing/bills/print?month=YYYY-MM`) is restricted to staff roles (`admin|accountant|committee`) and scopes print outputs to the active building `CurrentBuilding::get()`.
