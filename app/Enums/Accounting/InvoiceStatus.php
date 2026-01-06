<?php

namespace App\Enums\Accounting;

enum InvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case MATCHED = 'MATCHED';
    case VARIANCE = 'VARIANCE';
    case APPROVED = 'APPROVED';
    case PAID = 'PAID';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::MATCHED => 'Matched',
            self::VARIANCE => 'Variance Detected',
            self::APPROVED => 'Approved',
            self::PAID => 'Paid',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'blue',
            self::MATCHED => 'green',
            self::VARIANCE => 'yellow',
            self::APPROVED => 'emerald',
            self::PAID => 'green',
            self::REJECTED => 'red',
            self::CANCELLED => 'gray',
        };
    }
}
