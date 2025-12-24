<?php

namespace App\Services\PutAway;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PutAwayNumberGenerator
{
    public function generate(?Carbon $now = null): string
    {
        $now ??= now();

        $prefix = 'PA-' . $now->format('Ym') . '-';

        return DB::transaction(function () use ($prefix) {
            $last = DB::table('put_aways')
                ->where('put_away_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('put_away_number')
                ->value('put_away_number');

            $next = 1;
            if (is_string($last) && preg_match('/^(?:PA-\d{6})-(\d{4})$/', $last, $m)) {
                $next = ((int) $m[1]) + 1;
            }

            return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
