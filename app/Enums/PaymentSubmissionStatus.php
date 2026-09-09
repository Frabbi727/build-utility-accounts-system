<?php

namespace App\Enums;

enum PaymentSubmissionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('billing.pending'),
            self::Approved => __('billing.approved'),
            self::Rejected => __('billing.rejected'),
        };
    }
}
