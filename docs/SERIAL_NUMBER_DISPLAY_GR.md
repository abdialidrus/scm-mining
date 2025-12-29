# Display Serial Numbers on Goods Receipt Show Page

## Overview

Added display of serial numbers on the Goods Receipt detail page for items that are serialized.

## Changes Made

### 1. Backend - GoodsReceiptController

**File**: `app/Http/Controllers/Api/GoodsReceiptController.php`

Added `lines.serialNumbers` to the eager loading in the `show()` method:

```php
public function show(GoodsReceipt $goodsReceipt): JsonResponse
{
    return response()->json([
        'data' => $goodsReceipt->load([
            'purchaseOrder.supplier',
            'warehouse',
            'lines.item',
            'lines.uom',
            'lines.serialNumbers',  // ← Added
            'statusHistories.actor',
        ]),
    ]);
}
```

### 2. Model - GoodsReceiptLine

**File**: `app/Models/GoodsReceiptLine.php`

Added `serialNumbers` relationship:

```php
public function serialNumbers()
{
    return $this->hasMany(ItemSerialNumber::class, 'goods_receipt_line_id');
}
```

### 3. TypeScript Types

**File**: `resources/js/services/goodsReceiptApi.ts`

Added `ItemSerialNumberDto` type and updated `GoodsReceiptLineDto`:

```typescript
export type ItemSerialNumberDto = {
    id: number;
    item_id: number;
    serial_number: string;
    status: string;
    current_location_id: number | null;
    received_at?: string | null;
    goods_receipt_line_id?: number | null;
    picked_at?: string | null;
    picking_order_line_id?: number | null;
};

export type GoodsReceiptLineDto = {
    // ...existing fields
    item?: {
        id: number;
        sku: string;
        name: string;
        is_serialized?: boolean; // ← Added
    } | null;
    serial_numbers?: ItemSerialNumberDto[]; // ← Added
};
```

### 4. UI - Goods Receipt Show Page

**File**: `resources/js/pages/goods-receipts/Show.vue`

Enhanced the line items display:

1. **Visual Distinction for Serialized Items**:
    - Blue background for serialized item lines
    - "Serialized" badge next to item name

2. **Serial Numbers Display**:
    - Shows count: "Serial Numbers (3)"
    - Displays each serial as a chip/tag
    - Monospace font for better readability
    - Border separator between item info and serials

```vue
<!-- Serial Numbers Display -->
<div
    v-if="l.item?.is_serialized && l.serial_numbers && l.serial_numbers.length > 0"
    class="mt-2 border-t pt-2"
>
    <div class="mb-2 text-xs font-medium text-muted-foreground">
        Serial Numbers ({{ l.serial_numbers.length }})
    </div>
    <div class="flex flex-wrap gap-2">
        <div
            v-for="serial in l.serial_numbers"
            :key="serial.id"
            class="inline-flex items-center rounded-md bg-primary/10 px-2.5 py-1 text-xs font-mono text-primary border border-primary/20"
        >
            {{ serial.serial_number }}
        </div>
    </div>
</div>
```

### 5. Bug Fix

**File**: `resources/js/pages/goods-receipts/Show.vue`

Fixed TypeScript property naming issue:

- Changed `gr.purchaseOrder` → `gr.purchase_order` (snake_case to match API)

## Visual Result

### Before

```
┌─────────────────────────────────────┐
│ ITM-LPT-001 — Laptop Dell           │
│ Ordered: 3 — Received: 3 PCS        │
└─────────────────────────────────────┘
```

### After (Serialized Item)

```
┌───────────────────────────────────────────────┐
│ ITM-LPT-001 — Laptop Dell [Serialized] ← Badge
│ Ordered: 3 — Received: 3 PCS                  │
│ ─────────────────────────────────────────     │
│ Serial Numbers (3)                            │
│ [SN-LPT-001] [SN-LPT-002] [SN-LPT-003]  ← Chips
└───────────────────────────────────────────────┘
```

## Features

- ✅ Shows serial numbers only for serialized items
- ✅ Blue background distinguishes serialized items
- ✅ "Serialized" badge for quick identification
- ✅ Serial count displayed
- ✅ Serial numbers shown as visual chips
- ✅ Monospace font for better readability
- ✅ Responsive layout (wraps on small screens)
- ✅ Dark mode compatible

## Testing Steps

1. Navigate to a Goods Receipt that has serialized items
2. Verify blue background on serialized item lines
3. Verify "Serialized" badge appears
4. Verify "Serial Numbers (X)" label appears
5. Verify all serial numbers are displayed as chips
6. Verify serial numbers are in monospace font
7. Test on dark mode
8. Test responsive layout on mobile

## Files Modified

1. `app/Http/Controllers/Api/GoodsReceiptController.php` - Added eager loading
2. `app/Models/GoodsReceiptLine.php` - Added serialNumbers relationship
3. `resources/js/services/goodsReceiptApi.ts` - Added types
4. `resources/js/pages/goods-receipts/Show.vue` - Enhanced UI
5. `docs/SERIAL_NUMBER_DISPLAY_GR.md` - This documentation

## Related Documentation

- `docs/SERIAL_NUMBER_TAG_INPUT.md` - Tag input implementation for GR Form
- `docs/SERIAL_NUMBER_COMPLETE.md` - Complete serial number flow
- `docs/SERIAL_NUMBER_FIXES.md` - Bug fixes for serial numbers
