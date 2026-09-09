# Operator Command Center, Direct Reminders & Resident Payment Queue Design

**Date:** 2026-09-10  
**Status:** Approved by User  
**Target:** Global Spotlight Search (`Cmd+K`), 1-Click WhatsApp/SMS Dues Reminder Generator, and Resident Digital Payment Submission & Verification Queue.

---

## 1. Problem Statement

While the double-entry accounting core, billing generation, and resident dashboard are functional, operators and residents still experience friction in three critical areas:
1. **Slow Lookup Friction:** Building managers handling phone calls or resident queries must navigate multiple pages (`Flats` -> search -> click flat -> click `Statement`) to find basic balances or open payment forms.
2. **Manual Dues Follow-Up:** Reminding flat owners about overdue bills is fully manual. Operators must manually calculate balances, look up phone numbers, and draft custom messages one by one.
3. **Payment Reconciliation Bottleneck:** When residents pay via bKash, Nagad, or Bank Transfer, they share receipts over phone calls or WhatsApp. The accountant must manually transcribe these details into `RecordPaymentForm`. There is no digital intimation flow to link a resident's Transaction ID directly to an approved payment.

---

## 2. Core Modules & Technical Architecture

### A. Global Spotlight Search & Command Palette (`Cmd+K`)
- **Target Component:** `App\Livewire\SpotlightSearch` & `resources/views/livewire/spotlight-search.blade.php`.
- **Inclusion:** Rendered globally in `resources/views/components/layouts/app.blade.php` with an Alpine keyboard listener (`@keydown.window.cmd.k.prevent="..."`, `@keydown.window.ctrl.k.prevent="..."`, and `@keydown.window.escape="..."`).
- **Navbar Trigger:** A quick-search button in the top navigation bar (`🔍 Search flats, owners, actions... [⌘K]`).
- **Search Capabilities:**
  - Debounced query (`wire:model.live.debounce.250ms="query"`).
  - Scoped to `CurrentBuilding` for operators.
  - Queries:
    - **Flats:** Match by flat number, floor, owner name, tenant name, contact phone.
    - Computes live balance for each matched flat using `BillSummary` / `JournalService` (`Overdue`, `Advance`, or `Paid in Full`).
    - **Quick Actions per Flat:**
      - Jump to Ledger Statement (`flats/{flat}/statement`).
      - Jump to Record Payment (`payments/create?flat_id={id}`).
      - Direct WhatsApp Dues Reminder link (`wa.me`).
    - **Navigation Shortcuts:** Instant jump to `Generate Bills`, `Owner Dues Report`, `Expenses`, `Maintenance Tickets`, `Notices`, and `Opening Balances`.

### B. 1-Click WhatsApp & SMS Dues Reminder Generator
- **Helper Service:** `App\Support\DuesReminder` (or helper methods on `BillSummary` / `Flat`).
- **Localized Messages (Bangla & English):**
  - **English:**
    > *"Dear {Owner Name}, Assalamu Alaikum. This is a gentle reminder that your service charge for {Building Name}, Flat {Flat No} has an outstanding balance of BDT {Amount}. Please clear the dues at your earliest convenience via bKash/Nagad/Bank Transfer. You can view your bill here: {Statement URL}. Thank you, Management Committee."*
  - **Bangla:**
    > *"আসসালামু আলাইকুম {মালিকের নাম}। {বিল্ডিং নাম}-এর ফ্ল্যাট {ফ্ল্যাট নম্বর}-এর সার্ভিস চার্জ বাবদ বকেয়া {পরিমাণ} টাকা পরিশোধের জন্য বিনীত অনুরোধ জানানো হচ্ছে। বিল ও স্টেটমেন্ট দেখতে ভিজিট করুন: {URL}। ধন্যবাদ, পরিচালনা কমিটি।"*
- **Integration Points:**
  - Embedded in `OwnerDues` report (`resources/views/livewire/reports/owner-dues.blade.php`).
  - Embedded in `FlatList` (`resources/views/livewire/flat-list.blade.php`).
  - Embedded in `SpotlightSearch` results.
- **Actions:**
  - Green WhatsApp button opening `https://wa.me/{phone_digits}?text={url_encoded_message}` in a new tab.
  - Clipboard copy button with toast notification for standard SMS.

### C. Resident Payment Submission & Approval Queue
- **Database Table:** `payment_submissions`
  - `id` (bigint, pk)
  - `building_id` (foreignId -> buildings)
  - `flat_id` (foreignId -> flats)
  - `user_id` (foreignId -> users, the resident who submitted)
  - `amount` (decimal 15,2)
  - `payment_method` (string: bkash, nagad, bank, cash)
  - `reference_number` (string, e.g. bKash TrxID or Bank deposit ref)
  - `payment_date` (date)
  - `slip_path` (string, nullable)
  - `resident_notes` (text, nullable)
  - `status` (string: `pending`, `approved`, `rejected`, default: `pending`)
  - `reviewed_by` (foreignId -> users, nullable)
  - `reviewed_at` (timestamp, nullable)
  - `rejection_reason` (text, nullable)
  - `payment_id` (foreignId -> payments, nullable, set upon approval)
  - `timestamps`
- **Model:** `App\Models\PaymentSubmission` with `PaymentSubmissionFactory` and `PaymentSubmissionPolicy`.
- **Resident Flow (in `App\Livewire\Dashboard`):**
  - "Submit Payment" modal button on resident dashboard.
  - Pre-fills amount with current overdue balance.
  - Upload receipt slip / screenshot (stored securely in `storage/app/private/payment_slips` or public disk).
  - Displays pending submissions table on resident dashboard with status pill (`Pending`, `Approved`, `Rejected`).
- **Operator / Accountant Flow:**
  - Dashboard counter pill on staff dashboard: `🔔 X Pending Payment Verifications`.
  - Management Screen: `App\Livewire\Billing\PaymentSubmissionList` (`/billing/submissions` or accessible from `PaymentList`).
  - **1-Click Approve:**
    - Executes double-entry payment posting via DB transaction:
      - Creates `Payment` record with method, date, amount, reference.
      - Posts balanced `JournalEntry` via `JournalService` (Debit: Cash/Bank, Credit: Service Charge Receivable with `flat_id`).
      - Auto-allocates payment to oldest unpaid bills via `PaymentAllocation`.
      - Links `payment_id` on `payment_submissions` and updates status to `approved`.
  - **1-Click Reject:**
    - Prompts for rejection reason (e.g. "TrxID not matched in statement").
    - Updates status to `rejected` with `rejection_reason`.

---

## 3. Invariants & Security Principles

1. **Strict Accounting Integrity:**
   - No financial balance is cached on `payment_submissions`.
   - Money balances are always computed from the ledger.
   - Approval of a payment submission strictly follows the same double-entry transaction posting as manual payment recording.
2. **Multi-Tenant Isolation:**
   - All submissions and searches are scoped to `CurrentBuilding`.
   - Residents can only submit and view payments for flats they own or occupy.
3. **Role Authorization:**
   - Submissions: Resident (owner, tenant) or operator.
   - Approval/Rejection: Restricted to `canManageMoney()` (Admin & Accountant).
