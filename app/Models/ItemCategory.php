<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'parent_id',
        'is_active',
        'requires_approval',
        'color_code',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_approval' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Parent category (for hierarchy)
     */
    public function parent()
    {
        return $this->belongsTo(ItemCategory::class, 'parent_id');
    }

    /**
     * Child categories
     */
    public function children()
    {
        return $this->hasMany(ItemCategory::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Items in this category
     */
    public function items()
    {
        return $this->hasMany(Item::class, 'item_category_id');
    }

    /**
     * Get full path of category (for breadcrumb)
     * e.g., "Spare Parts > Engine Parts"
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Scope: only active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: root categories (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get all descendant category IDs (including self)
     */
    public function getAllDescendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }

    /**
     * Static method to get all descendant IDs for multiple categories
     */
    public static function getDescendantIdsForCategories(array $categoryIds): array
    {
        $allIds = [];

        // Load all categories with their children recursively
        $categories = self::with(['children' => function ($query) {
            $query->with('children');
        }])->whereIn('id', $categoryIds)->get();

        foreach ($categories as $category) {
            $allIds = array_merge($allIds, $category->getAllDescendantIds());
        }

        return array_unique($allIds);
    }
}
