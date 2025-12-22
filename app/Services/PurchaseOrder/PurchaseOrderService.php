<?php

namespace App\Services\PurchaseOrder;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderStatusHistory;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly PurchaseOrderNumberGenerator $numberGenerator,
    ) {}

    /**
     * Create PO draft from multiple PRs (must all be APPROVED and same supplier).
     *
     * @param array{
     *   supplier_id:int,
     *   purchase_request_ids:array<int,int>,
     *   currency_code?:string|null,
     *   tax_rate?:numeric|null,
     *   lines?:array<int,array{item_id:int,quantity:numeric,uom_id:int,unit_price?:numeric|null,remarks?:string|null}>
     * } $data
     */
    public function createDraftFromPurchaseRequests(User $actor, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $data) {
            if (!$actor->hasAnyRole(['super_admin', 'procurement'])) {
                throw new AuthorizationException('Only procurement can create PO.');
            }

            $supplierId = (int) $data['supplier_id'];
            $supplier = Supplier::query()->findOrFail($supplierId);

            $ids = array_values(array_unique(array_map('intval', $data['purchase_request_ids'] ?? [])));
            if (count($ids) === 0) {
                throw ValidationException::withMessages([
                    'purchase_request_ids' => 'At least one Purchase Request is required.',
                ]);
            }

            /** @var \Illuminate\Database\Eloquent\Collection<int, PurchaseRequest> $prs */
            $prs = PurchaseRequest::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($prs->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'purchase_request_ids' => 'One or more Purchase Requests were not found.',
                ]);
            }

            foreach ($prs as $pr) {
                if ($pr->status !== PurchaseRequest::STATUS_APPROVED) {
                    throw ValidationException::withMessages([
                        'purchase_request_ids' => "PR {$pr->pr_number} must be APPROVED.",
                    ]);
                }
            }

            $currency = trim((string) Arr::get($data, 'currency_code', config('procurement.default_currency', 'IDR')));
            if ($currency === '') {
                $currency = 'IDR';
            }

            $taxRate = Arr::get($data, 'tax_rate');
            if ($taxRate === null || $taxRate === '') {
                $taxRate = config('procurement.ppn_rate', 0.11);
            }
            $taxRate = (float) $taxRate;
            if ($taxRate < 0 || $taxRate > 1) {
                throw ValidationException::withMessages([
                    'tax_rate' => 'Tax rate must be between 0 and 1.',
                ]);
            }

            $po = new PurchaseOrder();
            $po->po_number = $this->numberGenerator->generate();
            $po->supplier_id = $supplier->id;
            $po->status = PurchaseOrder::STATUS_DRAFT;
            $po->currency_code = $currency;
            $po->tax_rate = $taxRate;

            // Snapshots (stored at PO creation time)
            $po->supplier_snapshot = [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
            ];
            $po->tax_snapshot = [
                'type' => 'PPN',
                'rate' => $taxRate,
                'label' => 'PPN',
            ];

            $po->save();

            // Attach PRs (merge capability)
            $po->purchaseRequests()->sync($ids);

            // Create lines (either explicitly provided or merged from PR lines)
            $lines = Arr::get($data, 'lines');
            if (!is_array($lines) || count($lines) === 0) {
                $lines = $this->buildMergedLinesFromPRs($prs);
            }

            $this->syncLines($po, $lines);

            // Mark PRs converted to PO (keeps old behavior)
            foreach ($prs as $pr) {
                $from = $pr->status;
                $pr->status = PurchaseRequest::STATUS_CONVERTED_TO_PO;
                $pr->converted_to_po_at = now();
                $pr->save();

                PurchaseOrderStatusHistory::query()->create([
                    'purchase_order_id' => $po->id,
                    'from_status' => null,
                    'to_status' => $po->status,
                    'action' => 'create',
                    'actor_user_id' => $actor->id,
                    'meta' => [
                        'purchase_request_id' => $pr->id,
                        'purchase_request_number' => $pr->pr_number,
                        'purchase_request_from_status' => $from,
                    ],
                    'created_at' => now(),
                ]);
            }

            return $po->load([
                'supplier',
                'lines.item',
                'lines.uom',
                'purchaseRequests',
                'statusHistories.actor',
            ]);
        });
    }

    public function submit(User $actor, int $purchaseOrderId): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $purchaseOrderId) {
            if (!$actor->hasAnyRole(['super_admin', 'procurement'])) {
                throw new AuthorizationException('Only procurement can submit PO.');
            }

            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrderId);

            if ($po->status !== PurchaseOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only DRAFT PO can be submitted.',
                ]);
            }

            $from = $po->status;
            $po->status = PurchaseOrder::STATUS_SUBMITTED;
            $po->submitted_at = now();
            $po->submitted_by_user_id = $actor->id;
            $po->save();

            $this->recordStatusHistory($po, $from, $po->status, 'submit', $actor);

            return $this->loadForShow($po);
        });
    }

    /**
     * Approve flow:
     * - finance approves SUBMITTED -> IN_APPROVAL
     * - gm approves IN_APPROVAL -> IN_APPROVAL (records step)
     * - director approves IN_APPROVAL -> APPROVED (final)
     */
    public function approve(User $actor, int $purchaseOrderId): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $purchaseOrderId) {
            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrderId);

            if (!in_array($po->status, [PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_IN_APPROVAL], true)) {
                throw ValidationException::withMessages([
                    'status' => 'PO is not in an approvable state.',
                ]);
            }

            $step = $this->nextApprovalStep($po);

            if ($step === 'finance') {
                if (!$actor->hasAnyRole(['finance', 'super_admin'])) {
                    throw new AuthorizationException('Only finance can approve this step.');
                }
                if ($po->status !== PurchaseOrder::STATUS_SUBMITTED) {
                    throw ValidationException::withMessages([
                        'status' => 'Finance step requires SUBMITTED PO.',
                    ]);
                }

                $from = $po->status;
                $po->status = PurchaseOrder::STATUS_IN_APPROVAL;
                $po->save();

                $this->recordStatusHistory($po, $from, $po->status, 'approve', $actor, ['step' => 'finance']);

                return $this->loadForShow($po);
            }

            if ($step === 'gm') {
                if (!$actor->hasAnyRole(['gm', 'super_admin'])) {
                    throw new AuthorizationException('Only GM can approve this step.');
                }

                // Keep status IN_APPROVAL, just record step
                if ($po->status !== PurchaseOrder::STATUS_IN_APPROVAL) {
                    throw ValidationException::withMessages([
                        'status' => 'GM step requires IN_APPROVAL PO.',
                    ]);
                }

                $this->recordStatusHistory($po, $po->status, $po->status, 'approve', $actor, ['step' => 'gm']);

                return $this->loadForShow($po);
            }

            // director
            if (!$actor->hasAnyRole(['director', 'super_admin'])) {
                throw new AuthorizationException('Only Director can approve this step.');
            }

            if ($po->status !== PurchaseOrder::STATUS_IN_APPROVAL) {
                throw ValidationException::withMessages([
                    'status' => 'Director step requires IN_APPROVAL PO.',
                ]);
            }

            $from = $po->status;
            $po->status = PurchaseOrder::STATUS_APPROVED;
            $po->approved_at = now();
            $po->approved_by_user_id = $actor->id;
            $po->save();

            $this->recordStatusHistory($po, $from, $po->status, 'approve', $actor, ['step' => 'director']);

            return $this->loadForShow($po);
        });
    }

    public function send(User $actor, int $purchaseOrderId): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $purchaseOrderId) {
            if (!$actor->hasAnyRole(['super_admin', 'procurement'])) {
                throw new AuthorizationException('Only procurement can send PO.');
            }

            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrderId);

            if ($po->status !== PurchaseOrder::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => 'Only APPROVED PO can be sent.',
                ]);
            }

            $from = $po->status;
            $po->status = PurchaseOrder::STATUS_SENT;
            $po->sent_at = now();
            $po->sent_by_user_id = $actor->id;
            $po->save();

            $this->recordStatusHistory($po, $from, $po->status, 'send', $actor);

            return $this->loadForShow($po);
        });
    }

    public function close(User $actor, int $purchaseOrderId): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $purchaseOrderId) {
            if (!$actor->hasAnyRole(['super_admin', 'procurement'])) {
                throw new AuthorizationException('Only procurement can close PO.');
            }

            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrderId);

            if ($po->status !== PurchaseOrder::STATUS_SENT) {
                throw ValidationException::withMessages([
                    'status' => 'Only SENT PO can be closed.',
                ]);
            }

            $from = $po->status;
            $po->status = PurchaseOrder::STATUS_CLOSED;
            $po->closed_at = now();
            $po->closed_by_user_id = $actor->id;
            $po->save();

            $this->recordStatusHistory($po, $from, $po->status, 'close', $actor);

            return $this->loadForShow($po);
        });
    }

    public function cancel(User $actor, int $purchaseOrderId, ?string $reason = null): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $purchaseOrderId, $reason) {
            if (!$actor->hasAnyRole(['super_admin', 'procurement'])) {
                throw new AuthorizationException('Only procurement can cancel PO.');
            }

            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrderId);

            if (in_array($po->status, [PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'PO is already closed or cancelled.',
                ]);
            }

            $from = $po->status;
            $po->status = PurchaseOrder::STATUS_CANCELLED;
            $po->cancelled_at = now();
            $po->cancelled_by_user_id = $actor->id;
            $po->cancel_reason = $reason;
            $po->save();

            $this->recordStatusHistory($po, $from, $po->status, 'cancel', $actor, [
                'reason' => $reason,
            ]);

            return $this->loadForShow($po);
        });
    }

    /**
     * @param array{
     *   supplier_id:int,
     *   currency_code:string,
     *   tax_rate:numeric,
     *   lines:array<int,array{id:int,unit_price:numeric,remarks?:string|null}>
     * } $data
     */
    public function updateDraft(User $actor, int $purchaseOrderId, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $purchaseOrderId, $data) {
            if (!$actor->hasAnyRole(['super_admin', 'procurement'])) {
                throw new AuthorizationException('Only procurement can update draft PO.');
            }

            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()
                ->lockForUpdate()
                ->with(['lines'])
                ->findOrFail($purchaseOrderId);

            if ($po->status !== PurchaseOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only DRAFT PO can be updated.',
                ]);
            }

            $supplierId = (int) $data['supplier_id'];
            $supplier = Supplier::query()->findOrFail($supplierId);

            $currency = trim((string) ($data['currency_code'] ?? ''));
            if ($currency === '') {
                throw ValidationException::withMessages([
                    'currency_code' => 'Currency code is required.',
                ]);
            }

            $taxRate = (float) ($data['tax_rate'] ?? 0);
            if ($taxRate < 0 || $taxRate > 1) {
                throw ValidationException::withMessages([
                    'tax_rate' => 'Tax rate must be between 0 and 1.',
                ]);
            }

            $po->supplier_id = $supplier->id;
            $po->currency_code = $currency;
            $po->tax_rate = $taxRate;

            // Refresh snapshots on edit
            $po->supplier_snapshot = [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
            ];
            $po->tax_snapshot = [
                'type' => 'PPN',
                'rate' => $taxRate,
                'label' => 'PPN',
            ];

            $po->save();

            $incomingLines = $data['lines'] ?? [];
            if (!is_array($incomingLines) || count($incomingLines) === 0) {
                throw ValidationException::withMessages([
                    'lines' => 'At least one line is required.',
                ]);
            }

            // Map current PO lines by id
            $existing = $po->lines->keyBy('id');

            // Validate that payload refers only to this PO's lines
            foreach ($incomingLines as $i => $line) {
                $lineId = (int) ($line['id'] ?? 0);
                /** @var PurchaseOrderLine|null $model */
                $model = $existing->get($lineId);

                if (!$model) {
                    throw ValidationException::withMessages([
                        "lines.$i.id" => 'Invalid line id for this purchase order.',
                    ]);
                }

                $model->unit_price = (float) ($line['unit_price'] ?? 0);
                $model->remarks = Arr::get($line, 'remarks');
                $model->save();
            }

            // Record status history without changing status
            $this->recordStatusHistory($po, $po->status, $po->status, 'update_draft', $actor, [
                'fields' => ['supplier_id', 'currency_code', 'tax_rate', 'lines.unit_price'],
            ]);

            return $this->loadForShow($po->refresh());
        });
    }

    public function reopen(User $actor, int $purchaseOrderId, ?string $reason = null): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $purchaseOrderId, $reason) {
            if (!$actor->hasAnyRole(['super_admin', 'procurement'])) {
                throw new AuthorizationException('Only procurement can reopen PO.');
            }

            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrderId);

            if ($po->status !== PurchaseOrder::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'Only CANCELLED PO can be reopened to DRAFT.',
                ]);
            }

            $from = $po->status;
            $po->status = PurchaseOrder::STATUS_DRAFT;

            // Preserve cancellation fields for audit (cancelled_at/by/reason remain set)
            $po->save();

            $this->recordStatusHistory($po, $from, $po->status, 'reopen', $actor, [
                'reason' => $reason,
                'preserved_cancel_reason' => $po->cancel_reason,
                'preserved_cancelled_at' => optional($po->cancelled_at)->toISOString(),
            ]);

            return $this->loadForShow($po);
        });
    }

    /** @return array<int,array{item_id:int,quantity:numeric,uom_id:int,unit_price?:numeric|null,remarks?:string|null}> */
    private function buildMergedLinesFromPRs($prs): array
    {
        $map = [];

        foreach ($prs as $pr) {
            $pr->loadMissing(['lines']);
            foreach ($pr->lines as $line) {
                $key = $line->item_id . '|' . $line->uom_id;
                if (!isset($map[$key])) {
                    $map[$key] = [
                        'item_id' => (int) $line->item_id,
                        'uom_id' => (int) $line->uom_id,
                        'quantity' => 0,
                        'unit_price' => 0,
                        'remarks' => null,
                    ];
                }
                $map[$key]['quantity'] = (float) $map[$key]['quantity'] + (float) $line->quantity;
            }
        }

        return array_values($map);
    }

    /**
     * @param array<int,array{item_id:int,quantity:numeric,uom_id:int,unit_price?:numeric|null,remarks?:string|null}> $lines
     */
    private function syncLines(PurchaseOrder $po, array $lines): void
    {
        $po->lines()->delete();

        $lineNo = 1;
        foreach ($lines as $line) {
            $item = Item::query()->findOrFail((int) $line['item_id']);
            $uom = Uom::query()->findOrFail((int) $line['uom_id']);

            $po->lines()->create([
                'line_no' => $lineNo++,
                'item_id' => $item->id,
                'quantity' => $line['quantity'],
                'uom_id' => $uom->id,
                'unit_price' => (float) Arr::get($line, 'unit_price', 0),
                'item_snapshot' => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                ],
                'uom_snapshot' => [
                    'id' => $uom->id,
                    'code' => $uom->code,
                    'name' => $uom->name,
                ],
                'remarks' => Arr::get($line, 'remarks'),
            ]);
        }
    }

    private function nextApprovalStep(PurchaseOrder $po): string
    {
        // Determine progress by checking status history meta.step.
        $steps = PurchaseOrderStatusHistory::query()
            ->where('purchase_order_id', $po->id)
            ->where('action', 'approve')
            ->orderBy('id')
            ->pluck('meta');

        $done = [];
        foreach ($steps as $meta) {
            if (is_array($meta) && isset($meta['step'])) {
                $done[] = (string) $meta['step'];
            }
        }

        if (!in_array('finance', $done, true)) {
            return 'finance';
        }
        if (!in_array('gm', $done, true)) {
            return 'gm';
        }

        return 'director';
    }

    private function recordStatusHistory(
        PurchaseOrder $po,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        ?User $actor,
        array $meta = [],
    ): void {
        PurchaseOrderStatusHistory::query()->create([
            'purchase_order_id' => $po->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action' => $action,
            'actor_user_id' => $actor?->id,
            'meta' => empty($meta) ? null : $meta,
            'created_at' => now(),
        ]);
    }

    private function loadForShow(PurchaseOrder $po): PurchaseOrder
    {
        return $po->load([
            'supplier',
            'lines.item',
            'lines.uom',
            'purchaseRequests.department',
            'purchaseRequests.requester',
            'statusHistories.actor',
            'submittedBy',
            'approvedBy',
        ]);
    }
}
