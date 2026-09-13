# Automated Reminders & Advanced Notification Management Specification

## 1. Overview & Objectives
This document specifies the architecture and implementation for an **Automated Reminders & Advanced Notification Management System** in the Utility Accounts application.

The system delivers:
1. **Configurable Automated Bill Reminders**: Scheduled notifications for upcoming due dates, due date day, and overdue bills.
2. **Real-time Maintenance Lifecycle Notifications**: Instant push notifications to residents and staff for ticket updates, assignments, and resolution notes.
3. **Admin Notification Management UI**: An administrative interface to manage automation rules, dynamic message templates, trigger on-demand reminder dispatches with dry-run previews, and monitor delivery history.

---

## 2. Architecture & Data Flow

```
+-------------------------------------------------------------------------------+
|                             Laravel Scheduler                                 |
|               (Daily at 08:00 AM: notifications:send-bill-reminders)          |
+---------------------------------------+---------------------------------------+
                                        |
                                        v
+-------------------------------------------------------------------------------+
|                       SendBillRemindersCommand                                |
| 1. Reads active notification_rules for bill events                            |
| 2. Queries unpaid / partial ServiceChargeBill matching days_offset            |
| 3. Resolves resident User IDs (Owner & Tenants) via Flat                      |
| 4. Generates idempotent key: bill_reminder:{bill_id}:{rule_id}:{date}         |
| 5. Substitutes dynamic template placeholders                                  |
+---------------------------------------+---------------------------------------+
                                        |
                                        v
+-------------------------------------------------------------------------------+
|                       NotificationService                                     |
| 1. Persists Notification records with 'pending' status                        |
| 2. Dispatches SendPushNotificationJob (afterCommit)                           |
+---------------------------------------+---------------------------------------+
                                        |
                                        v
+-------------------------------------------------------------------------------+
|                   Push Delivery & Mobile In-App Center                        |
| - Firebase Cloud Messaging (FCM) to iOS/Android devices                       |
| - In-App Notification Center API (/api/v1/notifications)                      |
+-------------------------------------------------------------------------------+
```

---

## 3. Database Schema

### 3.1 `notification_rules` Table
| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | `bigint unsigned` | No | Primary Key |
| `building_id` | `bigint unsigned` | Yes | Foreign key to `buildings.id`. Null = organization default |
| `trigger_event` | `string(50)` | No | Enum: `bill_due_upcoming`, `bill_due_today`, `bill_overdue`, `maintenance_status_changed`, `maintenance_assigned`, `maintenance_created` |
| `days_offset` | `integer` | No | Offset from due date (e.g., `-3` = 3 days before, `0` = on due date, `2` = 2 days overdue). 0 for event-based triggers. |
| `title_template` | `string(255)` | No | Notification title template with placeholder tokens |
| `body_template` | `text` | No | Notification body template with placeholder tokens |
| `is_active` | `boolean` | No | Whether this rule is enabled (default `true`) |
| `channels` | `jsonb` | No | Delivery channels (default `["push", "in_app"]`) |
| `created_at` / `updated_at` | `timestamp` | No | Laravel timestamps |

### 3.2 Enum Updates
* `App\Enums\NotificationType`:
  * `BillGenerated = 'BILL_GENERATED'`
  * `BillDueReminder = 'BILL_DUE_REMINDER'`
  * `BillOverdue = 'BILL_OVERDUE'`
  * `PaymentApproved = 'PAYMENT_APPROVED'`
  * `PaymentRejected = 'PAYMENT_REJECTED'`
  * `MaintenanceCreated = 'MAINTENANCE_CREATED'`
  * `MaintenanceUpdated = 'MAINTENANCE_UPDATED'`
  * `MaintenanceAssigned = 'MAINTENANCE_ASSIGNED'`
  * `NoticePublished = 'NOTICE_PUBLISHED'`
  * `AdminNotification = 'ADMIN_NOTIFICATION'`
  * `SystemBroadcast = 'SYSTEM_BROADCAST'`

* `App\Enums\NotificationTriggerEvent`:
  * `BillDueUpcoming = 'bill_due_upcoming'`
  * `BillDueToday = 'bill_due_today'`
  * `BillOverdue = 'bill_overdue'`
  * `MaintenanceStatusChanged = 'maintenance_status_changed'`
  * `MaintenanceAssigned = 'maintenance_assigned'`
  * `MaintenanceCreated = 'maintenance_created'`

---

## 4. Automation Rules & Dynamic Placeholders

### 4.1 Default Seeded Rules
1. **Bill Due in 3 Days** (`bill_due_upcoming`, `days_offset = -3`):
   * Title: `Payment Reminder: Bill for Flat {flat_number}`
   * Body: `Hello {resident_name}, your utility bill for {billing_month} of ৳{amount} is due on {due_date}. Please pay to avoid late fees.`
2. **Bill Due Today** (`bill_due_today`, `days_offset = 0`):
   * Title: `Bill Due Today: Flat {flat_number}`
   * Body: `Hello {resident_name}, your utility bill of ৳{amount} is due today ({due_date}). Tap to make your payment.`
3. **Bill Overdue** (`bill_overdue`, `days_offset = 2`):
   * Title: `Overdue Payment Notice: Flat {flat_number}`
   * Body: `Attention {resident_name}: Your utility bill of ৳{amount} is overdue since {due_date}. Please settle promptly to prevent late fees.`
4. **Maintenance Ticket Updated** (`maintenance_status_changed`):
   * Title: `Maintenance Ticket Updated: #{ticket_id}`
   * Body: `Your ticket "{ticket_title}" status changed to {ticket_status}. {resolution_notes}`
5. **Technician Assigned** (`maintenance_assigned`):
   * Title: `Technician Assigned: #{ticket_id}`
   * Body: `Technician {assigned_to} has been assigned to your ticket "{ticket_title}".`

### 4.2 Template Token Engine
A dedicated `TemplateParser` utility replaces tokens dynamically:
* `{resident_name}` $\rightarrow$ Recipient user's name
* `{flat_number}` $\rightarrow$ Flat number (e.g. "4B")
* `{amount}` $\rightarrow$ Formatted bill total amount
* `{due_date}` $\rightarrow$ Formatted due date (e.g. "15 Sep 2026")
* `{billing_month}` $\rightarrow$ Month name (e.g. "September 2026")
* `{ticket_id}` $\rightarrow$ Maintenance ticket ID
* `{ticket_title}` $\rightarrow$ Maintenance request title
* `{ticket_status}` $\rightarrow$ Formatted status label (e.g. "In Progress", "Resolved")
* `{assigned_to}` $\rightarrow$ Staff or Vendor name
* `{resolution_notes}` $\rightarrow$ Notes added by admin or technician

---

## 5. Maintenance Lifecycle Triggers

A dedicated service / listener `MaintenanceNotificationService` will be invoked whenever:
1. A maintenance ticket status is changed (`MaintenanceRequestList` or API).
2. A staff member or vendor is assigned or updated.
3. Resolution notes are added.
4. A resident submits a new ticket (notifying building staff/admins).

Each notification will include deep-linking payloads:
```json
{
  "screen": "maintenance",
  "ticket_id": 123,
  "type": "ticket_updated"
}
```

---

## 6. Admin Notification Management UI (`/admin/notifications`)

The Livewire component `App\Livewire\Admin\NotificationList` will be enhanced into a full management hub with 3 main sections:

1. **Automation Rules & Template Editor**:
   * View all active rules with toggle switches.
   * Edit days offset and title/body templates.
   * Quick-tag inserters for dynamic placeholder chips.
   * Live template preview with sample context.
2. **Notification History & Logs**:
   * Real-time search, filter by type, status (`pending`, `sent`, `failed`), and read state (`unread`, `read`).
   * Detail modal showing FCM payload, recipient, and delivery timestamps.
3. **On-Demand Dispatcher & Broadcast**:
   * "Run Reminders Now" button with modal showing preview count of eligible bills before dispatching.
   * Targeted manual push broadcast to specific groups (All Residents, Owners, Tenants, Staff, or Individual).

---

## 7. Scheduled Commands & Console Setup

In `routes/console.php`:
```php
/**
 * Automated Bill Due Date Reminders run daily at 08:00 AM.
 */
Schedule::command('notifications:send-bill-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping();
```

The command supports:
* `--dry-run`: Reports which bills and users would be notified without sending messages.
* `--building=ID`: Limits reminder dispatch to a specific building.

---

## 8. Reliability & Idempotency Guarantee
* Every scheduled notification calculates a unique `notification_key`:
  `bill_reminder:{bill_id}:{rule_id}:{Y-m-d}`
* The unique index on `notification_key` prevents double-dispatch even if the command is executed multiple times or retried on the same day.

---

## 9. Verification & Testing Strategy
* **`SendBillRemindersCommandTest`**:
  * Tests that upcoming, due today, and overdue bills trigger appropriate notifications.
  * Tests that paid bills are ignored.
  * Tests idempotency (running twice does not create duplicate notifications).
  * Tests template token replacement with real flat/user data.
* **`MaintenanceNotificationTest`**:
  * Tests that status updates trigger push jobs with correct payload and deep links.
  * Tests that technician assignments trigger appropriate notifications.
* **`NotificationRulesManagementTest`**:
  * Tests admin ability to toggle rules, update templates, and test preview.
