<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Approval\ApprovalWorkflowService;

class PurchaseOrderPolicy
{
    public function __construct(
        private readonly ApprovalWorkflowService $approvalWorkflowService,
    ) {}

    public function viewAny(User $user): bool
    {
        // minimal for now
        return $user->hasAnyRole(['super_admin', 'procurement', 'finance', 'gm', 'director', 'warehouse']);
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'procurement']);
    }

    public function submit(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasAnyRole(['super_admin', 'procurement'])
            && $purchaseOrder->status === PurchaseOrder::STATUS_DRAFT;
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        // Check basic state
        if (!in_array($purchaseOrder->status, [PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_IN_APPROVAL], true)) {
            return false;
        }

        // ✨ NEW: Check via approval workflow service
        $nextApproval = $this->approvalWorkflowService->getNextPendingApproval($purchaseOrder);

        if (!$nextApproval) {
            return false; // No pending approval
        }

        return $this->approvalWorkflowService->canApprove($user, $nextApproval);
    }

    public function reject(User $user, PurchaseOrder $purchaseOrder): bool
    {
        // Same logic as approve - user can reject if they can approve
        return $this->approve($user, $purchaseOrder);
    }

    public function send(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasAnyRole(['super_admin', 'procurement'])
            && $purchaseOrder->status === PurchaseOrder::STATUS_APPROVED;
    }

    public function close(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasAnyRole(['super_admin', 'procurement'])
            && $purchaseOrder->status === PurchaseOrder::STATUS_SENT;
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasAnyRole(['super_admin', 'procurement'])
            && !in_array($purchaseOrder->status, [PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED], true);
    }

    public function updateDraft(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasAnyRole(['super_admin', 'procurement'])
            && $purchaseOrder->status === PurchaseOrder::STATUS_DRAFT;
    }

    public function reopen(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasAnyRole(['super_admin', 'procurement'])
            && $purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED;
    }
}
