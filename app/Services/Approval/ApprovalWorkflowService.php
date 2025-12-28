<?php

namespace App\Services\Approval;

use App\Models\Approval;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalWorkflowService
{
    /**
     * Initialize approval workflow for a document.
     *
     * Creates approval instances for all applicable steps based on the workflow configuration.
     *
     * @param Model $approvable The model that needs approval (e.g., PurchaseOrder)
     * @param string $workflowCode The workflow code to use
     * @throws ValidationException If workflow not found or inactive
     */
    public function initiate(Model $approvable, string $workflowCode): void
    {
        $workflow = ApprovalWorkflow::query()
            ->where('code', $workflowCode)
            ->where('is_active', true)
            ->with('orderedSteps')
            ->first();

        if (!$workflow) {
            throw ValidationException::withMessages([
                'workflow' => "Approval workflow '{$workflowCode}' not found or inactive.",
            ]);
        }

        $steps = $workflow->orderedSteps;

        foreach ($steps as $step) {
            // Check if step condition is met
            if (!$this->evaluateStepCondition($step, $approvable)) {
                continue; // Skip this step
            }

            // Resolve approver
            $assignedToUserId = null;
            $assignedToRole = null;

            if ($step->approver_type === ApprovalWorkflowStep::APPROVER_TYPE_ROLE) {
                $assignedToRole = $step->approver_value;
            } elseif ($step->approver_type === ApprovalWorkflowStep::APPROVER_TYPE_USER) {
                $assignedToUserId = (int) $step->approver_value;
            } elseif ($step->approver_type === ApprovalWorkflowStep::APPROVER_TYPE_DEPARTMENT_HEAD) {
                // Resolve department head from approvable model
                $assignedToUserId = $this->resolveDepartmentHead($approvable);
            }

            // Create approval instance
            Approval::create([
                'approval_workflow_id' => $workflow->id,
                'approval_workflow_step_id' => $step->id,
                'approvable_type' => get_class($approvable),
                'approvable_id' => $approvable->id,
                'status' => Approval::STATUS_PENDING,
                'assigned_to_user_id' => $assignedToUserId,
                'assigned_to_role' => $assignedToRole,
            ]);
        }
    }

    /**
     * Get the next pending approval for a document.
     *
     * @param Model $approvable
     * @return Approval|null
     */
    public function getNextPendingApproval(Model $approvable): ?Approval
    {
        return Approval::query()
            ->with(['step', 'assignedToUser'])
            ->where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->where('status', Approval::STATUS_PENDING)
            ->orderBy('id')
            ->first();
    }

    /**
     * Get all approvals for a document.
     *
     * @param Model $approvable
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getApprovals(Model $approvable)
    {
        return Approval::query()
            ->with(['step', 'assignedToUser', 'approvedBy', 'rejectedBy'])
            ->where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * Check if user can approve the given approval.
     *
     * @param User $user
     * @param Approval $approval
     * @return bool
     */
    public function canApprove(User $user, Approval $approval): bool
    {
        if (!$approval->isPending()) {
            return false;
        }

        // Check by specific user assignment
        if ($approval->assigned_to_user_id) {
            return (int) $user->id === (int) $approval->assigned_to_user_id;
        }

        // Check by role assignment
        if ($approval->assigned_to_role) {
            return $user->hasRole($approval->assigned_to_role);
        }

        return false;
    }

    /**
     * Approve an approval step.
     *
     * @param User $user
     * @param Approval $approval
     * @param string|null $comments
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function approve(User $user, Approval $approval, ?string $comments = null): void
    {
        if (!$approval->isPending()) {
            throw ValidationException::withMessages([
                'approval' => 'This approval is not in pending state.',
            ]);
        }

        if (!$this->canApprove($user, $approval)) {
            throw new AuthorizationException('You are not authorized to approve this step.');
        }

        DB::transaction(function () use ($user, $approval, $comments) {
            $approval->update([
                'status' => Approval::STATUS_APPROVED,
                'approved_by_user_id' => $user->id,
                'approved_at' => now(),
                'comments' => $comments,
            ]);
        });
    }

    /**
     * Reject an approval step.
     *
     * @param User $user
     * @param Approval $approval
     * @param string $reason
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function reject(User $user, Approval $approval, string $reason): void
    {
        if (!$approval->isPending()) {
            throw ValidationException::withMessages([
                'approval' => 'This approval is not in pending state.',
            ]);
        }

        if (!$this->canApprove($user, $approval)) {
            throw new AuthorizationException('You are not authorized to reject this step.');
        }

        DB::transaction(function () use ($user, $approval, $reason) {
            $approval->update([
                'status' => Approval::STATUS_REJECTED,
                'rejected_by_user_id' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            // Cancel all remaining pending approvals for this document
            $this->cancelRemainingApprovals($approval->approvable);
        });
    }

    /**
     * Check if the workflow for a document is complete (all steps approved).
     *
     * @param Model $approvable
     * @return bool
     */
    public function isWorkflowComplete(Model $approvable): bool
    {
        $pendingCount = Approval::query()
            ->where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->where('status', Approval::STATUS_PENDING)
            ->count();

        return $pendingCount === 0;
    }

    /**
     * Check if the workflow for a document is rejected.
     *
     * @param Model $approvable
     * @return bool
     */
    public function isWorkflowRejected(Model $approvable): bool
    {
        return Approval::query()
            ->where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->where('status', Approval::STATUS_REJECTED)
            ->exists();
    }

    /**
     * Cancel all pending approvals for a document.
     *
     * @param Model $approvable
     */
    public function cancelRemainingApprovals(Model $approvable): void
    {
        Approval::query()
            ->where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->where('status', Approval::STATUS_PENDING)
            ->update([
                'status' => Approval::STATUS_CANCELLED,
            ]);
    }

    /**
     * Evaluate if a step's condition is met.
     *
     * @param ApprovalWorkflowStep $step
     * @param Model $approvable
     * @return bool
     */
    private function evaluateStepCondition(ApprovalWorkflowStep $step, Model $approvable): bool
    {
        if (!$step->condition_field) {
            return true; // No condition = always apply
        }

        // Get field value from model (supports dot notation)
        $fieldValue = data_get($approvable, $step->condition_field);
        $conditionValue = $step->condition_value;

        switch ($step->condition_operator) {
            case '>':
                return $fieldValue > $conditionValue;
            case '>=':
                return $fieldValue >= $conditionValue;
            case '<':
                return $fieldValue < $conditionValue;
            case '<=':
                return $fieldValue <= $conditionValue;
            case '=':
            case '==':
                return $fieldValue == $conditionValue;
            case '!=':
                return $fieldValue != $conditionValue;
            case 'IN':
                $values = is_array($conditionValue) ? $conditionValue : json_decode($conditionValue, true);
                return in_array($fieldValue, $values ?? [], false);
            case 'NOT_IN':
                $values = is_array($conditionValue) ? $conditionValue : json_decode($conditionValue, true);
                return !in_array($fieldValue, $values ?? [], false);
            default:
                return true;
        }
    }

    /**
     * Resolve department head user ID from approvable model.
     *
     * @param Model $approvable
     * @return int|null
     */
    private function resolveDepartmentHead(Model $approvable): ?int
    {
        // Try to get department from approvable
        if (method_exists($approvable, 'department')) {
            $department = $approvable->department;
            if ($department && $department->head_user_id) {
                return (int) $department->head_user_id;
            }
        }

        return null;
    }
}
