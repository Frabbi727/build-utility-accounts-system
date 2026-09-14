<?php

namespace App\Enums;

enum NotificationTriggerEvent: string
{
    case BillDueUpcoming = 'bill_due_upcoming';
    case BillDueToday = 'bill_due_today';
    case BillOverdue = 'bill_overdue';
    case PaymentReceived = 'payment_received';
    case PaymentReversed = 'payment_reversed';
    case MaintenanceStatusChanged = 'maintenance_status_changed';
    case MaintenanceAssigned = 'maintenance_assigned';
    case MaintenanceCreated = 'maintenance_created';

    public function label(): string
    {
        return match ($this) {
            self::BillDueUpcoming => 'Bill Due Upcoming',
            self::BillDueToday => 'Bill Due Today',
            self::BillOverdue => 'Bill Overdue',
            self::PaymentReceived => 'Payment Received',
            self::PaymentReversed => 'Payment Reversed',
            self::MaintenanceStatusChanged => 'Maintenance Status Changed',
            self::MaintenanceAssigned => 'Maintenance Assigned',
            self::MaintenanceCreated => 'Maintenance Created',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BillDueUpcoming => 'Reminder sent before the bill due date',
            self::BillDueToday => 'Reminder sent on the bill due date',
            self::BillOverdue => 'Notice sent after the bill due date passes',
            self::PaymentReceived => 'Receipt notice sent when a payment is recorded',
            self::PaymentReversed => 'Notice sent when a payment is reversed or bounced',
            self::MaintenanceStatusChanged => 'Alert sent when a maintenance request status is updated',
            self::MaintenanceAssigned => 'Alert sent when a technician or staff member is assigned',
            self::MaintenanceCreated => 'Alert sent when a new maintenance request is submitted',
        };
    }

    /**
     * @return list<string>
     */
    public function supportedTokens(): array
    {
        return match ($this) {
            self::BillDueUpcoming, self::BillDueToday, self::BillOverdue => [
                '{resident_name}',
                '{flat_number}',
                '{amount}',
                '{due_date}',
                '{billing_month}',
            ],
            self::PaymentReceived, self::PaymentReversed => [
                '{resident_name}',
                '{flat_number}',
                '{amount}',
                '{receipt_no}',
                '{received_on}',
                '{reason}',
            ],
            self::MaintenanceStatusChanged, self::MaintenanceAssigned, self::MaintenanceCreated => [
                '{resident_name}',
                '{flat_number}',
                '{ticket_id}',
                '{ticket_title}',
                '{ticket_status}',
                '{assigned_to}',
                '{resolution_notes}',
            ],
        };
    }

    public function defaultDaysOffset(): int
    {
        return match ($this) {
            self::BillDueUpcoming => -3,
            self::BillDueToday => 0,
            self::BillOverdue => 2,
            self::PaymentReceived, self::PaymentReversed => 0,
            self::MaintenanceStatusChanged, self::MaintenanceAssigned, self::MaintenanceCreated => 0,
        };
    }

    public function isBillEvent(): bool
    {
        return in_array($this, [self::BillDueUpcoming, self::BillDueToday, self::BillOverdue], true);
    }

    public function isPaymentEvent(): bool
    {
        return in_array($this, [self::PaymentReceived, self::PaymentReversed], true);
    }

    public function isMaintenanceEvent(): bool
    {
        return in_array($this, [self::MaintenanceStatusChanged, self::MaintenanceAssigned, self::MaintenanceCreated], true);
    }
}
