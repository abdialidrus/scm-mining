<?php

namespace App\Enums\Accounting;

enum PaymentStatus: string
{
    case UNPAID = 'UNPAID';
    case PARTIAL_PAID = 'PARTIAL_PAID';
    case PAID = 'PAID';
    case OVERDUE = 'OVERDUE';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Unpaid',
            self::PARTIAL_PAID => 'Partially Paid',
            self::PAID => 'Paid',
            self::OVERDUE => 'Overdue',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::UNPAID => 'yellow',
            self::PARTIAL_PAID => 'blue',
            self::PAID => 'green',
            self::OVERDUE => 'red',
        };
    }
}
