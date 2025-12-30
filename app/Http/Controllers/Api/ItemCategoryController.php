<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ItemCategory\StoreItemCategoryRequest;
use App\Http\Requests\Api\ItemCategory\UpdateItemCategoryRequest;
use App\Models\ItemCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $isActive = $request->query('is_active');
        $parentId = $request->query('parent_id');

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = ItemCategory::query()
            ->with(['parent'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', '%' . $search . '%')
                    ->orWhere('name', 'ilike', '%' . $search . '%');
            });
        }

        if ($isActive !== null) {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOL));
        }

        if ($parentId !== null) {
            if ($parentId === 'null' || $parentId === '') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', (int) $parentId);
            }
        }

        return response()->json([
            'data' => $query->paginate($perPage)->withQueryString(),
        ]);
    }

    public function show(ItemCategory $itemCategory): JsonResponse
    {
        return response()->json([
            'data' => $itemCategory->load(['parent', 'children']),
        ]);
    }

    public function store(StoreItemCategoryRequest $request): JsonResponse
    {
        $category = ItemCategory::create($request->validated());

        return response()->json([
            'data' => $category->load(['parent']),
        ], 201);
    }

    public function update(UpdateItemCategoryRequest $request, ItemCategory $itemCategory): JsonResponse
    {
        // Prevent circular parent reference
        if ($request->has('parent_id') && $request->parent_id) {
            if ($request->parent_id == $itemCategory->id) {
                return response()->json([
                    'message' => 'A category cannot be its own parent',
                    'errors' => ['parent_id' => ['A category cannot be its own parent']],
                ], 422);
            }

            // Check if parent_id is a descendant of current category
            $parent = ItemCategory::find($request->parent_id);
            $ancestor = $parent?->parent;
            while ($ancestor) {
                if ($ancestor->id === $itemCategory->id) {
                    return response()->json([
                        'message' => 'Circular reference detected',
                        'errors' => ['parent_id' => ['Cannot set a descendant as parent']],
                    ], 422);
                }
                $ancestor = $ancestor->parent;
            }
        }

        $itemCategory->update($request->validated());

        return response()->json([
            'data' => $itemCategory->load(['parent']),
        ]);
    }

    public function destroy(ItemCategory $itemCategory): JsonResponse
    {
        // Check if category has items
        if ($itemCategory->items()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with items',
                'errors' => ['category' => ['This category has items assigned to it']],
            ], 422);
        }

        // Check if category has children
        if ($itemCategory->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with sub-categories',
                'errors' => ['category' => ['This category has sub-categories']],
            ], 422);
        }

        $itemCategory->delete();

        return response()->json([
            'message' => 'Item category deleted successfully',
        ]);
    }

    /**
     * Get all categories in tree structure (for dropdown/select)
     */
    public function tree(Request $request): JsonResponse
    {
        $isActive = $request->query('is_active');

        $query = ItemCategory::query()
            ->with(['children' => function ($q) use ($isActive) {
                $q->orderBy('sort_order')->orderBy('name');
                if ($isActive !== null) {
                    $q->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOL));
                }
            }])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($isActive !== null) {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOL));
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }
}
