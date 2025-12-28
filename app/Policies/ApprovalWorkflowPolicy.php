<?php

namespace App\Policies;

use App\Models\ApprovalWorkflow;
use App\Models\User;

class ApprovalWorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function view(User $user, ApprovalWorkflow $approvalWorkflow): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, ApprovalWorkflow $approvalWorkflow): bool
    {
        return $user->hasRole('super_admin');
    }

    public function delete(User $user, ApprovalWorkflow $approvalWorkflow): bool
    {
        return $user->hasRole('super_admin');
    }
}
