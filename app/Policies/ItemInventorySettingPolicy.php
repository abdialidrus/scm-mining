<?php

namespace App\Policies;

use App\Models\ItemInventorySetting;
use App\Models\User;

class ItemInventorySettingPolicy
{
    /**
     * Determine whether the user can view any item inventory settings.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'inventory-manager',
            'warehouse-manager',
            'procurement',
        ]);
    }

    /**
     * Determine whether the user can view the item inventory setting.
     */
    public function view(User $user, ItemInventorySetting $itemInventorySetting): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'inventory-manager',
            'warehouse-manager',
            'procurement',
        ]);
    }

    /**
     * Determine whether the user can create item inventory settings.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'inventory-manager',
            'warehouse-manager',
        ]);
    }

    /**
     * Determine whether the user can update the item inventory setting.
     */
    public function update(User $user, ItemInventorySetting $itemInventorySetting): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'inventory-manager',
            'warehouse-manager',
        ]);
    }

    /**
     * Determine whether the user can delete the item inventory setting.
     */
    public function delete(User $user, ItemInventorySetting $itemInventorySetting): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'inventory-manager',
        ]);
    }
}
