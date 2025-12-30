<?php

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse', 'procurement']);
    }

    public function view(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse']);
    }

    public function update(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse'])
            && $goodsReceipt->status === GoodsReceipt::STATUS_DRAFT;
    }

    public function post(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse'])
            && $goodsReceipt->status === GoodsReceipt::STATUS_DRAFT;
    }

    public function cancel(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse'])
            && $goodsReceipt->status === GoodsReceipt::STATUS_DRAFT;
    }
}
