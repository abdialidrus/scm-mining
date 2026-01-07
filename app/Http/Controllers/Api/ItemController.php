<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Item\StoreItemRequest;
use App\Http\Requests\Api\Item\UpdateItemRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $categoryIds = $request->query('category_ids', []);

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = DB::table('items')
            ->leftJoin('uoms', 'uoms.id', '=', 'items.base_uom_id')
            ->leftJoin('item_categories', 'item_categories.id', '=', 'items.item_category_id')
            ->select([
                'items.id',
                'items.sku',
                'items.name',
                'items.is_serialized',
                'items.criticality_level',
                'items.base_uom_id',
                'items.item_category_id',
                DB::raw('uoms.code as base_uom_code'),
                DB::raw('uoms.name as base_uom_name'),
                DB::raw('item_categories.code as category_code'),
                DB::raw('item_categories.name as category_name'),
                DB::raw('item_categories.color_code as category_color'),
            ])
            ->orderBy('items.name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('items.sku', 'LIKE', '%' . $search . '%')
                    ->orWhere('items.name', 'LIKE', '%' . $search . '%');
            });
        }

        if (is_array($categoryIds) && count($categoryIds) > 0) {
            // Get all descendant category IDs (including parent categories themselves)
            $allCategoryIds = ItemCategory::getDescendantIdsForCategories($categoryIds);
            $query->whereIn('items.item_category_id', $allCategoryIds);
        }

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $paginator,
        ]);
    }

    public function show(Item $item): JsonResponse
    {
        $item->load(['baseUom', 'category.parent']);

        return response()->json([
            'data' => $item,
        ]);
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        $item = Item::create([
            'sku' => $request->sku,
            'name' => $request->name,
            'is_serialized' => $request->boolean('is_serialized', false),
            'criticality_level' => $request->criticality_level,
            'base_uom_id' => $request->base_uom_id,
            'item_category_id' => $request->item_category_id,
        ]);

        $item->load(['baseUom', 'category']);

        return response()->json([
            'message' => 'Item created successfully',
            'data' => $item,
        ], 201);
    }

    public function update(UpdateItemRequest $request, Item $item): JsonResponse
    {
        $item->update([
            'sku' => $request->sku,
            'name' => $request->name,
            'is_serialized' => $request->boolean('is_serialized', false),
            'criticality_level' => $request->criticality_level,
            'base_uom_id' => $request->base_uom_id,
            'item_category_id' => $request->item_category_id,
        ]);

        $item->load(['baseUom', 'category']);

        return response()->json([
            'message' => 'Item updated successfully',
            'data' => $item,
        ]);
    }

    public function destroy(Item $item): JsonResponse
    {
        // Check if item is used in any transactions
        $usageChecks = [
            'purchase_request_lines' => $item->hasMany(\App\Models\PurchaseRequestLine::class)->exists(),
            'purchase_order_lines' => $item->hasMany(\App\Models\PurchaseOrderLine::class)->exists(),
            'goods_receipt_lines' => $item->hasMany(\App\Models\GoodsReceiptLine::class)->exists(),
        ];

        $inUse = array_filter($usageChecks);

        if (!empty($inUse)) {
            return response()->json([
                'message' => 'Cannot delete item. It is being used in: ' . implode(', ', array_keys($inUse)),
            ], 422);
        }

        $item->delete();

        return response()->json([
            'message' => 'Item deleted successfully',
        ]);
    }
}
