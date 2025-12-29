# Serial Number Input UI Improvements

## Overview

Improved the Goods Receipt form to provide a better user experience when entering serial numbers for serialized items.

## Changes Made

### 1. **Enhanced Serial Number Textarea**

- **Increased rows**: From 3 to 5 rows for better visibility
- **Monospace font**: Serial numbers now display in `font-mono` for better readability
- **Better placeholder**: Multi-line placeholder with example serial numbers
- **Stronger focus ring**: Changed from `ring-1` to `ring-2` for better visual feedback

### 2. **Visual Distinction for Serialized Items**

- Added blue background tint to serialized item lines
- Added "Serialized" badge next to item name
- Makes it immediately clear which items require serial numbers

### 3. **Improved Counter Display**

- **Color-coded counter**: Green when serials are entered, gray when empty
- **Better layout**: Counter and quantity displayed side-by-side
- **Clearer labels**: "serial number(s) entered" vs "Qty:"

### 4. **Duplicate Detection**

- Automatically removes duplicate serial numbers
- Console warning when duplicates are found
- Uses `Set` for efficient deduplication

### 5. **Responsive Layout**

- Serial number field takes full width (`md:col-span-7`) for better space utilization
- Remarks field adapts: full width for serialized items, 4 columns for non-serialized
- Fixed broken HTML element (removed orphaned `" type="number" />`)

### 6. **Better Error Display**

- Error messages positioned consistently for both input types
- Validation errors show in red below the input

## Visual Improvements

### Before

```
┌─────────────────────────────────────────┐
│ Item Name                                │
│ [3-row textarea]                         │
│ 0 serial(s) entered → Qty: 0             │
└─────────────────────────────────────────┘
```

### After

```
┌────────────────────────────────────────────────┐
│ Item Name [Serialized]                    ← Badge
│ ┌──────────────────────────────────────┐
│ │ SN-LPT-2025-001                      │ ← 5 rows
│ │ SN-LPT-2025-002                      │ ← Monospace
│ │ SN-LPT-2025-003                      │ ← Better focus
│ │                                      │
│ │                                      │
│ └──────────────────────────────────────┘
│ 3 serial number(s) entered     Qty: 3   ← Better layout
│ [Remarks field - full width]             ← Adaptive
└────────────────────────────────────────────────┘
```

## Code Quality Improvements

### Duplicate Detection

```typescript
// Before: No duplicate handling
line.serial_numbers = serials;

// After: Automatic deduplication
const uniqueSerials = Array.from(new Set(serials));
line.serial_numbers = uniqueSerials;

if (uniqueSerials.length !== serials.length) {
    console.warn(
        `Removed ${serials.length - uniqueSerials.length} duplicate serial number(s)`,
    );
}
```

### Conditional Styling

```vue
<!-- Dynamic class binding for serialized items -->
<div :class="[
    isSerializedItem(l.purchase_order_line_id)
        ? 'border-blue-200 bg-blue-50/30 dark:border-blue-800 dark:bg-blue-950/20'
        : '',
]">
```

## Testing Checklist

- [ ] Serial number textarea displays with 5 rows
- [ ] Placeholder text shows example format
- [ ] Serial numbers display in monospace font
- [ ] Counter updates in real-time
- [ ] Counter turns green when serials are entered
- [ ] Duplicate serial numbers are removed automatically
- [ ] "Serialized" badge appears for serialized items
- [ ] Background color distinguishes serialized items
- [ ] Remarks field spans full width for serialized items
- [ ] Non-serialized items still use quantity input
- [ ] Focus ring is visible when textarea is focused
- [ ] Dark mode styling works correctly

## Files Modified

- `resources/js/pages/goods-receipts/Form.vue`
    - Enhanced textarea styling
    - Added duplicate detection
    - Improved layout and visual feedback
    - Fixed broken HTML element

## Benefits

1. **Better UX**: Clearer visual distinction between serialized and non-serialized items
2. **Data Quality**: Automatic duplicate removal prevents errors
3. **Usability**: Larger textarea and better placeholder text
4. **Accessibility**: Stronger focus indicators and clear labels
5. **Consistency**: Unified styling with the rest of the application
