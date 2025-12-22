<?php

namespace App\Services\PurchaseRequest;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseRequestNumberGenerator
{
    public function generate(?Carbon $now = null): string
    {
        $now ??= now();

        $prefix = 'PR-' . $now->format('Ym') . '-';

        return DB::transaction(function () use ($prefix) {
            // Postgres-safe: lock matching rows to avoid duplicate sequences under concurrency.
            $last = DB::table('purchase_requests')
                ->where('pr_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('pr_number')
                ->value('pr_number');

            $next = 1;
            if (is_string($last) && preg_match('/^(?:PR-\d{6})-(\d{4})$/', $last, $m)) {
                $next = ((int) $m[1]) + 1;
            }

            return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
