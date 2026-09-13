<?php

namespace App\Enums;

enum NotificationType: string
{
    case BillGenerated = 'BILL_GENERATED';
    case BillDueReminder = 'BILL_DUE_REMINDER';
    case BillOverdue = 'BILL_OVERDUE';
    case PaymentApproved = 'PAYMENT_APPROVED';
    case PaymentRejected = 'PAYMENT_REJECTED';
    case MaintenanceCreated = 'MAINTENANCE_CREATED';
    case MaintenanceUpdated = 'MAINTENANCE_UPDATED';
    case MaintenanceAssigned = 'MAINTENANCE_ASSIGNED';
    case NoticePublished = 'NOTICE_PUBLISHED';
    case AdminNotification = 'ADMIN_NOTIFICATION';
    case SystemBroadcast = 'SYSTEM_BROADCAST';

    /**
     * Human-readable label for display in admin screens.
     */
    public function label(): string
    {
        return match ($this) {
            self::BillGenerated => 'Bill Generated',
            self::BillDueReminder => 'Bill Due Reminder',
            self::BillOverdue => 'Bill Overdue',
            self::PaymentApproved => 'Payment Approved',
            self::PaymentRejected => 'Payment Rejected',
            self::MaintenanceCreated => 'Maintenance Created',
            self::MaintenanceUpdated => 'Maintenance Updated',
            self::MaintenanceAssigned => 'Maintenance Assigned',
            self::NoticePublished => 'Notice Published',
            self::AdminNotification => 'Admin Notification',
            self::SystemBroadcast => 'System Broadcast',
        };
    }

    /**
     * The Flutter screen route name for deep-linking.
     */
    public function screen(): string
    {
        return match ($this) {
            self::BillGenerated, self::BillDueReminder, self::BillOverdue => 'bills',
            self::PaymentApproved, self::PaymentRejected => 'payments',
            self::MaintenanceCreated, self::MaintenanceUpdated, self::MaintenanceAssigned => 'maintenance',
            self::NoticePublished => 'notices',
            self::AdminNotification, self::SystemBroadcast => 'notifications',
        };
    }
}
