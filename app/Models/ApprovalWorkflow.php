<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'model_type',
        'is_active',
    ];

    protected $appends = ['document_type'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get document_type attribute (alias for model_type).
     * Converts class name to constant format.
     */
    public function getDocumentTypeAttribute(): ?string
    {
        if (!$this->model_type) {
            return null;
        }

        // Convert class name to constant format
        // e.g., "App\Models\PurchaseOrder" -> "PURCHASE_ORDER"
        $map = [
            'App\Models\PurchaseRequest' => 'PURCHASE_REQUEST',
            'App\Models\PurchaseOrder' => 'PURCHASE_ORDER',
            'App\Models\GoodsReceipt' => 'GOODS_RECEIPT',
        ];

        return $map[$this->model_type] ?? $this->model_type;
    }

    /**
     * Set document_type attribute (alias for model_type).
     * Converts constant format to class name.
     */
    public function setDocumentTypeAttribute(?string $value): void
    {
        if (!$value) {
            $this->attributes['model_type'] = null;
            return;
        }

        // Convert constant format to class name
        // e.g., "PURCHASE_ORDER" -> "App\Models\PurchaseOrder"
        $map = [
            'PURCHASE_REQUEST' => 'App\Models\PurchaseRequest',
            'PURCHASE_ORDER' => 'App\Models\PurchaseOrder',
            'GOODS_RECEIPT' => 'App\Models\GoodsReceipt',
        ];

        $this->attributes['model_type'] = $map[$value] ?? $value;
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    /**
     * Get ordered steps for this workflow.
     */
    public function orderedSteps(): HasMany
    {
        return $this->steps()->orderBy('step_order');
    }
}
