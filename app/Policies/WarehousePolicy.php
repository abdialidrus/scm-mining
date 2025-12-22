<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse', 'procurement']);
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse']);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse']);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse']);
    }
}
