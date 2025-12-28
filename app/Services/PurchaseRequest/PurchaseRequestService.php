<?php

namespace App\Services\PurchaseRequest;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\PurchaseRequestStatusHistory;
use App\Models\User;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function __construct(
        private readonly PurchaseRequestNumberGenerator $numberGenerator,
        private readonly ApprovalWorkflowService $approvalWorkflowService,
    ) {}

    /**
     * @param array{department_id:int,remarks?:string|null,lines:array<int,array{item_id:int,quantity:numeric,uom_id:int,remarks?:string|null}>} $data
     */
    public function createDraft(User $actor, array $data): PurchaseRequest
    {
        return DB::transaction(function () use ($actor, $data) {
            if (!$actor->department_id) {
                throw ValidationException::withMessages([
                    'department_id' => 'User is not assigned to any department.',
                ]);
            }

            $departmentId = (int) $data['department_id'];
            if ($departmentId !== (int) $actor->department_id) {
                throw new AuthorizationException('Cannot create PR for another department.');
            }

            $pr = new PurchaseRequest();
            $pr->pr_number = $this->numberGenerator->generate();
            $pr->requester_user_id = $actor->id;
            $pr->department_id = $departmentId;
            $pr->status = PurchaseRequest::STATUS_DRAFT;
            $pr->remarks = Arr::get($data, 'remarks');
            $pr->save();

            $this->syncLines($pr, $data['lines']);

            return $pr->load(['lines.item', 'lines.uom', 'department', 'requester']);
        });
    }

    /**
     * Update draft PR lines + remarks.
     *
     * @param array{remarks?:string|null,lines:array<int,array{item_id:int,quantity:numeric,uom_id:int,remarks?:string|null}>} $data
     */
    public function updateDraft(User $actor, int $purchaseRequestId, array $data): PurchaseRequest
    {
        return DB::transaction(function () use ($actor, $purchaseRequestId, $data) {
            /** @var PurchaseRequest $pr */
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequestId);

            if ((int) $pr->requester_user_id !== (int) $actor->id) {
                throw new AuthorizationException('Only requester can update this PR.');
            }

            if ($pr->status !== PurchaseRequest::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only DRAFT PR can be updated.',
                ]);
            }

            $pr->remarks = Arr::get($data, 'remarks', $pr->remarks);
            $pr->save();

            $this->syncLines($pr, $data['lines']);

            return $pr->load(['lines.item', 'lines.uom', 'department', 'requester']);
        });
    }

    public function submit(User $actor, int $purchaseRequestId): PurchaseRequest
    {
        return DB::transaction(function () use ($actor, $purchaseRequestId) {
            /** @var PurchaseRequest $pr */
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequestId);

            if ((int) $pr->requester_user_id !== (int) $actor->id) {
                throw new AuthorizationException('Only requester can submit this PR.');
            }

            if ($pr->status !== PurchaseRequest::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only DRAFT PR can be submitted.',
                ]);
            }

            if ($pr->lines()->count() < 1) {
                throw ValidationException::withMessages([
                    'lines' => 'PR must have at least one line item before submitting.',
                ]);
            }

            $fromStatus = $pr->status;

            $pr->status = PurchaseRequest::STATUS_PENDING_APPROVAL;
            $pr->submitted_at = now();
            $pr->submitted_by_user_id = $actor->id;
            $pr->save();

            $this->recordStatusHistory(
                pr: $pr,
                fromStatus: $fromStatus,
                toStatus: $pr->status,
                action: 'submit',
                actor: $actor,
            );

            // Initiate approval workflow
            try {
                $this->approvalWorkflowService->initiate(
                    approvable: $pr,
                    workflowCode: 'PR_STANDARD'
                );
            } catch (\Exception $e) {
                // Log error but don't block submission
                // Fallback: PR will be in PENDING_APPROVAL without workflow
                Log::warning('Failed to initiate PR approval workflow', [
                    'pr_id' => $pr->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $pr->load(['lines.item', 'lines.uom', 'department.head', 'requester', 'approvals.step', 'approvals.approver']);
        });
    }

    public function approve(User $actor, int $purchaseRequestId, ?string $comments = null): PurchaseRequest
    {
        return DB::transaction(function () use ($actor, $purchaseRequestId, $comments) {
            /** @var PurchaseRequest $pr */
            $pr = PurchaseRequest::query()
                ->with(['department', 'approvals'])
                ->lockForUpdate()
                ->findOrFail($purchaseRequestId);

            if ($pr->status !== PurchaseRequest::STATUS_PENDING_APPROVAL) {
                throw ValidationException::withMessages([
                    'status' => 'Only PENDING_APPROVAL PR can be approved.',
                ]);
            }

            // Rule: requester cannot approve their own PR.
            if ((int) $pr->requester_user_id === (int) $actor->id) {
                throw new AuthorizationException('Requester cannot approve their own PR.');
            }

            // Get the current pending approval for this user
            $approval = $this->approvalWorkflowService->getNextPendingApproval($pr);

            if (!$approval) {
                throw ValidationException::withMessages([
                    'approval' => 'No pending approval found for this PR.',
                ]);
            }

            // Check if user can approve this step
            if (!$this->approvalWorkflowService->canApprove($actor, $approval)) {
                throw new AuthorizationException('You are not authorized to approve this PR at this stage.');
            }

            $fromStatus = $pr->status;

            // Approve the current step
            $this->approvalWorkflowService->approve($actor, $approval, $comments);

            // Check if workflow is complete
            if ($this->approvalWorkflowService->isWorkflowComplete($pr)) {
                $pr->status = PurchaseRequest::STATUS_APPROVED;
                $pr->approved_at = now();
                $pr->approved_by_user_id = $actor->id;
                $pr->save();

                $this->recordStatusHistory(
                    pr: $pr,
                    fromStatus: $fromStatus,
                    toStatus: $pr->status,
                    action: 'approve',
                    actor: $actor
                );
            }

            return $pr->load(['lines.item', 'lines.uom', 'department.head', 'requester', 'approvals.step', 'approvals.approver']);
        });
    }

    /**
     * Reject a PR approval.
     *
     * Note: authorization rule mirrors approval (requester cannot reject own PR).
     */
    public function reject(User $actor, int $purchaseRequestId, string $reason): PurchaseRequest
    {
        return DB::transaction(function () use ($actor, $purchaseRequestId, $reason) {
            /** @var PurchaseRequest $pr */
            $pr = PurchaseRequest::query()
                ->with(['department', 'approvals'])
                ->lockForUpdate()
                ->findOrFail($purchaseRequestId);

            if ($pr->status !== PurchaseRequest::STATUS_PENDING_APPROVAL) {
                throw ValidationException::withMessages([
                    'status' => 'Only PENDING_APPROVAL PR can be rejected.',
                ]);
            }

            if ((int) $pr->requester_user_id === (int) $actor->id) {
                throw new AuthorizationException('Requester cannot reject their own PR.');
            }

            // Get the current pending approval for this user
            $approval = $this->approvalWorkflowService->getNextPendingApproval($pr);

            if (!$approval) {
                throw ValidationException::withMessages([
                    'approval' => 'No pending approval found for this PR.',
                ]);
            }

            // Check if user can reject this step
            if (!$this->approvalWorkflowService->canApprove($actor, $approval)) {
                throw new AuthorizationException('You are not authorized to reject this PR at this stage.');
            }

            $fromStatus = $pr->status;

            // Reject the approval (this will cancel remaining approvals)
            $this->approvalWorkflowService->reject($actor, $approval, $reason);

            // Update PR status to REJECTED
            $pr->status = PurchaseRequest::STATUS_REJECTED;
            $pr->save();

            $this->recordStatusHistory(
                pr: $pr,
                fromStatus: $fromStatus,
                toStatus: $pr->status,
                action: 'reject',
                actor: $actor,
                meta: ['reason' => $reason],
            );

            return $pr->load(['lines.item', 'lines.uom', 'department.head', 'requester']);
        });
    }

    // NOTE: PR -> PO conversion is handled by PurchaseOrderService when creating a PO from PRs.

    private function recordStatusHistory(
        PurchaseRequest $pr,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        ?User $actor,
        array $meta = [],
    ): void {
        PurchaseRequestStatusHistory::query()->create([
            'purchase_request_id' => $pr->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action' => $action,
            'actor_user_id' => $actor?->id,
            'meta' => empty($meta) ? null : $meta,
            'created_at' => now(),
        ]);
    }

    /**
     * @param array<int,array{item_id:int,quantity:numeric,uom_id:int,remarks?:string|null}> $lines
     */
    private function syncLines(PurchaseRequest $pr, array $lines): void
    {
        // replace-all strategy to keep it simple and audit-friendly. Later we can store revisions.
        $pr->lines()->delete();

        $lineNo = 1;
        foreach ($lines as $line) {
            $pr->lines()->create([
                'line_no' => $lineNo++,
                'item_id' => (int) $line['item_id'],
                'quantity' => $line['quantity'],
                'uom_id' => (int) $line['uom_id'],
                'remarks' => Arr::get($line, 'remarks'),
            ]);
        }
    }
}
