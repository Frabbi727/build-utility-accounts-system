<?php

namespace App\Enums;

enum NotificationType: string
{
    case BillGenerated = 'BILL_GENERATED';
    case PaymentApproved = 'PAYMENT_APPROVED';
    case PaymentRejected = 'PAYMENT_REJECTED';
    case MaintenanceUpdated = 'MAINTENANCE_UPDATED';
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
            self::PaymentApproved => 'Payment Approved',
            self::PaymentRejected => 'Payment Rejected',
            self::MaintenanceUpdated => 'Maintenance Updated',
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
            self::BillGenerated => 'bills',
            self::PaymentApproved, self::PaymentRejected => 'payments',
            self::MaintenanceUpdated => 'maintenance',
            self::NoticePublished => 'notices',
            self::AdminNotification, self::SystemBroadcast => 'notifications',
        };
    }
}
