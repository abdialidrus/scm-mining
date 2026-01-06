<?php

namespace App\Enums\Accounting;

enum MatchingStatus: string
{
    case PENDING = 'PENDING';
    case MATCHED = 'MATCHED';
    case PARTIAL_MATCH = 'PARTIAL_MATCH';
    case MISMATCHED = 'MISMATCHED';
    case QTY_VARIANCE = 'QTY_VARIANCE';
    case PRICE_VARIANCE = 'PRICE_VARIANCE';
    case BOTH_VARIANCE = 'BOTH_VARIANCE';
    case OVER_INVOICED = 'OVER_INVOICED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::MATCHED => 'Matched',
            self::PARTIAL_MATCH => 'Partial Match',
            self::MISMATCHED => 'Mismatched',
            self::QTY_VARIANCE => 'Quantity Variance',
            self::PRICE_VARIANCE => 'Price Variance',
            self::BOTH_VARIANCE => 'Qty & Price Variance',
            self::OVER_INVOICED => 'Over Invoiced',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::MATCHED => 'green',
            self::PARTIAL_MATCH => 'yellow',
            self::MISMATCHED => 'red',
            self::QTY_VARIANCE => 'yellow',
            self::PRICE_VARIANCE => 'yellow',
            self::BOTH_VARIANCE => 'orange',
            self::OVER_INVOICED => 'red',
        };
    }
}
