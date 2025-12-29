<?php

namespace App\Policies;

use App\Models\PickingOrder;
use App\Models\User;

class PickingOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse']);
    }

    public function view(User $user, PickingOrder $pickingOrder): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse']);
    }

    public function post(User $user, PickingOrder $pickingOrder): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse'])
            && $pickingOrder->status === PickingOrder::STATUS_DRAFT;
    }

    public function cancel(User $user, PickingOrder $pickingOrder): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse'])
            && $pickingOrder->status === PickingOrder::STATUS_DRAFT;
    }
}
