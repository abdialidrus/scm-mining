<?php

namespace App\Services\PurchaseOrder;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseOrderNumberGenerator
{
    public function generate(?Carbon $now = null): string
    {
        $now ??= now();

        $prefix = 'PO-' . $now->format('Ym') . '-';

        return DB::transaction(function () use ($prefix) {
            $last = DB::table('purchase_orders')
                ->where('po_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('po_number')
                ->value('po_number');

            $next = 1;
            if (is_string($last) && preg_match('/^(?:PO-\d{6})-(\d{4})$/', $last, $m)) {
                $next = ((int) $m[1]) + 1;
            }

            return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
