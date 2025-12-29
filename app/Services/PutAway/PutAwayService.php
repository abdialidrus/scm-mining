<?php

namespace App\Services\PutAway;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PutAway;
use App\Models\PutAwayLine;
use App\Models\PutAwayStatusHistory;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Services\Inventory\StockMovementService;
use App\Services\Inventory\StockQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PutAwayService
{
    public function __construct(
        private readonly PutAwayNumberGenerator $numberGenerator,
        private readonly StockMovementService $stockMovementService,
        private readonly StockQueryService $stockQueryService,
    ) {}

    /**
     * @param array{
     *   goods_receipt_id:int,
     *   put_away_at?:string|null,
     *   remarks?:string|null,
     *   lines:array<int,array{
     *     goods_receipt_line_id:int,
     *     destination_location_id:int,
     *     qty:numeric,
     *     remarks?:string|null
     *   }>
     * } $data
     */
    public function createDraft(User $actor, array $data): PutAway
    {
        return DB::transaction(function () use ($actor, $data) {
            if (!$actor->hasAnyRole(['super_admin', 'warehouse'])) {
                throw new AuthorizationException('Only warehouse can create Put Away.');
            }

            $incomingLines = array_values((array) ($data['lines'] ?? []));
            if (count($incomingLines) === 0) {
                throw ValidationException::withMessages([
                    'lines' => 'At least one line is required.',
                ]);
            }

            $grId = (int) $data['goods_receipt_id'];

            /** @var GoodsReceipt $gr */
            $gr = GoodsReceipt::query()
                ->lockForUpdate()
                ->with(['lines', 'warehouse'])
                ->findOrFail($grId);

            if (!in_array($gr->status, [GoodsReceipt::STATUS_POSTED, GoodsReceipt::STATUS_PUT_AWAY_PARTIAL], true)) {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => 'Put Away can only be created for POSTED / PUT_AWAY_PARTIAL GR.',
                ]);
            }

            // Stale UI guard: validate remaining only for GR lines referenced by incoming payload.
            $lineIds = array_values(array_unique(array_map(
                static fn($l) => (int) Arr::get($l, 'goods_receipt_line_id'),
                $incomingLines,
            )));

            $postedQtyByGrLine = DB::table('put_away_lines')
                ->join('put_aways', 'put_aways.id', '=', 'put_away_lines.put_away_id')
                ->where('put_aways.goods_receipt_id', $gr->id)
                ->where('put_aways.status', PutAway::STATUS_POSTED)
                ->whereIn('put_away_lines.goods_receipt_line_id', $lineIds)
                ->selectRaw('put_away_lines.goods_receipt_line_id, SUM(put_away_lines.qty) as qty')
                ->groupBy('put_away_lines.goods_receipt_line_id')
                ->pluck('qty', 'goods_receipt_line_id')
                ->all();

            $grLinesById = $gr->lines->keyBy('id');
            $errors = [];
            $totalRemaining = 0.0;

            foreach ($incomingLines as $i => $payloadLine) {
                $grLineId = (int) Arr::get($payloadLine, 'goods_receipt_line_id');
                $qty = (float) Arr::get($payloadLine, 'qty', 0);

                /** @var GoodsReceiptLine|null $grLine */
                $grLine = $grLinesById->get($grLineId);
                if (!$grLine) {
                    // Let syncLines handle the canonical validation message.
                    continue;
                }

                $recv = (float) $grLine->received_quantity;
                $put = (float) ($postedQtyByGrLine[$grLineId] ?? 0);
                $remaining = max(0.0, $recv - $put);
                $totalRemaining += $remaining;

                if ($remaining <= 1e-9) {
                    $errors["lines.$i.goods_receipt_line_id"] = [
                        'No remaining quantity for this GR line.',
                    ];
                    continue;
                }

                // Enforce cap at draft time as well (stale UI / concurrency safety).
                if ($qty - $remaining > 1e-9) {
                    $errors["lines.$i.qty"] = [
                        "Qty exceeds remaining quantity. Remaining: {$remaining}.",
                    ];
                }
            }

            if (!empty($errors)) {
                throw ValidationException::withMessages($errors);
            }

            if ($totalRemaining <= 1e-9) {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => 'No remaining quantity to put away for this Goods Receipt.',
                ]);
            }

            $putAway = new PutAway();
            $putAway->put_away_number = $this->numberGenerator->generate();
            $putAway->goods_receipt_id = $gr->id;
            $putAway->warehouse_id = $gr->warehouse_id;
            $putAway->status = PutAway::STATUS_DRAFT;
            $putAway->put_away_at = Arr::get($data, 'put_away_at') ? now()->parse((string) $data['put_away_at']) : now();
            $putAway->remarks = Arr::get($data, 'remarks');
            $putAway->created_by_user_id = $actor->id;
            $putAway->save();

            $this->syncLines($putAway, $gr, $incomingLines);

            $this->recordStatusHistory($putAway, null, $putAway->status, 'create', $actor, [
                'goods_receipt_id' => $gr->id,
                'gr_number' => $gr->gr_number,
            ]);

            return $this->loadForShow($putAway);
        });
    }

    public function post(User $actor, int $putAwayId): PutAway
    {
        return DB::transaction(function () use ($actor, $putAwayId) {
            if (!$actor->hasAnyRole(['super_admin', 'warehouse'])) {
                throw new AuthorizationException('Only warehouse can post Put Away.');
            }

            /** @var PutAway $putAway */
            $putAway = PutAway::query()
                ->lockForUpdate()
                ->with(['lines', 'goodsReceipt.lines'])
                ->findOrFail($putAwayId);

            if ($putAway->status !== PutAway::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only DRAFT Put Away can be posted.',
                ]);
            }

            /** @var GoodsReceipt $gr */
            $gr = GoodsReceipt::query()
                ->lockForUpdate()
                ->with(['lines'])
                ->findOrFail($putAway->goods_receipt_id);

            if (!in_array($gr->status, [GoodsReceipt::STATUS_POSTED, GoodsReceipt::STATUS_PUT_AWAY_PARTIAL], true)) {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => 'GR is not in put-awayable state.',
                ]);
            }

            $receivingLocationId = (int) WarehouseLocation::query()
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

            // Validate (business cap): total put away per GR line must not exceed GR received qty.
            $alreadyPutAwayByGrLine = DB::table('put_away_lines')
                ->join('put_aways', 'put_aways.id', '=', 'put_away_lines.put_away_id')
                ->where('put_aways.goods_receipt_id', $gr->id)
                ->where('put_aways.status', PutAway::STATUS_POSTED)
                ->selectRaw('put_away_lines.goods_receipt_line_id, SUM(put_away_lines.qty) as qty')
                ->groupBy('put_away_lines.goods_receipt_line_id')
                ->pluck('qty', 'goods_receipt_line_id')
                ->all();

            $grLinesById = $gr->lines->keyBy('id');

            foreach ($putAway->lines as $i => $line) {
                /** @var PutAwayLine $line */
                $qty = (float) $line->qty;
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        "lines.$i.qty" => 'Qty must be > 0.',
                    ]);
                }

                /** @var GoodsReceiptLine|null $grLine */
                $grLine = $grLinesById->get((int) $line->goods_receipt_line_id);
                if (!$grLine) {
                    throw ValidationException::withMessages([
                        "lines.$i.goods_receipt_line_id" => 'GR line not found.',
                    ]);
                }

                $already = (float) ($alreadyPutAwayByGrLine[$grLine->id] ?? 0);
                $recv = (float) $grLine->received_quantity;

                if (($already + $qty) - $recv > 1e-9) {
                    throw ValidationException::withMessages([
                        "lines.$i.qty" => "Over put-away is not allowed. Received: {$recv}, already put away: {$already}.",
                    ]);
                }

                // Ledger cap: on-hand in RECEIVING must be sufficient per item/uom at the moment of posting.
                $onHand = $this->stockQueryService->getOnHandForLocation(
                    $receivingLocationId,
                    (int) $line->item_id,
                    $line->uom_id ? (int) $line->uom_id : null,
                );

                if (($qty - $onHand) > 1e-9) {
                    throw ValidationException::withMessages([
                        "lines.$i.qty" => "Insufficient stock in RECEIVING. On hand: {$onHand}.",
                    ]);
                }

                // Validate destination is STORAGE within same warehouse.
                $dest = WarehouseLocation::query()->find((int) $line->destination_location_id);
                if (!$dest || !$dest->is_active) {
                    throw ValidationException::withMessages([
                        "lines.$i.destination_location_id" => 'Destination location not found or inactive.',
                    ]);
                }
                if ($dest->type !== WarehouseLocation::TYPE_STORAGE) {
                    throw ValidationException::withMessages([
                        "lines.$i.destination_location_id" => 'Destination location must be STORAGE.',
                    ]);
                }
                if ((int) $dest->warehouse_id !== (int) $gr->warehouse_id) {
                    throw ValidationException::withMessages([
                        "lines.$i.destination_location_id" => 'Destination location must be within the same warehouse.',
                    ]);
                }
            }

            $from = $putAway->status;
            $putAway->status = PutAway::STATUS_POSTED;
            $putAway->posted_at = now();
            $putAway->posted_by_user_id = $actor->id;
            $putAway->save();

            // Create stock movements for each put away line.
            foreach ($putAway->lines as $line) {
                /** @var PutAwayLine $line */
                $this->stockMovementService->createMovement([
                    'item_id' => (int) $line->item_id,
                    'uom_id' => $line->uom_id ? (int) $line->uom_id : null,
                    'source_location_id' => (int) $receivingLocationId,
                    'destination_location_id' => (int) $line->destination_location_id,
                    'qty' => (float) $line->qty,
                    'reference_type' => StockMovement::REF_PUT_AWAY,
                    'reference_id' => (int) $putAway->id,
                    'created_by' => (int) $actor->id,
                    'movement_at' => $putAway->posted_at,
                    'meta' => [
                        'put_away_number' => $putAway->put_away_number,
                        'put_away_line_id' => (int) $line->id,
                        'goods_receipt_id' => (int) $gr->id,
                        'gr_number' => $gr->gr_number,
                        'goods_receipt_line_id' => (int) $line->goods_receipt_line_id,
                    ],
                ]);

                // Update serial numbers location if item is serialized
                $item = $line->item;
                if ($item && $item->is_serialized) {
                    // Get serial numbers for this GR line that are still in RECEIVING
                    $serialNumbers = \App\Models\ItemSerialNumber::query()
                        ->where('item_id', $line->item_id)
                        ->where('goods_receipt_line_id', $line->goods_receipt_line_id)
                        ->where('current_location_id', $receivingLocationId)
                        ->where('status', \App\Models\ItemSerialNumber::STATUS_AVAILABLE)
                        ->limit((int) $line->qty)
                        ->get();

                    foreach ($serialNumbers as $serial) {
                        $serial->current_location_id = $line->destination_location_id;
                        $serial->save();
                    }
                }
            }

            $this->recordStatusHistory($putAway, $from, $putAway->status, 'post', $actor);

            // Update GR status based on total posted put-away qty per GR line.
            $this->syncGoodsReceiptPutAwayStatus($actor, $gr);

            return $this->loadForShow($putAway);
        });
    }

    public function cancel(User $actor, int $putAwayId, ?string $reason = null): PutAway
    {
        return DB::transaction(function () use ($actor, $putAwayId, $reason) {
            if (!$actor->hasAnyRole(['super_admin', 'warehouse'])) {
                throw new AuthorizationException('Only warehouse can cancel Put Away.');
            }

            /** @var PutAway $putAway */
            $putAway = PutAway::query()
                ->lockForUpdate()
                ->with(['goodsReceipt'])
                ->findOrFail($putAwayId);

            if ($putAway->status !== PutAway::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only DRAFT Put Away can be cancelled.',
                ]);
            }

            $from = $putAway->status;
            $putAway->status = PutAway::STATUS_CANCELLED;
            $putAway->cancelled_at = now();
            $putAway->cancelled_by_user_id = $actor->id;
            $putAway->cancel_reason = $reason ? trim($reason) : null;
            $putAway->save();

            $this->recordStatusHistory($putAway, $from, $putAway->status, 'cancel', $actor, [
                'reason' => $putAway->cancel_reason,
            ]);

            return $this->loadForShow($putAway);
        });
    }

    /**
     * @param array<int,array{goods_receipt_line_id:int,destination_location_id:int,qty:numeric,remarks?:string|null}> $incomingLines
     */
    private function syncLines(PutAway $putAway, GoodsReceipt $gr, array $incomingLines): void
    {
        $putAway->lines()->delete();

        $grLines = $gr->lines->keyBy('id');

        $receivingLocationId = (int) WarehouseLocation::query()
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

        foreach ($incomingLines as $i => $line) {
            $grLineId = (int) Arr::get($line, 'goods_receipt_line_id');
            $qty = (float) Arr::get($line, 'qty', 0);
            $destId = (int) Arr::get($line, 'destination_location_id');

            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    "lines.$i.qty" => 'Qty must be > 0.',
                ]);
            }

            /** @var GoodsReceiptLine|null $grLine */
            $grLine = $grLines->get($grLineId);
            if (!$grLine) {
                throw ValidationException::withMessages([
                    "lines.$i.goods_receipt_line_id" => 'GR line not found.',
                ]);
            }

            // Destination validation is repeated on post, but validate early for better DX.
            $dest = WarehouseLocation::query()->find($destId);
            if (!$dest || !$dest->is_active) {
                throw ValidationException::withMessages([
                    "lines.$i.destination_location_id" => 'Destination location not found or inactive.',
                ]);
            }
            if ($dest->type !== WarehouseLocation::TYPE_STORAGE) {
                throw ValidationException::withMessages([
                    "lines.$i.destination_location_id" => 'Destination location must be STORAGE.',
                ]);
            }
            if ((int) $dest->warehouse_id !== (int) $gr->warehouse_id) {
                throw ValidationException::withMessages([
                    "lines.$i.destination_location_id" => 'Destination location must be within the same warehouse.',
                ]);
            }

            $putAway->lines()->create([
                'goods_receipt_line_id' => $grLine->id,
                'item_id' => $grLine->item_id,
                'uom_id' => $grLine->uom_id,
                'source_location_id' => $receivingLocationId,
                'destination_location_id' => $dest->id,
                'qty' => $qty,
                'remarks' => Arr::get($line, 'remarks'),
            ]);
        }
    }

    private function syncGoodsReceiptPutAwayStatus(User $actor, GoodsReceipt $gr): void
    {
        $postedQtyByGrLine = DB::table('put_away_lines')
            ->join('put_aways', 'put_aways.id', '=', 'put_away_lines.put_away_id')
            ->where('put_aways.goods_receipt_id', $gr->id)
            ->where('put_aways.status', PutAway::STATUS_POSTED)
            ->selectRaw('put_away_lines.goods_receipt_line_id, SUM(put_away_lines.qty) as qty')
            ->groupBy('put_away_lines.goods_receipt_line_id')
            ->pluck('qty', 'goods_receipt_line_id')
            ->all();

        $allDone = true;
        foreach ($gr->lines as $line) {
            /** @var GoodsReceiptLine $line */
            $recv = (float) $line->received_quantity;
            $put = (float) ($postedQtyByGrLine[$line->id] ?? 0);

            if (($recv - $put) > 1e-9) {
                $allDone = false;
                break;
            }
        }

        $from = $gr->status;
        $to = $allDone ? GoodsReceipt::STATUS_PUT_AWAY_COMPLETED : GoodsReceipt::STATUS_PUT_AWAY_PARTIAL;

        if ($from !== $to) {
            $gr->status = $to;
            $gr->save();

            // reuse GR history implementation
            \App\Models\GoodsReceiptStatusHistory::query()->create([
                'goods_receipt_id' => $gr->id,
                'from_status' => $from,
                'to_status' => $to,
                'action' => 'put_away_sync',
                'actor_user_id' => $actor->id,
                'meta' => null,
                'created_at' => now(),
            ]);
        }
    }

    private function recordStatusHistory(
        PutAway $putAway,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        ?User $actor,
        array $meta = [],
    ): void {
        PutAwayStatusHistory::query()->create([
            'put_away_id' => $putAway->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action' => $action,
            'actor_user_id' => $actor?->id,
            'meta' => empty($meta) ? null : $meta,
            'created_at' => now(),
        ]);
    }

    private function loadForShow(PutAway $putAway): PutAway
    {
        return $putAway->load([
            'goodsReceipt',
            'warehouse',
            'lines.item',
            'lines.uom',
            'lines.sourceLocation',
            'lines.destinationLocation',
            'statusHistories.actor',
            'createdBy',
            'postedBy',
        ]);
    }
}
