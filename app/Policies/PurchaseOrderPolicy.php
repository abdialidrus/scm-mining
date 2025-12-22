<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        // minimal for now
        return $user->hasAnyRole(['super_admin', 'procurement', 'finance', 'gm', 'director']);
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
        // Enforced in service per-step; policy only checks role and state.
        if (!in_array($purchaseOrder->status, [PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_IN_APPROVAL], true)) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'finance', 'gm', 'director']);
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
