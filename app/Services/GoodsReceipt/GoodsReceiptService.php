<?php

namespace App\Services\GoodsReceipt;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptStatusHistory;
use App\Models\Item;
use App\Models\ItemSerialNumber;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Inventory\StockMovementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function __construct(
        private readonly GoodsReceiptNumberGenerator $numberGenerator,
        private readonly StockMovementService $stockMovementService,
    ) {}

    /**
     * @param array{
     *   purchase_order_id:int,
     *   warehouse_id:int,
     *   received_at?:string|null,
     *   remarks?:string|null,
     *   lines:array<int,array{purchase_order_line_id:int,received_quantity:numeric,remarks?:string|null}>
     * } $data
     */
    public function createDraft(User $actor, array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($actor, $data) {
            if (!$actor->hasAnyRole(['super_admin', 'warehouse'])) {
                throw new AuthorizationException('Only warehouse can create Goods Receipt.');
            }

            $poId = (int) $data['purchase_order_id'];
            $warehouseId = (int) $data['warehouse_id'];

            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()
                ->lockForUpdate()
                ->with(['supplier', 'lines.item', 'lines.uom'])
                ->findOrFail($poId);

            if (!in_array($po->status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_CLOSED], true)) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'Goods Receipt can only be created for PO with status at least APPROVED.',
                ]);
            }

            $warehouse = Warehouse::query()->findOrFail($warehouseId);
            if (!$warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Warehouse is not active.',
                ]);
            }

            $incomingLines = $data['lines'] ?? [];
            if (!is_array($incomingLines) || count($incomingLines) === 0) {
                throw ValidationException::withMessages([
                    'lines' => 'At least one receipt line is required.',
                ]);
            }

            // Precompute received-to-date per PO line (POSTED only)
            $receivedToDate = $this->receivedQtyByPoLine($po->id);

            // Validate incoming lines belong to the PO and prevent over-receipt
            $poLines = $po->lines->keyBy('id');

            foreach ($incomingLines as $i => $line) {
                $poLineId = (int) ($line['purchase_order_line_id'] ?? 0);
                $recvQty = (float) ($line['received_quantity'] ?? 0);

                if ($poLineId <= 0 || !$poLines->has($poLineId)) {
                    throw ValidationException::withMessages([
                        "lines.$i.purchase_order_line_id" => 'Invalid PO line for this purchase order.',
                    ]);
                }

                if ($recvQty <= 0) {
                    throw ValidationException::withMessages([
                        "lines.$i.received_quantity" => 'Received quantity must be > 0.',
                    ]);
                }

                /** @var PurchaseOrderLine $pol */
                $pol = $poLines->get($poLineId);

                $ordered = (float) $pol->quantity;
                $already = (float) ($receivedToDate[$poLineId] ?? 0);

                if (($already + $recvQty) - $ordered > 1e-9) {
                    throw ValidationException::withMessages([
                        "lines.$i.received_quantity" => "Over-receipt is not allowed. Ordered: {$ordered}, already received: {$already}.",
                    ]);
                }
            }

            $gr = new GoodsReceipt();
            $gr->gr_number = $this->numberGenerator->generate();
            $gr->purchase_order_id = $po->id;
            $gr->warehouse_id = $warehouse->id;
            $gr->status = GoodsReceipt::STATUS_DRAFT;
            $gr->remarks = Arr::get($data, 'remarks');
            $gr->received_at = Arr::get($data, 'received_at') ? now()->parse((string) $data['received_at']) : now();

            $gr->purchase_order_snapshot = [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'supplier' => $po->supplier_snapshot,
                'currency_code' => $po->currency_code,
                'tax_rate' => (float) $po->tax_rate,
                'totals_snapshot' => $po->totals_snapshot,
            ];

            $gr->warehouse_snapshot = [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'address' => $warehouse->address,
            ];

            $gr->save();

            $this->syncLinesFromPo($gr, $po, $incomingLines);

            $this->recordStatusHistory($gr, null, $gr->status, 'create', $actor, [
                'purchase_order_id' => $po->id,
                'po_number' => $po->po_number,
            ]);

            return $this->loadForShow($gr);
        });
    }

    /**
     * @param array{
     *   received_at?:string|null,
     *   remarks?:string|null,
     *   lines:array<int,array{purchase_order_line_id:int,received_quantity:numeric,remarks?:string|null,serial_numbers?:array<string>}>
     * } $data
     */
    public function updateDraft(User $actor, int $goodsReceiptId, array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($actor, $goodsReceiptId, $data) {
            if (!$actor->hasAnyRole(['super_admin', 'warehouse'])) {
                throw new AuthorizationException('Only warehouse can update Goods Receipt.');
            }

            /** @var GoodsReceipt $gr */
            $gr = GoodsReceipt::query()
                ->lockForUpdate()
                ->with(['purchaseOrder.lines.item'])
                ->findOrFail($goodsReceiptId);

            if ($gr->status !== GoodsReceipt::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only DRAFT GR can be updated.',
                ]);
            }

            $po = $gr->purchaseOrder;

            if (!in_array($po->status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_CLOSED], true)) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'PO is not in receivable state.',
                ]);
            }

            $incomingLines = $data['lines'] ?? [];
            if (!is_array($incomingLines) || count($incomingLines) === 0) {
                throw ValidationException::withMessages([
                    'lines' => 'At least one receipt line is required.',
                ]);
            }

            // Precompute received-to-date per PO line (POSTED only, excludes this draft)
            $receivedToDate = $this->receivedQtyByPoLine($po->id);

            // Validate incoming lines
            $poLines = $po->lines->keyBy('id');

            foreach ($incomingLines as $i => $line) {
                $poLineId = (int) ($line['purchase_order_line_id'] ?? 0);
                $recvQty = (float) ($line['received_quantity'] ?? 0);

                if ($poLineId <= 0 || !$poLines->has($poLineId)) {
                    throw ValidationException::withMessages([
                        "lines.$i.purchase_order_line_id" => 'Invalid PO line for this purchase order.',
                    ]);
                }

                if ($recvQty <= 0) {
                    throw ValidationException::withMessages([
                        "lines.$i.received_quantity" => 'Received quantity must be > 0.',
                    ]);
                }

                /** @var PurchaseOrderLine $pol */
                $pol = $poLines->get($poLineId);

                $ordered = (float) $pol->quantity;
                $already = (float) ($receivedToDate[$poLineId] ?? 0);

                if (($already + $recvQty) - $ordered > 1e-9) {
                    throw ValidationException::withMessages([
                        "lines.$i.received_quantity" => "Over-receipt is not allowed. Ordered: {$ordered}, already received: {$already}.",
                    ]);
                }

                // Validate serial numbers for serialized items
                $item = $pol->item;
                if ($item && $item->is_serialized) {
                    $serialNumbers = $line['serial_numbers'] ?? null;

                    if (!is_array($serialNumbers) || count($serialNumbers) === 0) {
                        throw ValidationException::withMessages([
                            "lines.$i.serial_numbers" => "Serial numbers are required for serialized item: {$item->name}.",
                        ]);
                    }

                    if (count($serialNumbers) != $recvQty) {
                        throw ValidationException::withMessages([
                            "lines.$i.serial_numbers" => "Number of serial numbers must match received quantity for item: {$item->name}.",
                        ]);
                    }
                }
            }

            // Update header
            if (isset($data['received_at'])) {
                $gr->received_at = now()->parse((string) $data['received_at']);
            }
            if (isset($data['remarks'])) {
                $gr->remarks = $data['remarks'];
            }
            $gr->save();

            // Update lines
            $this->syncLinesFromPo($gr, $po, $incomingLines);

            $this->recordStatusHistory($gr, $gr->status, $gr->status, 'update', $actor);

            return $this->loadForShow($gr);
        });
    }

    public function post(User $actor, int $goodsReceiptId): GoodsReceipt
    {
        return DB::transaction(function () use ($actor, $goodsReceiptId) {
            if (!$actor->hasAnyRole(['super_admin', 'warehouse'])) {
                throw new AuthorizationException('Only warehouse can post Goods Receipt.');
            }

            /** @var GoodsReceipt $gr */
            $gr = GoodsReceipt::query()
                ->lockForUpdate()
                ->with(['lines.item', 'purchaseOrder'])
                ->findOrFail($goodsReceiptId);

            if ($gr->status !== GoodsReceipt::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only DRAFT GR can be posted.',
                ]);
            }

            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()
                ->lockForUpdate()
                ->with(['lines'])
                ->findOrFail($gr->purchase_order_id);

            if (!in_array($po->status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_CLOSED], true)) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'PO is not in receivable state.',
                ]);
            }

            $receivedToDate = $this->receivedQtyByPoLine($po->id); // posted-only, excludes this GR because still DRAFT
            $poLines = $po->lines->keyBy('id');

            foreach ($gr->lines as $i => $line) {
                /** @var GoodsReceiptLine $line */
                $poLineId = (int) $line->purchase_order_line_id;
                $pol = $poLines->get($poLineId);
                if (!$pol) {
                    throw ValidationException::withMessages([
                        "lines.$i.purchase_order_line_id" => 'PO line not found (changed/removed).',
                    ]);
                }

                $ordered = (float) $pol->quantity;
                $already = (float) ($receivedToDate[$poLineId] ?? 0);
                $recv = (float) $line->received_quantity;

                if ($recv <= 0) {
                    throw ValidationException::withMessages([
                        "lines.$i.received_quantity" => 'Received quantity must be > 0.',
                    ]);
                }

                if (($already + $recv) - $ordered > 1e-9) {
                    throw ValidationException::withMessages([
                        "lines.$i.received_quantity" => "Over-receipt is not allowed. Ordered: {$ordered}, already received: {$already}.",
                    ]);
                }
            }

            // Resolve default RECEIVING location for warehouse.
            $receivingLocationId = WarehouseLocation::query()
                ->where('warehouse_id', $gr->warehouse_id)
                ->where('type', WarehouseLocation::TYPE_RECEIVING)
                ->where('is_default', true)
                ->where('is_active', true)
                ->value('id');

            if (!$receivingLocationId) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Default RECEIVING location not found for this warehouse.',
                ]);
            }

            $from = $gr->status;
            $gr->status = GoodsReceipt::STATUS_POSTED;
            $gr->posted_at = now();
            $gr->posted_by_user_id = $actor->id;
            $gr->save();

            // Create stock movements (ledger) into RECEIVING.
            foreach ($gr->lines as $line) {
                /** @var GoodsReceiptLine $line */
                $this->stockMovementService->createMovement([
                    'item_id' => (int) $line->item_id,
                    'uom_id' => $line->uom_id ? (int) $line->uom_id : null,
                    'source_location_id' => null, // inbound
                    'destination_location_id' => (int) $receivingLocationId,
                    'qty' => (float) $line->received_quantity,
                    'reference_type' => StockMovement::REF_GOODS_RECEIPT,
                    'reference_id' => (int) $gr->id,
                    'created_by' => (int) $actor->id,
                    'movement_at' => $gr->posted_at,
                    'meta' => [
                        'gr_number' => $gr->gr_number,
                        'goods_receipt_line_id' => (int) $line->id,
                        'purchase_order_id' => (int) $gr->purchase_order_id,
                        'purchase_order_line_id' => (int) $line->purchase_order_line_id,
                    ],
                ]);

                // Create serial numbers for serialized items
                $item = Item::find($line->item_id);
                if ($item && $item->is_serialized) {
                    $serialNumbers = $line->serial_numbers ?? null;

                    if (!is_array($serialNumbers) || count($serialNumbers) === 0) {
                        throw ValidationException::withMessages([
                            'serial_numbers' => "Serial numbers are required for serialized item: {$item->name} (Line {$line->line_no}). Please cancel this GR and create a new one with serial numbers.",
                        ]);
                    }

                    if (count($serialNumbers) != $line->received_quantity) {
                        throw ValidationException::withMessages([
                            'serial_numbers' => "Number of serial numbers (" . count($serialNumbers) . ") must match received quantity ({$line->received_quantity}) for item: {$item->name} (Line {$line->line_no}).",
                        ]);
                    }

                    foreach ($serialNumbers as $serialNumber) {
                        // Check for duplicate serial numbers
                        $existing = ItemSerialNumber::where('item_id', $item->id)
                            ->where('serial_number', $serialNumber)
                            ->first();

                        if ($existing) {
                            throw ValidationException::withMessages([
                                "lines.{$line->id}.serial_numbers" => "Serial number '{$serialNumber}' already exists for this item.",
                            ]);
                        }

                        ItemSerialNumber::create([
                            'item_id' => (int) $line->item_id,
                            'serial_number' => $serialNumber,
                            'status' => ItemSerialNumber::STATUS_AVAILABLE,
                            'current_location_id' => (int) $receivingLocationId,
                            'received_at' => $gr->posted_at,
                            'goods_receipt_line_id' => (int) $line->id,
                            'remarks' => "Received via GR #{$gr->gr_number}",
                            'meta' => [
                                'gr_number' => $gr->gr_number,
                                'purchase_order_id' => (int) $gr->purchase_order_id,
                            ],
                        ]);
                    }
                }
            }

            $this->recordStatusHistory($gr, $from, $gr->status, 'post', $actor);

            return $this->loadForShow($gr);
        });
    }

    public function cancel(User $actor, int $goodsReceiptId, ?string $reason = null): GoodsReceipt
    {
        return DB::transaction(function () use ($actor, $goodsReceiptId, $reason) {
            if (!$actor->hasAnyRole(['super_admin', 'warehouse'])) {
                throw new AuthorizationException('Only warehouse can cancel Goods Receipt.');
            }

            /** @var GoodsReceipt $gr */
            $gr = GoodsReceipt::query()->lockForUpdate()->findOrFail($goodsReceiptId);

            if ($gr->status === GoodsReceipt::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'GR already cancelled.',
                ]);
            }

            // MVP rule: allow cancel only while DRAFT.
            if ($gr->status !== GoodsReceipt::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only DRAFT GR can be cancelled in MVP.',
                ]);
            }

            $from = $gr->status;
            $gr->status = GoodsReceipt::STATUS_CANCELLED;
            $gr->cancelled_at = now();
            $gr->cancelled_by_user_id = $actor->id;
            $gr->cancel_reason = $reason;
            $gr->save();

            $this->recordStatusHistory($gr, $from, $gr->status, 'cancel', $actor, [
                'reason' => $reason,
            ]);

            return $this->loadForShow($gr);
        });
    }

    /** @return array<int,float> */
    private function receivedQtyByPoLine(int $purchaseOrderId): array
    {
        $rows = DB::table('goods_receipt_lines')
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_lines.goods_receipt_id')
            ->where('goods_receipts.purchase_order_id', $purchaseOrderId)
            ->where('goods_receipts.status', GoodsReceipt::STATUS_POSTED)
            ->groupBy('goods_receipt_lines.purchase_order_line_id')
            ->selectRaw('goods_receipt_lines.purchase_order_line_id as po_line_id, SUM(goods_receipt_lines.received_quantity) as qty')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->po_line_id] = (float) $r->qty;
        }

        return $map;
    }

    /**
     * @param array<int,array{purchase_order_line_id:int,received_quantity:numeric,remarks?:string|null,serial_numbers?:array<string>}> $incomingLines
     */
    private function syncLinesFromPo(GoodsReceipt $gr, PurchaseOrder $po, array $incomingLines): void
    {
        $gr->lines()->delete();

        $poLines = $po->lines->keyBy('id');
        $lineNo = 1;

        // Get RECEIVING location for the warehouse
        $receivingLocation = WarehouseLocation::query()
            ->where('warehouse_id', $gr->warehouse_id)
            ->where('type', WarehouseLocation::TYPE_RECEIVING)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        foreach ($incomingLines as $line) {
            $poLineId = (int) $line['purchase_order_line_id'];

            /** @var PurchaseOrderLine $pol */
            $pol = $poLines->get($poLineId);

            $grLine = $gr->lines()->create([
                'line_no' => $lineNo++,
                'purchase_order_line_id' => $pol->id,
                'item_id' => $pol->item_id,
                'uom_id' => $pol->uom_id,
                'ordered_quantity' => (float) $pol->quantity,
                'received_quantity' => (float) ($line['received_quantity'] ?? 0),
                'serial_numbers' => $line['serial_numbers'] ?? null, // Store serial numbers in line
                'item_snapshot' => $pol->item_snapshot,
                'uom_snapshot' => $pol->uom_snapshot,
                'remarks' => Arr::get($line, 'remarks'),
            ]);
        }
    }

    private function recordStatusHistory(
        GoodsReceipt $gr,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        ?User $actor,
        array $meta = [],
    ): void {
        GoodsReceiptStatusHistory::query()->create([
            'goods_receipt_id' => $gr->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action' => $action,
            'actor_user_id' => $actor?->id,
            'meta' => empty($meta) ? null : $meta,
            'created_at' => now(),
        ]);
    }

    private function loadForShow(GoodsReceipt $gr): GoodsReceipt
    {
        return $gr->load([
            'purchaseOrder.supplier',
            'warehouse',
            'lines.item',
            'lines.uom',
            'statusHistories.actor',
            'postedBy',
        ]);
    }
}
