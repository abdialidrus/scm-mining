<?php

namespace App\Services\GoodsReceipt;

use App\Models\GoodsReceipt;
use App\Models\PutAway;
use Illuminate\Support\Facades\DB;

class GoodsReceiptPutAwaySummaryService
{
    /**
     * Returns per GR line: received_qty, posted_put_away_qty, remaining_qty.
     *
     * @return array<int,array{goods_receipt_line_id:int,received_qty:float,put_away_qty:float,remaining_qty:float}>
     */
    public function getLineSummary(int $goodsReceiptId): array
    {
        /** @var GoodsReceipt $gr */
        $gr = GoodsReceipt::query()
            ->with(['lines:id,goods_receipt_id,received_quantity'])
            ->findOrFail($goodsReceiptId);

        $postedByLine = DB::table('put_away_lines')
            ->join('put_aways', 'put_aways.id', '=', 'put_away_lines.put_away_id')
            ->where('put_aways.goods_receipt_id', $gr->id)
            ->where('put_aways.status', PutAway::STATUS_POSTED)
            ->groupBy('put_away_lines.goods_receipt_line_id')
            ->selectRaw('put_away_lines.goods_receipt_line_id as line_id, SUM(put_away_lines.qty) as qty')
            ->pluck('qty', 'line_id')
            ->all();

        $out = [];
        foreach ($gr->lines as $line) {
            $received = (float) $line->received_quantity;
            $put = (float) ($postedByLine[$line->id] ?? 0);
            $remaining = max(0.0, $received - $put);

            $out[] = [
                'goods_receipt_line_id' => (int) $line->id,
                'received_qty' => $received,
                'put_away_qty' => $put,
                'remaining_qty' => $remaining,
            ];
        }

        return $out;
    }
}
