<?php

namespace App\Observers;

use App\Models\StockMovement;
use App\Models\StockBalance;
use Illuminate\Support\Facades\DB;

class StockMovementObserver
{
    /**
     * Handle the StockMovement "created" event.
     */
    public function created(StockMovement $movement): void
    {
        // Update destination location balance (increase)
        if ($movement->destination_location_id) {
            $this->updateBalance(
                $movement->destination_location_id,
                $movement->item_id,
                $movement->uom_id,
                $movement->qty
            );
        }

        // Update source location balance (decrease)
        if ($movement->source_location_id) {
            $this->updateBalance(
                $movement->source_location_id,
                $movement->item_id,
                $movement->uom_id,
                -$movement->qty
            );
        }
    }

    /**
     * Handle the StockMovement "deleted" event.
     * Reverse the movement
     */
    public function deleted(StockMovement $movement): void
    {
        // Reverse destination location balance (decrease)
        if ($movement->destination_location_id) {
            $this->updateBalance(
                $movement->destination_location_id,
                $movement->item_id,
                $movement->uom_id,
                -$movement->qty
            );
        }

        // Reverse source location balance (increase)
        if ($movement->source_location_id) {
            $this->updateBalance(
                $movement->source_location_id,
                $movement->item_id,
                $movement->uom_id,
                $movement->qty
            );
        }
    }

    /**
     * Update stock balance for a location/item/uom combination
     */
    private function updateBalance(int $locationId, int $itemId, ?int $uomId, float $qtyChange): void
    {
        $balance = StockBalance::firstOrNew([
            'location_id' => $locationId,
            'item_id' => $itemId,
            'uom_id' => $uomId,
        ]);

        $balance->qty_on_hand = ($balance->qty_on_hand ?? 0) + $qtyChange;
        $balance->as_of_at = now();
        $balance->save();

        // Delete balance record if qty reaches zero
        if ($balance->qty_on_hand == 0) {
            $balance->delete();
        }
    }
}
