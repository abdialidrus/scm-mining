<?php

namespace App\Policies;

use App\Models\PutAway;
use App\Models\User;

class PutAwayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse']);
    }

    public function view(User $user, PutAway $putAway): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse']);
    }

    public function post(User $user, PutAway $putAway): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse'])
            && $putAway->status === PutAway::STATUS_DRAFT;
    }

    public function cancel(User $user, PutAway $putAway): bool
    {
        return $user->hasAnyRole(['super_admin', 'warehouse'])
            && $putAway->status === PutAway::STATUS_DRAFT;
    }
}
