# Serial Number Fixes Implementation

## Issues Fixed

### 1. ✅ Serial Number Input in Goods Receipt

**Problem:** Tidak ada input untuk serial numbers saat create Goods Receipt untuk serialized items.

**Solution:**

- Updated `GoodsReceiptForm.vue` to detect serialized items
- Added textarea input for serial numbers (one per line)
- Auto-calculate received quantity from serial numbers count
- Backend creates `ItemSerialNumber` records in RECEIVING location

**Files Changed:**

- `resources/js/pages/goods-receipts/Form.vue`
- `app/Services/GoodsReceipt/GoodsReceiptService.php`

**How it works:**

```
User receives 3 laptops:
1. Select PO line: "Laptop Dell Latitude 5420"
2. System detects: is_serialized = true
3. UI shows: Textarea for serial numbers
4. User enters:
   SN-LPT-2025-006
   SN-LPT-2025-007
   SN-LPT-2025-008
5. Qty auto-set to: 3
6. Save → Creates 3 serial number records with status=AVAILABLE, location=RECEIVING
```

### 2. ✅ Serial Number Location Update in Put Away

**Problem:** Setelah Put Away, serial numbers masih di RECEIVING (tidak pindah ke STORAGE).

**Solution:**

- Updated `PutAwayService::post()` to move serial numbers from RECEIVING to STORAGE
- After creating stock movements, update `current_location_id` for affected serials
- Uses `goods_receipt_line_id` to match correct serials

**Files Changed:**

- `app/Services/PutAway/PutAwayService.php`

**How it works:**

```
Put Away Process:
1. User creates Put Away for GR line
2. System posts Put Away
3. For serialized items:
   - Find serial numbers: item_id + gr_line_id + current_location=RECEIVING
   - Limit by qty being put away
   - Update: current_location_id = destination_location_id (STORAGE)
4. Serial numbers now in correct STORAGE location
```

## Testing Steps

### Test Serial Number Flow End-to-End:

1. **Goods Receipt - Create Serial Numbers**

    ```
    Navigate to: /goods-receipts/create
    - Select PO with serialized item (e.g., Laptop)
    - Enter serial numbers (one per line)
    - Observe: Qty auto-updates
    - Save
    ```

2. **Verify in Database**

    ```sql
    SELECT serial_number, status, current_location_id, goods_receipt_line_id
    FROM item_serial_numbers
    WHERE item_id = 4; -- Laptop
    ```

    Expected: Serial numbers with status=AVAILABLE, location=RECEIVING

3. **Put Away - Move to Storage**

    ```
    Navigate to: /put-aways/create
    - Select the GR from step 1
    - System auto-fills lines
    - Select destination: STORAGE location
    - Post Put Away
    ```

4. **Verify Serial Moved to Storage**

    ```sql
    SELECT serial_number, status, current_location_id
    FROM item_serial_numbers
    WHERE item_id = 4;
    ```

    Expected: current_location_id updated to STORAGE location ID

5. **Picking Order - Use Serials**
    ```
    Navigate to: /picking-orders/create
    - Select warehouse
    - Add serialized item
    - Select source location (STORAGE)
    - Observe: Serial number dropdown appears
    - Select serials from dropdown
    - Qty auto-updates
    ```

## Database State After Each Step

**After Goods Receipt POST:**

```
item_serial_numbers:
  serial_number: SN-LPT-2025-006
  status: AVAILABLE
  current_location_id: 1 (RECEIVING)
  goods_receipt_line_id: 123
```

**After Put Away POST:**

```
item_serial_numbers:
  serial_number: SN-LPT-2025-006
  status: AVAILABLE
  current_location_id: 2 (STORAGE) ← Updated!
  goods_receipt_line_id: 123
```

**After Picking Order POST (future):**

```
item_serial_numbers:
  serial_number: SN-LPT-2025-006
  status: PICKED ← Status changed
  current_location_id: NULL ← Removed from warehouse
  picked_at: 2025-12-29 10:30:00
  picking_order_line_id: 456
```

## Code Snippets

### Goods Receipt - Serial Number Input (Vue)

```vue
<!-- Serialized: Serial Numbers Input -->
<div v-if="isSerializedItem(l.purchase_order_line_id)" class="md:col-span-3">
    <label class="text-xs font-medium">Serial Numbers *</label>
    <textarea
        :value="(l.serial_numbers || []).join('\n')"
        @input="(e) => onSerialNumbersChange(idx, e.target.value)"
        rows="3"
        placeholder="Enter serial numbers (one per line)"
    ></textarea>
    <p class="mt-1 text-xs text-muted-foreground">
        {{ l.serial_numbers?.length || 0 }} serial(s) → Qty: {{ l.received_quantity }}
    </p>
</div>
```

### Goods Receipt Service - Create Serials (PHP)

```php
if ($item && $item->is_serialized && is_array($serialNumbers) && count($serialNumbers) > 0) {
    foreach ($serialNumbers as $serialNumber) {
        \App\Models\ItemSerialNumber::query()->create([
            'item_id' => $item->id,
            'serial_number' => trim($serialNumber),
            'status' => \App\Models\ItemSerialNumber::STATUS_AVAILABLE,
            'current_location_id' => $receivingLocation?->id,
            'received_at' => $gr->received_at,
            'goods_receipt_line_id' => $grLine->id,
        ]);
    }
}
```

### Put Away Service - Update Serial Location (PHP)

```php
// Update serial numbers location if item is serialized
$item = $line->item;
if ($item && $item->is_serialized) {
    $serialNumbers = \App\Models\ItemSerialNumber::query()
        ->where('item_id', $line->item_id)
        ->where('goods_receipt_line_id', $line->goods_receipt_line_id)
        ->where('current_location_id', $receivingLocationId)
        ->where('status', \App\Models\ItemSerialNumber::STATUS_AVAILABLE)
        ->limit((int) $line->qty)
        ->get();

    foreach ($serialNumbers as $serial) {
        $serial->current_location_id = $line->destination_location_id;
        $serial->save();
    }
}
```

## Status

✅ **Both issues fixed and tested**

- Serial numbers can be entered during Goods Receipt
- Serial numbers move from RECEIVING to STORAGE during Put Away
- Serial numbers appear in Picking Order dropdown from correct location
