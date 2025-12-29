<?php

namespace App\Services\PickingOrder;

use App\Models\PickingOrder;
use App\Models\PickingOrderLine;
use App\Models\PickingOrderStatusHistory;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Inventory\StockMovementService;
use App\Services\Inventory\StockQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PickingOrderService
{
    public function __construct(
        private readonly PickingOrderNumberGenerator $numberGenerator,
        private readonly StockMovementService $stockMovementService,
        private readonly StockQueryService $stockQueryService,
    ) {}

    /**
     * @param array{
     *   warehouse_id:int,
     *   department_id?:int|null,
     *   purpose?:string|null,
     *   picked_at?:string|null,
     *   remarks?:string|null,
     *   lines:array<int,array{
     *     item_id:int,
     *     uom_id?:int|null,
     *     source_location_id:int,
     *     qty:numeric,
     *     remarks?:string|null
     *   }>
     * } $data
     */
    public function createDraft(User $actor, array $data): PickingOrder
    {
        return DB::transaction(function () use ($actor, $data) {
            if (!$actor->hasAnyRole(['super_admin', 'warehouse'])) {
                throw new AuthorizationException('Only warehouse can create Picking Order.');
            }

            $incomingLines = array_values((array) ($data['lines'] ?? []));
            if (count($incomingLines) === 0) {
                throw ValidationException::withMessages([
                    'lines' => 'At least one line is required.',
                ]);
            }

            $warehouseId = (int) $data['warehouse_id'];

            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::query()->findOrFail($warehouseId);

            if (!$warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Warehouse is not active.',
                ]);
            }

            $pickingOrder = new PickingOrder();
            $pickingOrder->picking_order_number = $this->numberGenerator->generate();
            $pickingOrder->warehouse_id = $warehouse->id;
            $pickingOrder->department_id = Arr::get($data, 'department_id') ? (int) $data['department_id'] : null;
            $pickingOrder->status = PickingOrder::STATUS_DRAFT;
            $pickingOrder->purpose = Arr::get($data, 'purpose');
            $pickingOrder->picked_at = Arr::get($data, 'picked_at') ? now()->parse((string) $data['picked_at']) : now();
            $pickingOrder->remarks = Arr::get($data, 'remarks');
            $pickingOrder->created_by_user_id = $actor->id;
            $pickingOrder->save();

            $this->syncLines($pickingOrder, $warehouse, $incomingLines);

            $this->recordStatusHistory($pickingOrder, null, $pickingOrder->status, 'create', $actor, [
                'warehouse_id' => $warehouse->id,
                'department_id' => $pickingOrder->department_id,
            ]);

            return $this->loadForShow($pickingOrder);
        });
    }

    public function post(User $actor, int $pickingOrderId): PickingOrder
    {
        return DB::transaction(function () use ($actor, $pickingOrderId) {
            if (!$actor->hasAnyRole(['super_admin', 'warehouse'])) {
                throw new AuthorizationException('Only warehouse can post Picking Order.');
            }

            /** @var PickingOrder $pickingOrder */
            $pickingOrder = PickingOrder::query()
                ->lockForUpdate()
                ->with(['lines', 'warehouse'])
                ->findOrFail($pickingOrderId);

            if ($pickingOrder->status !== PickingOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only DRAFT Picking Order can be posted.',
                ]);
            }

            $warehouse = $pickingOrder->warehouse;

            if (!$warehouse || !$warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Warehouse not found or inactive.',
                ]);
            }

            // Validate stock availability for each line (real-time check)
            foreach ($pickingOrder->lines as $i => $line) {
                /** @var PickingOrderLine $line */

                // Validate source location
                $sourceLocation = WarehouseLocation::query()->find((int) $line->source_location_id);

                if (!$sourceLocation || !$sourceLocation->is_active) {
                    throw ValidationException::withMessages([
                        "lines.$i.source_location_id" => 'Source location not found or inactive.',
                    ]);
                }

                if ($sourceLocation->type !== WarehouseLocation::TYPE_STORAGE) {
                    throw ValidationException::withMessages([
                        "lines.$i.source_location_id" => 'Source location must be STORAGE type.',
                    ]);
                }

                if ((int) $sourceLocation->warehouse_id !== (int) $warehouse->id) {
                    throw ValidationException::withMessages([
                        "lines.$i.source_location_id" => 'Source location must be within the same warehouse.',
                    ]);
                }

                // Check real-time stock availability
                $availableStock = $this->stockQueryService->getOnHandForLocation(
                    (int) $line->source_location_id,
                    (int) $line->item_id,
                    $line->uom_id ? (int) $line->uom_id : null
                );

                $requestedQty = (float) $line->qty;

                if ($requestedQty > $availableStock + 1e-9) { // Allow tiny float tolerance
                    throw ValidationException::withMessages([
                        "lines.$i.qty" => sprintf(
                            'Insufficient stock at %s. Available: %s, Requested: %s',
                            $sourceLocation->code,
                            number_format($availableStock, 4),
                            number_format($requestedQty, 4)
                        ),
                    ]);
                }
            }

            $from = $pickingOrder->status;
            $pickingOrder->status = PickingOrder::STATUS_POSTED;
            $pickingOrder->posted_at = now();
            $pickingOrder->posted_by_user_id = $actor->id;
            $pickingOrder->save();

            $this->recordStatusHistory($pickingOrder, $from, $pickingOrder->status, 'post', $actor);

            // Create stock movements for each picking order line
            foreach ($pickingOrder->lines as $line) {
                /** @var PickingOrderLine $line */
                $this->stockMovementService->createMovement([
                    'item_id' => (int) $line->item_id,
                    'uom_id' => $line->uom_id ? (int) $line->uom_id : null,
                    'source_location_id' => (int) $line->source_location_id,
                    'destination_location_id' => null, // OUT from warehouse
                    'qty' => (float) $line->qty,
                    'reference_type' => StockMovement::REF_PICKING_ORDER,
                    'reference_id' => (int) $pickingOrder->id,
                    'created_by' => (int) $actor->id,
                    'movement_at' => $pickingOrder->posted_at,
                    'meta' => [
                        'picking_order_number' => $pickingOrder->picking_order_number,
                        'picking_order_line_id' => (int) $line->id,
                        'department_id' => $pickingOrder->department_id,
                        'purpose' => $pickingOrder->purpose,
                    ],
                ]);
            }

            return $this->loadForShow($pickingOrder);
        });
    }

    public function cancel(User $actor, int $pickingOrderId, ?string $reason = null): PickingOrder
    {
        return DB::transaction(function () use ($actor, $pickingOrderId, $reason) {
            if (!$actor->hasAnyRole(['super_admin', 'warehouse'])) {
                throw new AuthorizationException('Only warehouse can cancel Picking Order.');
            }

            /** @var PickingOrder $pickingOrder */
            $pickingOrder = PickingOrder::query()
                ->lockForUpdate()
                ->with(['warehouse'])
                ->findOrFail($pickingOrderId);

            if ($pickingOrder->status !== PickingOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only DRAFT Picking Order can be cancelled.',
                ]);
            }

            $from = $pickingOrder->status;
            $pickingOrder->status = PickingOrder::STATUS_CANCELLED;
            $pickingOrder->cancelled_at = now();
            $pickingOrder->cancelled_by_user_id = $actor->id;
            $pickingOrder->cancel_reason = $reason ? trim($reason) : null;
            $pickingOrder->save();

            $this->recordStatusHistory($pickingOrder, $from, $pickingOrder->status, 'cancel', $actor, [
                'reason' => $pickingOrder->cancel_reason,
            ]);

            return $this->loadForShow($pickingOrder);
        });
    }

    /**
     * @param array<int,array{item_id:int,uom_id?:int|null,source_location_id:int,qty:numeric,remarks?:string|null}> $incomingLines
     */
    private function syncLines(PickingOrder $pickingOrder, Warehouse $warehouse, array $incomingLines): void
    {
        $pickingOrder->lines()->delete();

        foreach ($incomingLines as $i => $line) {
            $itemId = (int) Arr::get($line, 'item_id');
            $uomId = Arr::get($line, 'uom_id') ? (int) $line['uom_id'] : null;
            $sourceLocationId = (int) Arr::get($line, 'source_location_id');
            $qty = (float) Arr::get($line, 'qty', 0);

            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    "lines.$i.qty" => 'Quantity must be greater than 0.',
                ]);
            }

            // Validate source location exists and is STORAGE type
            $sourceLocation = WarehouseLocation::query()->find($sourceLocationId);

            if (!$sourceLocation || !$sourceLocation->is_active) {
                throw ValidationException::withMessages([
                    "lines.$i.source_location_id" => 'Source location not found or inactive.',
                ]);
            }

            if ($sourceLocation->type !== WarehouseLocation::TYPE_STORAGE) {
                throw ValidationException::withMessages([
                    "lines.$i.source_location_id" => 'Source location must be STORAGE type.',
                ]);
            }

            if ((int) $sourceLocation->warehouse_id !== (int) $warehouse->id) {
                throw ValidationException::withMessages([
                    "lines.$i.source_location_id" => 'Source location must be within the same warehouse.',
                ]);
            }

            $pickingOrder->lines()->create([
                'item_id' => $itemId,
                'uom_id' => $uomId,
                'source_location_id' => $sourceLocationId,
                'qty' => $qty,
                'remarks' => Arr::get($line, 'remarks'),
            ]);
        }
    }

    private function recordStatusHistory(
        PickingOrder $pickingOrder,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        ?User $actor,
        array $meta = [],
    ): void {
        PickingOrderStatusHistory::query()->create([
            'picking_order_id' => $pickingOrder->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action' => $action,
            'actor_user_id' => $actor?->id,
            'meta' => empty($meta) ? null : $meta,
            'created_at' => now(),
        ]);
    }

    private function loadForShow(PickingOrder $pickingOrder): PickingOrder
    {
        return $pickingOrder->load([
            'department',
            'warehouse',
            'lines.item',
            'lines.uom',
            'lines.sourceLocation',
            'statusHistories.actor',
            'createdBy',
            'postedBy',
        ]);
    }
}
