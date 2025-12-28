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

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }
}
