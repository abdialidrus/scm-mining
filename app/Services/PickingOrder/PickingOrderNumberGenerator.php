<?php

namespace App\Services\PickingOrder;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PickingOrderNumberGenerator
{
    public function generate(?Carbon $now = null): string
    {
        $now ??= now();

        $prefix = 'PK-' . $now->format('Ym') . '-';

        return DB::transaction(function () use ($prefix) {
            $last = DB::table('picking_orders')
                ->where('picking_order_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('picking_order_number')
                ->value('picking_order_number');

            $next = 1;
            if (is_string($last) && preg_match('/^(?:PK-\d{6})-(\d{4})$/', $last, $m)) {
                $next = ((int) $m[1]) + 1;
            }

            return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
