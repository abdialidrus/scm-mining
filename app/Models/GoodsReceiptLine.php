<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_receipt_id',
        'line_no',
        'purchase_order_line_id',
        'item_id',
        'uom_id',
        'ordered_quantity',
        'received_quantity',
        'serial_numbers',
        'item_snapshot',
        'uom_snapshot',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'ordered_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'serial_numbers' => 'array',
            'item_snapshot' => 'array',
            'uom_snapshot' => 'array',
        ];
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderLine()
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function serialNumbers()
    {
        return $this->hasMany(ItemSerialNumber::class, 'goods_receipt_line_id');
    }
}
