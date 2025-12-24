<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class StockQueryService
{
    /**
     * Get on-hand quantity for an item at a single location.
     * Calculated on-the-fly from stock_movements ledger.
     */
    public function getOnHandForLocation(int $locationId, int $itemId, ?int $uomId = null): float
    {
        $in = DB::table('stock_movements')
            ->where('destination_location_id', $locationId)
            ->where('item_id', $itemId);

        $out = DB::table('stock_movements')
            ->where('source_location_id', $locationId)
            ->where('item_id', $itemId);

        if ($uomId !== null) {
            $in->where('uom_id', $uomId);
            $out->where('uom_id', $uomId);
        }

        $inQty = (float) ($in->sum('qty') ?? 0);
        $outQty = (float) ($out->sum('qty') ?? 0);

        return $inQty - $outQty;
    }

    /**
     * Get on-hand quantities for an item across all locations.
     *
     * @return array<int,float> [location_id => qty_on_hand]
     */
    public function getOnHandByLocationForItem(int $itemId, ?int $uomId = null): array
    {
        $in = DB::table('stock_movements')
            ->selectRaw('destination_location_id as location_id, SUM(qty) as qty')
            ->whereNotNull('destination_location_id')
            ->where('item_id', $itemId)
            ->groupBy('destination_location_id');

        $out = DB::table('stock_movements')
            ->selectRaw('source_location_id as location_id, SUM(qty) as qty')
            ->whereNotNull('source_location_id')
            ->where('item_id', $itemId)
            ->groupBy('source_location_id');

        if ($uomId !== null) {
            $in->where('uom_id', $uomId);
            $out->where('uom_id', $uomId);
        }

        $inRows = $in->get();
        $outRows = $out->get();

        $map = [];
        foreach ($inRows as $r) {
            $map[(int) $r->location_id] = (float) $r->qty;
        }
        foreach ($outRows as $r) {
            $loc = (int) $r->location_id;
            $map[$loc] = ($map[$loc] ?? 0) - (float) $r->qty;
        }

        // Remove null/empty keys (defensive)
        unset($map[0]);

        return $map;
    }

    /**
     * Calculate total on-hand across all locations.
     */
    public function getTotalOnHandForItem(int $itemId, ?int $uomId = null): float
    {
        $byLoc = $this->getOnHandByLocationForItem($itemId, $uomId);
        return array_sum($byLoc);
    }
}
