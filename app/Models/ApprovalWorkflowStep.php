<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflowStep extends Model
{
    use HasFactory;

    public const APPROVER_TYPE_ROLE = 'ROLE';
    public const APPROVER_TYPE_USER = 'USER';
    public const APPROVER_TYPE_DEPARTMENT_HEAD = 'DEPARTMENT_HEAD';
    public const APPROVER_TYPE_DYNAMIC = 'DYNAMIC';

    protected $fillable = [
        'approval_workflow_id',
        'step_order',
        'step_code',
        'step_name',
        'step_description',
        'approver_type',
        'approver_value',
        'condition_field',
        'condition_operator',
        'condition_value',
        'is_required',
        'allow_skip',
        'allow_parallel',
        'meta',
    ];

    protected $appends = ['approver_role', 'approver_user_id', 'is_final_step'];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'is_required' => 'boolean',
            'allow_skip' => 'boolean',
            'allow_parallel' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * Get approver_role attribute (for ROLE type).
     */
    public function getApproverRoleAttribute(): ?string
    {
        return $this->approver_type === self::APPROVER_TYPE_ROLE
            ? $this->approver_value
            : null;
    }

    /**
     * Get approver_user_id attribute (for USER type).
     */
    public function getApproverUserIdAttribute(): ?int
    {
        return $this->approver_type === self::APPROVER_TYPE_USER
            ? (int) $this->approver_value
            : null;
    }

    /**
     * Get is_final_step attribute (derived from is_required and workflow position).
     */
    public function getIsFinalStepAttribute(): bool
    {
        // Consider it final if it's the last step in order
        $maxOrder = $this->workflow->steps()->max('step_order');
        return $this->step_order === $maxOrder;
    }

    /**
     * Set approver_role attribute.
     */
    public function setApproverRoleAttribute(?string $value): void
    {
        if ($this->approver_type === self::APPROVER_TYPE_ROLE) {
            $this->attributes['approver_value'] = $value;
        }
    }

    /**
     * Set approver_user_id attribute.
     */
    public function setApproverUserIdAttribute(?int $value): void
    {
        if ($this->approver_type === self::APPROVER_TYPE_USER) {
            $this->attributes['approver_value'] = (string) $value;
        }
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }
}
