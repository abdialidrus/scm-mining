<?php

namespace App\Models;

use App\Models\PurchaseRequestLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_CONVERTED_TO_PO = 'CONVERTED_TO_PO';

    protected $fillable = [
        'pr_number',
        'requester_user_id',
        'department_id',
        'status',
        'submitted_at',
        'submitted_by_user_id',
        'approved_at',
        'approved_by_user_id',
        'converted_to_po_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'converted_to_po_at' => 'datetime',
        ];
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function lines()
    {
        return $this->hasMany(PurchaseRequestLine::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(PurchaseRequestStatusHistory::class);
    }

    public function approvals()
    {
        return $this->morphMany(Approval::class, 'approvable');
    }
}
