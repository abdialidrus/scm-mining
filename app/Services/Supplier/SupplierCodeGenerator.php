<?php

namespace App\Services\Supplier;

use Illuminate\Support\Facades\DB;

class SupplierCodeGenerator
{
    /**
     * Generates a supplier code like: SUP-YYYYMM-XXXX
     */
    public function generate(): string
    {
        return DB::transaction(function () {
            $ym = now()->format('Ym');

            $lastCode = DB::table('suppliers')
                ->where('code', 'like', "SUP-{$ym}-%")
                ->orderByDesc('id')
                ->value('code');

            $next = 1;
            if (is_string($lastCode) && preg_match('/^SUP-' . preg_quote($ym, '/') . '-(\d{4})$/', $lastCode, $m) === 1) {
                $next = ((int) $m[1]) + 1;
            }

            return sprintf('SUP-%s-%04d', $ym, $next);
        });
    }
}
