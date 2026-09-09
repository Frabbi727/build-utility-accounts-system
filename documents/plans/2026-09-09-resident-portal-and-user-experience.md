# Complete Resident Portal & User Experience Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the application into an intuitive, resident-friendly management platform by introducing a comprehensive Resident Portal with multi-flat switching, a Digital Notice Board, a Maintenance & Complaint Ticketing system, 1-click resident onboarding from master lists, and operator deep-linking.

**Architecture:** The resident dashboard resolves authenticated owners and tenants to their assigned flats, rendering live balances via `BillSummary`, current bill previews, and payment receipts. A new `notices` module provides building-scoped announcements, and `maintenance_requests` enables resident issue reporting and staff dispatch. Administrative screens in `OwnerList` and `TenantList` are equipped with 1-click login provisioning.

**Tech Stack:** Laravel 13, PHP 8.5, PostgreSQL 18, Livewire 4, spatie/laravel-permission 8, Tailwind CSS, PHPUnit 12.

## Global Constraints

- Never store or cache calculated money balances in columns; always derive via `JournalService` or `BillSummary`.
- Money arithmetic must use `bcadd`, `bcsub`, `bccomp` with scale 2.
- Master and community models must be strictly scoped to `CurrentBuilding`.
- Resident role policies must prevent any resident from viewing bills, receipts, or tickets belonging to another flat.
- Maintain existing component libraries: `resources/views/components/ui/*` and `resources/views/components/form/*`.
- Format modified PHP code with `vendor/bin/pint --format agent` and ensure `composer check` passes.

---

### Task 1: Tenant User Link & Resident Role Helpers
- Create migration: `database/migrations/2026_09_09_160000_add_user_id_to_tenants_table.php`
- Modify: `app/Models/Tenant.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Masters/TenantUserLinkTest.php`

### Task 2: 1-Click Resident Account Creation in Owner & Tenant Masters
- Modify: `app/Livewire/Masters/OwnerList.php` & `resources/views/livewire/masters/owner-list.blade.php`
- Modify: `app/Livewire/Masters/TenantList.php` & `resources/views/livewire/masters/tenant-list.blade.php`
- Test: `tests/Feature/Masters/ResidentOnboardingTest.php`

### Task 3: Digital Notice Board (`Notice` Model & Management)
- Create migration: `database/migrations/2026_09_09_161000_create_notices_table.php`
- Create: `app/Models/Notice.php`, `NoticeFactory.php`, `NoticePolicy.php`
- Create: `app/Livewire/Masters/NoticeList.php` & `resources/views/livewire/masters/notice-list.blade.php`
- Modify: `routes/web.php` & `app/Support/Navigation.php`
- Test: `tests/Feature/Masters/NoticeListTest.php`

### Task 4: Maintenance & Complaint Ticketing (`MaintenanceRequest`)
- Create migration: `database/migrations/2026_09_09_162000_create_maintenance_requests_table.php`
- Create: `app/Models/MaintenanceRequest.php`, `MaintenanceRequestFactory.php`, `MaintenanceRequestPolicy.php`
- Create: `app/Livewire/Admin/MaintenanceRequestList.php` & `resources/views/livewire/admin/maintenance-request-list.blade.php`
- Modify: `routes/web.php` & `app/Support/Navigation.php`
- Test: `tests/Feature/Admin/MaintenanceRequestTest.php`

### Task 5: Resident Portal Dashboard & Interactive Features
- Modify: `app/Livewire/Dashboard.php` & `resources/views/livewire/dashboard.blade.php`
- Test: `tests/Feature/ResidentPortalTest.php`

### Task 6: Operator Ergonomics & Deep-Linking
- Modify: `app/Livewire/RecordPaymentForm.php`
- Modify: `resources/views/livewire/flat-list.blade.php` & `resources/views/livewire/reports/owner-dues.blade.php`
- Test: `tests/Feature/Billing/RecordPaymentDeepLinkTest.php`
