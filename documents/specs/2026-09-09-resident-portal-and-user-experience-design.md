# Resident Portal, Community Operations and User Experience Design

**Date:** 2026-09-09  
**Status:** Approved by User  
**Target:** Complete Resident Self-Service Portal, Digital Notice Board, Maintenance Ticketing, 1-Click Resident Onboarding, and Operator Deep-Linking.

---

## 1. Problem Statement

The system contains an accurate double-entry accounting engine and billing pipeline, but resident-facing and operator ergonomics are minimal:
1. **Resident Dead-End:** An owner logging into the system is presented with an empty dashboard containing only a plain "View My Statement" link. There is no breakdown of the current bill, no view of recent receipts, no multi-flat switcher for owners with multiple properties, and no tenant portal at all.
2. **No Communication Channel:** Building managers have no built-in way to publish announcements (water shutdowns, lift maintenance, emergency notices, AGM circulars).
3. **No Service Request / Complaint Box:** Residents have no official tracking system to report plumbing, electrical, or elevator issues and monitor resolution.
4. **Tedious Resident Onboarding:** Creating logins for owners requires manually navigating to `UserList`, creating a user, assigning the owner role, then going to `OwnerList` and linking the user dropdown. Tenants cannot have logins at all.
5. **Operator Friction:** When an operator spots an overdue balance in `FlatList` or `OwnerDues` report, there is no direct link to collect payment; they must navigate away to Billing -> Record Payment and search for the flat from scratch.

---

## 2. Core Modules & Architecture

### A. Resident Portal & Multi-Flat Dashboard
- **Target Component:** `App\Livewire\Dashboard` & `resources/views/livewire/dashboard.blade.php`.
- **Flat Resolution:**
  - If `isStaff() == false`:
    - Check if `$user->owner` exists: `$flats = $user->owner->flats()->orderBy('number')->get()`.
    - Check if `$user->tenant` exists: `$flats = collect([$user->tenant->flat])->filter()`.
    - Reactive state: `$selectedFlatId` defaults to first flat ID.
    - If `$flats->count() > 1`: Render interactive flat tabs.
- **Financial Cards (via `BillSummary`):**
  - **Total Due Payable Now:** Calculated dynamically via `BillSummary::forBill()` or `balanceFor(ServiceChargeReceivable, flat_id)`.
  - **Current Month Charges:** Extracted from latest `ServiceChargeBill`.
  - **Arrears (b/f):** Carried forward balance from prior months.
  - **Advances Available:** Held in `Advance from Owners`.
- **Latest Bill Preview:**
  - Bill number, billing month, status badge (Paid, Unpaid, Partially Paid), breakdown of line items.
  - Direct "Print Bill" button (`route('bills.print', $latestBill)`).
- **Payment History Table:**
  - List of recent payments for the selected flat with receipt number, date, amount, method, and direct "Print Receipt" button (`route('payments.receipt', $payment)`).

### B. Digital Notice Board (`Notice`)
- **Table:** `notices`
  - `id`, `building_id`, `created_by`, `title`, `content`, `type` (`general`, `maintenance`, `emergency`, `event`), `is_pinned` (bool), `published_at`, `expires_at`, `timestamps`.
- **Staff Screen:** `App\Livewire\Masters\NoticeList` (`/notices`), added to `nav.masters`.
- **Resident Integration:** Emergency and pinned notice banners on resident dashboard with a modal/drawer for full text.

### C. Maintenance & Complaint Ticketing (`MaintenanceRequest`)
- **Table:** `maintenance_requests`
  - `id`, `building_id`, `flat_id`, `user_id`, `title`, `description`, `category` (`plumbing`, `electrical`, `elevator`, `cleaning`, `security`, `other`), `priority` (`low`, `medium`, `high`, `emergency`), `status` (`open`, `in_progress`, `resolved`, `closed`), `assigned_staff_id`, `assigned_vendor_id`, `resolution_notes`, `resolved_at`, `timestamps`.
- **Resident Experience:** "Report an Issue" button on dashboard, reactive modal, and "My Requests" tab with real-time status pill badges.
- **Staff Screen:** `App\Livewire\Admin\MaintenanceRequestList` (`/maintenance-requests`), filter by status/priority, assign staff/vendor, record resolution notes.

### D. 1-Click Resident Account Creation & Tenant Logins
- **Database:** Add `user_id` foreign key to `tenants` table.
- **Models:** Add `belongsTo(User::class)` to `Tenant`, update `User` with helper methods `isResident()`, `isOwner()`, `isTenant()`.
- **UI:** Add "Create / Link Login" modal to `OwnerList` and `TenantList`. Creates user, hashes password, syncs role (`owner` or `tenant`), associates record, and presents login credentials.

### E. Operator Ergonomics & Deep-Linking
- In `FlatList`: Add "Collect Payment" button pointing to `route('payments.create', ['flat_id' => $flat->id])`.
- In `OwnerDues` report: Add "Collect" button pointing to `route('payments.create', ['flat_id' => $flat->id])`.
- In `RecordPaymentForm`: Support `mount(?int $flat_id = null)` to instantly pre-select the flat and display dues breakdown.

---

## 3. Invariants & Security
- **Data Isolation:** All notice and ticket operations are strictly scoped to `CurrentBuilding`.
- **Owner Privacy:** Owners can only access bills, payments, notices, and tickets for their own flats.
- **Financial Single Source of Truth:** Money calculations continue to use `JournalService` and `BillSummary`; no cached financial columns are added.
