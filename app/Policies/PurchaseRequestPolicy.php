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
        if ($purchaseRequest->status !== PurchaseRequest::STATUS_SUBMITTED) {
            return false;
        }

        // requester cannot approve own PR.
        if ((int) $purchaseRequest->requester_user_id === (int) $user->id) {
            return false;
        }

        return (int) $purchaseRequest->department?->head_user_id === (int) $user->id;
    }
}
