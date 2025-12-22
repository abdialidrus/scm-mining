<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequestLine extends Model
{
    use HasFactory;

    protected $table = 'purchase_request_items';

    protected $fillable = [
        'purchase_request_id',
        'line_no',
        'item_id',
        'quantity',
        'uom_id',
        'remark',
    ];

    protected $appends = ['remarks'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'line_no' => 'integer',
        ];
    }

    public function getRemarksAttribute(): ?string
    {
        return $this->attributes['remark'] ?? null;
    }

    public function setRemarksAttribute(?string $value): void
    {
        $this->attributes['remark'] = $value;
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }
}
