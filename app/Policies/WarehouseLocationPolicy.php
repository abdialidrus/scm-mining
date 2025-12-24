<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarehouseLocation;

class WarehouseLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse', 'warehouse_supervisor']);
    }

    public function view(User $user, WarehouseLocation $location): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse', 'warehouse_supervisor']);
    }

    public function update(User $user, WarehouseLocation $location): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse', 'warehouse_supervisor']);
    }

    public function delete(User $user, WarehouseLocation $location): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse', 'warehouse_supervisor']);
    }
}
