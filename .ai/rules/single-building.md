# Single-building by decision: journal lines carry no building dimension

`journal_lines` has a `flat_id` dimension and **no `building_id`**. This is a deliberate,
confirmed decision, not an oversight: this deployment manages one building's finances.

Consequences to respect rather than work around:

- **Financial reports are org-wide.** Expenses and vendor bills have no building to filter
  on at the ledger level, so `App\Services\Reporting\LedgerReports` and the report screens
  do not scope by building. That is correct under this decision.
- `App\Support\CurrentBuilding` scopes **operational** screens only — flats, floors, charge
  heads, staff, bill generation, dashboard counts. Do not extend it to financial reports.
- `expenses.building_id` and `vendor_bills.building_id` exist and are nullable. They are
  operational metadata, not a reporting dimension; do not build per-building financial
  reporting on them.

If the product ever needs genuinely separate books per building, that is a migration
adding `building_id` to `journal_lines`, a backfill, and a rewrite of every report — a
deliberate project, not an incremental change. Raise it rather than half-implementing it.
