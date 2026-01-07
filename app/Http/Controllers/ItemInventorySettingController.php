<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemInventorySettingRequest;
use App\Http\Requests\UpdateItemInventorySettingRequest;
use App\Models\ItemInventorySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ItemInventorySettingController extends Controller
{
    /**
     * Display a listing of the item inventory settings.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ItemInventorySetting::class);

        $query = ItemInventorySetting::with(['item:id,sku,name', 'warehouse:id,code,name'])
            ->orderBy('created_at', 'desc');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('item', function ($itemQuery) use ($search) {
                    $itemQuery->where('sku', 'LIKE', "%{$search}%")
                        ->orWhere('name', 'LIKE', "%{$search}%");
                })
                    ->orWhereHas('warehouse', function ($whQuery) use ($search) {
                        $whQuery->where('code', 'LIKE', "%{$search}%")
                            ->orWhere('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Item filter
        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        // Warehouse filter
        if ($request->filled('warehouse_id')) {
            if ($request->warehouse_id === 'global') {
                $query->whereNull('warehouse_id');
            } else {
                $query->where('warehouse_id', $request->warehouse_id);
            }
        }

        // Active status filter
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $settings = $query->paginate($request->per_page ?? 15);

        if ($request->wantsJson()) {
            return response()->json($settings);
        }

        return Inertia::render('MasterData/ItemInventorySettings/Index', [
            'settings' => $settings,
            'filters' => $request->only(['search', 'item_id', 'warehouse_id', 'is_active']),
        ]);
    }

    /**
     * Show the form for creating a new item inventory setting.
     */
    public function create()
    {
        $this->authorize('create', ItemInventorySetting::class);

        return Inertia::render('MasterData/ItemInventorySettings/Form', [
            'isEdit' => false,
        ]);
    }

    /**
     * Store a newly created item inventory setting.
     */
    public function store(StoreItemInventorySettingRequest $request)
    {
        $setting = ItemInventorySetting::create($request->validated());

        $setting->load(['item:id,sku,name', 'warehouse:id,code,name']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Item inventory setting created successfully',
                'data' => $setting,
            ], 201);
        }

        return redirect()
            ->route('item-inventory-settings.index')
            ->with('success', 'Item inventory setting created successfully');
    }

    /**
     * Display the specified item inventory setting.
     */
    public function show(ItemInventorySetting $itemInventorySetting)
    {
        $this->authorize('view', $itemInventorySetting);

        $itemInventorySetting->load(['item:id,sku,name', 'warehouse:id,code,name']);

        if (request()->wantsJson()) {
            return response()->json($itemInventorySetting);
        }

        return Inertia::render('MasterData/ItemInventorySettings/Show', [
            'setting' => $itemInventorySetting,
        ]);
    }

    /**
     * Show the form for editing the specified item inventory setting.
     */
    public function edit(ItemInventorySetting $itemInventorySetting)
    {
        $this->authorize('update', $itemInventorySetting);

        $itemInventorySetting->load(['item:id,sku,name', 'warehouse:id,code,name']);

        return Inertia::render('MasterData/ItemInventorySettings/Form', [
            'setting' => $itemInventorySetting,
            'isEdit' => true,
        ]);
    }

    /**
     * Update the specified item inventory setting.
     */
    public function update(UpdateItemInventorySettingRequest $request, ItemInventorySetting $itemInventorySetting)
    {
        $itemInventorySetting->update($request->validated());

        $itemInventorySetting->load(['item:id,sku,name', 'warehouse:id,code,name']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Item inventory setting updated successfully',
                'data' => $itemInventorySetting,
            ]);
        }

        return redirect()
            ->route('item-inventory-settings.index')
            ->with('success', 'Item inventory setting updated successfully');
    }

    /**
     * Remove the specified item inventory setting.
     */
    public function destroy(ItemInventorySetting $itemInventorySetting)
    {
        $this->authorize('delete', $itemInventorySetting);

        $itemInventorySetting->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Item inventory setting deleted successfully',
            ]);
        }

        return redirect()
            ->route('item-inventory-settings.index')
            ->with('success', 'Item inventory setting deleted successfully');
    }

    /**
     * Get inventory settings for a specific item (all warehouses).
     */
    public function byItem(Request $request, int $itemId)
    {
        $this->authorize('viewAny', ItemInventorySetting::class);

        $settings = ItemInventorySetting::with(['warehouse:id,code,name'])
            ->where('item_id', $itemId)
            ->get();

        return response()->json($settings);
    }

    /**
     * Bulk update inventory settings for multiple warehouses.
     */
    public function bulkUpdate(Request $request)
    {
        $this->authorize('create', ItemInventorySetting::class);

        $request->validate([
            'settings' => 'required|array|min:1',
            'settings.*.id' => 'sometimes|exists:item_inventory_settings,id',
            'settings.*.item_id' => 'required|exists:items,id',
            'settings.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'settings.*.reorder_point' => 'required|numeric|min:0',
            'settings.*.reorder_quantity' => 'required|numeric|min:0',
            'settings.*.min_stock' => 'required|numeric|min:0',
            'settings.*.max_stock' => 'nullable|numeric|gt:settings.*.min_stock',
            'settings.*.lead_time_days' => 'required|integer|min:0|max:365',
            'settings.*.safety_stock' => 'required|numeric|min:0',
            'settings.*.is_active' => 'boolean',
            'settings.*.notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $results = [];

            foreach ($request->settings as $settingData) {
                if (isset($settingData['id'])) {
                    // Update existing
                    $setting = ItemInventorySetting::find($settingData['id']);
                    if ($setting) {
                        $setting->update($settingData);
                        $results[] = $setting;
                    }
                } else {
                    // Create new
                    $setting = ItemInventorySetting::create($settingData);
                    $results[] = $setting;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Bulk update completed successfully',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Bulk update failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
