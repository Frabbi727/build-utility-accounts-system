# Build Prompt — Building Management & Accounting System

> Paste this into your Claude CLI. Edit the **Stack** and **Config** lines first.

---

## Role & working method

You are a senior Laravel engineer building a production-grade **building/apartment
management system with a proper double-entry accounting core**. Before writing code:

1. Read this whole spec and restate the plan in your own words.
2. Propose the phase breakdown (MVP first — see Phases) and **wait for my confirmation**.
3. Build phase by phase. After each phase: run migrations, run tests, show me what's done.
4. Never skip the accounting invariants in the "Non-negotiables" section, even if it's faster.

Keep explanations short. Show code and file paths, not essays.

## Stack (edit if needed)

- **Backend:** Laravel 11, MySQL
- **UI:** Blade + Livewire (or Inertia+Vue — pick one and stay consistent), Tailwind
- **Auth/roles:** spatie/laravel-permission
- **Testing:** Pest
- **Language:** Bilingual UI — Bangla (default) + English, via Laravel localization

## Config

- Currency: BDT
- Service charge model: support **both** flat-rate and per-square-foot (configurable per building)
- Payment methods: Cash, Bank, bKash, Nagad

---

## Non-negotiables (accounting integrity)

These are the reason this is a "proper" system, not a cashbook. Enforce all of them:

1. **Every financial event posts a balanced journal entry** — `SUM(debit) = SUM(credit)`.
   Validate in code AND wrap the posting in a DB transaction; roll back if unbalanced.
2. **The ledger is the single source of truth for money.** Domain tables (bills, payments,
   expenses) are operational; each one *creates* a journal entry, never stores balances
   that could drift from the ledger. Link entries back with a polymorphic `source`.
3. **Accrual, not cash-only.** A service charge is posted as receivable when *due*, not
   when paid — so outstanding dues are always answerable from the ledger.
4. **One Receivable control account + a `flat_id` dimension on journal lines** for the
   per-owner sub-ledger. Do NOT create a GL account per flat.
5. **Period lock:** once a month is closed, its entries are immutable. Corrections are
   made with new adjusting entries, never edits.
6. **Full audit log:** who created/voided/paid what, and when. Voids reverse via
   contra entries, they don't delete rows.

---

## Chart of accounts (seed these)

- **Assets:** Cash in Hand, Bank, Service Charge Receivable
- **Liabilities:** Accounts Payable, Advance from Owners, Security Deposits
- **Equity/Funds:** General Fund, Sinking/Reserve Fund
- **Income:** Service Charge Income, Late Fee Income, Bank Interest, Other Income
- **Expenses:** Guard Salary, Common Electricity, Lift Maintenance, Cleaning,
  Water/WASA, Generator Fuel, Repairs & Maintenance, Admin

Make accounts hierarchical (`parent_id`) with a `code` and `type`.

## Core posting patterns (implement as a JournalService)

| Event | Debit | Credit |
|---|---|---|
| Generate monthly charge | Service Charge Receivable | Service Charge Income |
| Owner pays | Cash/Bank | Service Charge Receivable |
| Record vendor bill | (Expense account) | Accounts Payable |
| Pay vendor / salary | Accounts Payable / Expense | Cash/Bank |
| Late fee | Service Charge Receivable | Late Fee Income |
| Sinking fund contribution | General Fund (appropriation) | Sinking/Reserve Fund |

## Data model

**Accounting:** `accounts`, `journal_entries` (date, ref_no, description,
source_type, source_id), `journal_lines` (journal_entry_id, account_id, debit,
credit, flat_id nullable, memo).

**Domain:** `buildings`, `floors`, `flats`, `owners`, `tenants`, `vendors`,
`staff`, `service_charge_bills`, `bill_items`, `payments`, `payment_allocations`,
`vendor_bills`, `expenses`, `funds`.

---

## Features

### MVP (Phase 1)
- Masters: building, flats, owners, tenants, vendors, staff
- Chart of accounts + opening balances
- Service charge config (flat-rate / per-sqft)
- One-click monthly bill generation (posts accruals)
- Ad-hoc charges; auto late-fee after due date
- Record payments; auto-allocate to oldest dues; printable bilingual receipt
- Record expenses & vendor bills; pay fully/partially
- Reports: Owner dues (with aging), Collection report, Expense-by-category,
  Cash & Bank book, Trial Balance, Income & Expenditure, Balance Sheet
- Per-flat statement (dues + payment history)

### Phase 2
- Sinking/reserve fund module (contributions, withdrawals, separate balance)
- Recurring expense templates (salaries, utilities)
- Period lock + manual adjusting journal entries
- Bank reconciliation
- Payment reminders (SMS/email/in-app)
- Receipt image attachments; Excel/PDF export

### Phase 3
- Owner self-service portal (view own bills/receipts/statement only)
- Online payment gateway
- Notice board, complaint/maintenance request tracking

## Role-based UI (spatie/laravel-permission)

| Role | Access |
|---|---|
| Admin/Manager | Everything |
| Accountant | Billing, collections, expenses, journals, reports (no user/role admin) |
| Committee member | Read-only dashboards + reports |
| Owner | Own flat only — own bills, receipts, statement |

Gate every route, Livewire action, and report by role. Owners must never see
another flat's data.

## Deliverables per phase

Migrations, models with relationships, the JournalService, Livewire/controllers,
Blade views, seeders (chart of accounts + demo building), and Pest tests —
**including a test that asserts journal entries are always balanced** and that
a generated bill + its payment leave the flat's ledger at zero.

Start by confirming the plan and the Phase 1 scope.
