<?php

namespace App\Services\GoodsReceipt;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GoodsReceiptNumberGenerator
{
    public function generate(?Carbon $now = null): string
    {
        $now ??= now();

        $prefix = 'GR-' . $now->format('Ym') . '-';

        return DB::transaction(function () use ($prefix) {
            $last = DB::table('goods_receipts')
                ->where('gr_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('gr_number')
                ->value('gr_number');

            $next = 1;
            if (is_string($last) && preg_match('/^(?:GR-\d{6})-(\d{4})$/', $last, $m)) {
                $next = ((int) $m[1]) + 1;
            }

            return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
