# Design Specification: Dashboard Revamp (Executive Command Center & Resident Hub)

**Date**: 2026-09-15  
**Topic**: Comprehensive Dashboard Revamp for Admin/Staff and Resident Portals  
**Status**: Pending Review  

---

## 1. Executive Summary & Problem Statement

Currently, the application dashboard presents a minimal set of 6 plain numerical cards for staff and a basic layout for residents. Key operational workflows (e.g., billing cycle status, pending payment approvals, meter readings status, maintenance urgency, and top overdue flats) are fragmented across different navigation pages. 

The goal of this revamp is to give admins, accountants, committee members, and residents a complete, clear, and actionable overview of their building's operations and financials **at a single glance ("on a peep")**.

---

## 2. Target User Personas & Core Objectives

```
+-------------------------------------------------------------------------------+
|                                DASHBOARD USERS                                |
+---------------------------------------+---------------------------------------+
|   ADMIN / STAFF / ACCOUNTANT          |   RESIDENT / OWNER / TENANT           |
|   (Executive Operations & Finance)    |   (Personal Billing & Self-Service)   |
+---------------------------------------+---------------------------------------+
| • What is our cash position today?    | • Do I owe any money right now?       |
| • How much collected vs billed month? | • When is my next bill due?           |
| • Are there payments to approve?      | • How can I submit my payment slip?   |
| • Are meter readings logged for bill? | • What is the status of my ticket?    |
| • Who are the top overdue flats?      | • Are there building announcements?   |
+---------------------------------------+---------------------------------------+
```

---

## 3. Architecture & Visual Layout Design

### 3.1 Admin / Staff Command Center Layout

```
+-------------------------------------------------------------------------------+
| HEADER: Building Switcher | Current Billing Month | Quick Action Buttons     |
+-------------------------------------------------------------------------------+
| 1. EXECUTIVE KPI CARDS (4 Cards)                                              |
|  [ Monthly Collections ] [ Outstanding Dues ] [ Liquid Funds ] [ Net Cashflow]|
|  (৳ Collected / Billed)  (৳ Overdue / Flats)  (Cash + Bank)   (In vs Out)     |
+-------------------------------------------------------------------------------+
| 2. OPERATIONAL PULSE & ACTION QUEUE (4 Interactive Status Cards)             |
|  [ 🔔 Pending Payments ] [ ⚡ Meter Readings ] [ 📑 Bill Gen ] [ 🛠️ Tickets ] |
|  (X to verify -> Link)   (X/Y meters logged)  (Status: Done)  (X High/Urgent) |
+-------------------------------------------------------------------------------+
| 3. ANALYTICS & DEFAULTERS ROW (2 Columns)                                    |
|  LEFT (60%):                                | RIGHT (40%):                    |
|  • Monthly Income vs Expense Progress Bar   | • Top Overdue / Defaulter Watch |
|  • Billing Compliance (Paid / Unpaid / Part)| • Instant Flat Statement Link   |
+-------------------------------------------------------------------------------+
| 4. RECENT ACTIVITY & COMMUNITY FEED                                           |
|  • Latest 5 Confirmed Payments              | • Latest Maintenance Requests   |
+-------------------------------------------------------------------------------+
```

#### Detailed Breakdown of Staff Components:
1. **Executive Financial Cards**:
   - **Monthly Collections**: Amount collected this month, target billed amount, with a progress bar and collection percentage (`X% collected`).
   - **Total Outstanding Dues**: Total receivables across the building, with count of flats having overdue balances.
   - **Liquid Funds**: Total available liquid funds (`Cash in Hand + Bank`), with individual sub-totals.
   - **Net Monthly Cashflow**: Total Income recorded this month vs Total Expenses, showing net positive/negative cashflow badge.

2. **Operational Pulse & Action Pipeline**:
   - **Pending Payment Submissions**: Amber alert card displaying pending count with 1-click link to approval queue.
   - **Meter Reading Progress**: Shows how many active meters have readings logged for the current month vs pending.
   - **Monthly Bill Generation Status**: Indicates whether the current month's bills have been generated for all active flats.
   - **Active Maintenance Tickets**: Count of open & in-progress requests, highlighting any High/Emergency priority items.

3. **Analytics & Defaulter Watchlist**:
   - **Billing & Occupancy Breakdown**: Visual status badges showing count and % of Paid, Partially Paid, and Unpaid flats for the month.
   - **Top Overdue Watchlist**: Clean, high-contrast table of flats with the largest dues, showing Owner name, flat number, overdue amount, and a direct button to view their full ledger/statement.

4. **Quick Action Hub**:
   - Prominent header shortcuts: `+ Generate Bills`, `+ Record Payment`, `+ Record Expense`, `+ Enter Readings`, `+ New Notice`.

5. **Recent Activity Feed**:
   - Live stream of recent payments received (with receipt links) and recent maintenance requests filed.

---

### 3.2 Resident / Owner / Tenant Portal Layout

```
+-------------------------------------------------------------------------------+
| HEADER: Welcome greeting | Building Name & Flat Number | Flat Selector (if >1)|
+-------------------------------------------------------------------------------+
| 1. GLANCEABLE FINANCIAL HEALTH HERO BANNER                                    |
|   Status: [ PAID / NO DUES ] or [ ৳3,500 DUE BY 15TH OCT ]                    |
|   Breakdown: [ Arrears: ৳0 ] | [ Current Bill: ৳3,500 ] | [ Advance: ৳0 ]     |
|   Action: [ 💳 Submit Payment Proof (bKash / Nagad / Bank) ]                  |
+-------------------------------------------------------------------------------+
| 2. TWO-COLUMN INTERACTIVE CONTENT AREA                                        |
|  LEFT (65%):                                | RIGHT (35%):                    |
|  • Latest Bill Itemized Breakdown Card      | • Notice Board (Emergency & Gen)|
|  • Recent Payment History (Print Receipt)   | • My Maintenance Tickets Tracker|
|  • My Payment Submissions (Live Status)     |   (Report Issue Modal Trigger)  |
+-------------------------------------------------------------------------------+
```

#### Detailed Breakdown of Resident Components:
1. **Financial Hero Banner**:
   - High-contrast visual card indicating whether the resident is up-to-date or has outstanding dues.
   - 3-metric sub-breakdown: Arrears (past unpaid), Current Month Charges, and Advance balance held in their account.
   - Direct button to open the "Submit Payment" modal.

2. **Latest Bill & Breakdown**:
   - Shows bill number, month, and itemized lines (e.g. Service Charge, Utilities, Ad-hoc).
   - Direct link to print/download official bill PDF.

3. **Payment Submissions & Receipts**:
   - Track verification status of submitted payments (`Pending`, `Approved`, `Rejected` with reason tooltip).
   - Instant 1-click access to download official receipts for confirmed payments.

4. **Notice Board & Tickets**:
   - Pinned emergency announcements in prominent red/amber banners.
   - Real-time maintenance ticket status badges (`Open`, `In Progress`, `Resolved`, `Closed`).

---

## 4. Data Layer & Query Optimization

To maintain sub-100ms response times and prevent N+1 queries:
- **Ledger Aggregations**: Use existing `JournalService` and `LedgerReports` for ledger-verified sums.
- **Monthly Scopes**: Pre-filter `ServiceChargeBill`, `Payment`, and `Expense` queries within the active month window using indexed timestamps (`billing_month`, `received_on`, `expense_date`).
- **Building Context**: Enforce `building_id` scoping via `CurrentBuilding` singleton.
- **Eager Loading**: Eager-load `flat.owner`, `flat.building`, and `items` relations for bill previews and ticket lists.

---

## 5. Implementation & Verification Plan

1. **Backend Controller / Livewire (`app/Livewire/Dashboard.php`)**:
   - Add monthly collection & expense calculation logic.
   - Compute meter reading completion percentage for the current period.
   - Retrieve top 5 overdue flats for the active building.
   - Calculate monthly billing generation progress.

2. **Frontend View (`resources/views/livewire/dashboard.blade.php`)**:
   - Restructure Staff dashboard with modern Tailwind CSS card grid, progress bars, quick action bar, and pulse indicators.
   - Refine Resident portal with high-visibility hero banner, itemized bill preview, and status pill badges.

3. **Automated & Manual Verification**:
   - Run existing feature and unit test suite (`php artisan test`).
   - Add dedicated dashboard test cases for staff metrics and resident calculations.
   - Verify zero layout regressions in both English and Bengali locales.
