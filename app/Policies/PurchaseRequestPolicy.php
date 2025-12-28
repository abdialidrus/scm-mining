<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        // Minimal rule for now:
        // - requester can view
        // - any user in the same department can view
        // (later we can extend for procurement/finance roles and approval workflow).
        return (int) $purchaseRequest->requester_user_id === (int) $user->id
            || ((int) $purchaseRequest->department_id === (int) $user->department_id && $user->department_id !== null);
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return (int) $purchaseRequest->requester_user_id === (int) $user->id
            && $purchaseRequest->status === PurchaseRequest::STATUS_DRAFT;
    }

    public function submit(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return (int) $purchaseRequest->requester_user_id === (int) $user->id
            && $purchaseRequest->status === PurchaseRequest::STATUS_DRAFT;
    }

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        // Only PRs in PENDING_APPROVAL status can be approved
        if ($purchaseRequest->status !== PurchaseRequest::STATUS_PENDING_APPROVAL) {
            return false;
        }

        // Requester cannot approve own PR
        if ((int) $purchaseRequest->requester_user_id === (int) $user->id) {
            return false;
        }

        // Check if user can approve via approval workflow service
        // For now, allow if user is department head or has an assigned approval
        // The actual authorization will be checked in the service layer

        // Quick check: is user the department head?
        if ((int) $purchaseRequest->department?->head_user_id === (int) $user->id) {
            return true;
        }

        // Check if user has a pending approval assigned to them
        $hasPendingApproval = $purchaseRequest->approvals()
            ->where('status', 'PENDING')
            ->where(function ($query) use ($user) {
                $query->where('assigned_to_user_id', $user->id)
                    ->orWhereIn('assigned_to_role', $user->roles->pluck('name'));
            })
            ->exists();

        return $hasPendingApproval;
    }
}
