<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'is_serialized',
        'criticality_level',
        'base_uom_id',
        'item_category_id',
    ];

    protected function casts(): array
    {
        return [
            'is_serialized' => 'boolean',
            'criticality_level' => 'integer',
        ];
    }

    public function baseUom()
    {
        return $this->belongsTo(Uom::class, 'base_uom_id');
    }

    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }
}
