<?php

namespace App\Services\Approval;

use App\Models\Approval;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\User;
use App\Notifications\Approval\ApprovalRequiredNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            $approval = Approval::create([
                'approval_workflow_id' => $workflow->id,
                'approval_workflow_step_id' => $step->id,
                'approvable_type' => get_class($approvable),
                'approvable_id' => $approvable->id,
                'status' => Approval::STATUS_PENDING,
                'assigned_to_user_id' => $assignedToUserId,
                'assigned_to_role' => $assignedToRole,
            ]);

            // Send notification to approver(s)
            $this->notifyApprovers($approval, $approvable);
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

            // Send notification to document creator
            $this->notifyApproved($approval, $user);
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

            // Send notification to document creator
            $this->notifyRejected($approval, $user);
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

    /**
     * Send notification to approvers when approval is initiated.
     *
     * @param Approval $approval
     * @param Model $approvable
     * @return void
     */
    private function notifyApprovers(Approval $approval, Model $approvable): void
    {
        try {
            $approvers = $this->getApproversForNotification($approval);

            foreach ($approvers as $approver) {
                $approver->notify(new ApprovalRequiredNotification(
                    $approval,
                    $approvable,
                    $approver
                ));
            }

            Log::info('Approval required notification sent', [
                'approval_id' => $approval->id,
                'approvable_type' => get_class($approvable),
                'approvable_id' => $approvable->id,
                'approver_count' => count($approvers),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send approval required notification', [
                'approval_id' => $approval->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification when approval is approved.
     *
     * @param Approval $approval
     * @param User $approver
     * @return void
     */
    private function notifyApproved(Approval $approval, User $approver): void
    {
        try {
            $approvable = $approval->approvable;

            // Get document creator
            $creator = $this->getDocumentCreator($approvable);

            if (!$creator) {
                return;
            }

            // Check if this is the final approval
            $isFinalApproval = $this->isWorkflowComplete($approvable);

            $creator->notify(new \App\Notifications\Approval\DocumentApprovedNotification(
                $approval,
                $approvable,
                $approver,
                $isFinalApproval
            ));

            Log::info('Document approved notification sent', [
                'approval_id' => $approval->id,
                'approvable_type' => get_class($approvable),
                'approvable_id' => $approvable->id,
                'is_final' => $isFinalApproval,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send document approved notification', [
                'approval_id' => $approval->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification when approval is rejected.
     *
     * @param Approval $approval
     * @param User $rejector
     * @return void
     */
    private function notifyRejected(Approval $approval, User $rejector): void
    {
        try {
            $approvable = $approval->approvable;

            // Get document creator
            $creator = $this->getDocumentCreator($approvable);

            if (!$creator) {
                return;
            }

            $creator->notify(new \App\Notifications\Approval\DocumentRejectedNotification(
                $approval,
                $approvable,
                $rejector
            ));

            Log::info('Document rejected notification sent', [
                'approval_id' => $approval->id,
                'approvable_type' => get_class($approvable),
                'approvable_id' => $approvable->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send document rejected notification', [
                'approval_id' => $approval->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get approvers for notification.
     *
     * @param Approval $approval
     * @return array
     */
    private function getApproversForNotification(Approval $approval): array
    {
        $approvers = [];

        // If assigned to specific user
        if ($approval->assigned_to_user_id) {
            $user = User::find($approval->assigned_to_user_id);
            if ($user) {
                $approvers[] = $user;
            }
        }

        // If assigned to role
        if ($approval->assigned_to_role) {
            $roleUsers = User::role($approval->assigned_to_role)->get();
            foreach ($roleUsers as $user) {
                $approvers[] = $user;
            }
        }

        return $approvers;
    }

    /**
     * Get document creator.
     *
     * @param Model $approvable
     * @return User|null
     */
    private function getDocumentCreator(Model $approvable): ?User
    {
        // Try to get creator from created_by_user_id
        if (isset($approvable->created_by_user_id)) {
            return User::find($approvable->created_by_user_id);
        }

        // Try to get creator from creator relationship
        if (method_exists($approvable, 'creator')) {
            return $approvable->creator;
        }

        // Try to get from created_by_user relationship
        if (method_exists($approvable, 'createdByUser')) {
            return $approvable->createdByUser;
        }

        return null;
    }
}
