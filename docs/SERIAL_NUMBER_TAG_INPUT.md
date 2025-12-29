# Serial Number Tag Input - Better Approach

## Problem with Textarea Approach

The previous textarea implementation had a critical issue:

- Used `:value` binding instead of `v-model`
- Could not add new lines or edit properly
- User feedback: "text area ini tidak bisa tambah baris baru"

## Solution: Tag-Based Input

Instead of a textarea, we now use a **tag/chip input** approach where:

1. User enters ONE serial number at a time in an input field
2. Press Enter or click "Add" button to submit
3. Serial number appears as a visual tag/chip
4. Each tag has an X button to remove it
5. Much better UX and data validation

## Visual Design

```
┌────────────────────────────────────────────────────┐
│ Serial Numbers *                                    │
│                                                     │
│ ┌──────────────────────────────────┐  ┌─────┐    │
│ │ Enter serial number...           │  │ Add │    │
│ └──────────────────────────────────┘  └─────┘    │
│                                                     │
│ ┌───────────────────────────────────────────────┐ │
│ │  ╔═══════════════╗  ╔═══════════════╗         │ │
│ │  ║ SN-LPT-001 [×]║  ║ SN-LPT-002 [×]║  ...    │ │
│ │  ╚═══════════════╝  ╚═══════════════╝         │ │
│ └───────────────────────────────────────────────┘ │
│                                                     │
│ 3 serial number(s) added          Qty: 3          │
└────────────────────────────────────────────────────┘
```

## Key Features

### 1. Input Field with Add Button

- Single `<Input>` field for entering one serial at a time
- "Add" button next to it
- Press Enter key as shortcut
- Input clears after adding

### 2. Visual Tags/Chips

- Each serial number displayed as a chip
- Monospace font for readability
- Colored background for visibility
- Border for definition

### 3. Remove Button on Each Tag

- X icon button on each chip
- Click to remove that specific serial
- No need to re-enter all serials

### 4. Empty State

- Shows helpful message when no serials added
- Dashed border to indicate "add items here"
- Minimum height to prevent layout shift

### 5. Real-time Counter

- Shows count in green when serials exist
- Auto-updates quantity field
- Clear feedback on progress

### 6. Duplicate Prevention

- Checks if serial already exists
- Shows alert if duplicate detected
- Prevents data quality issues

## Implementation Details

### TypeScript Functions

```typescript
// Store input value for each line
const serialInputs = ref<Record<number, string>>({});

// Add serial number
function addSerialNumber(idx: number) {
    const input = serialInputs.value[idx]?.trim();
    if (!input) return; // Empty check

    const line = form.lines[idx];
    const serials = line.serial_numbers || [];

    // Duplicate check
    if (serials.includes(input)) {
        alert('Serial number already added');
        return;
    }

    // Add to array
    line.serial_numbers = [...serials, input];
    line.received_quantity = line.serial_numbers.length;

    // Clear input for next entry
    serialInputs.value[idx] = '';
}

// Remove serial number
function removeSerialNumber(idx: number, serial: string) {
    const line = form.lines[idx];
    line.serial_numbers = (line.serial_numbers || []).filter(
        (s) => s !== serial,
    );
    line.received_quantity = line.serial_numbers.length;
}

// Handle Enter key press
function handleSerialKeydown(idx: number, e: KeyboardEvent) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addSerialNumber(idx);
    }
}
```

### Vue Template

```vue
<!-- Input field with Add button -->
<div class="flex gap-2">
    <Input
        v-model="serialInputs[idx]"
        placeholder="Enter serial number and press Enter or click Add"
        class="font-mono"
        @keydown="(e: any) => handleSerialKeydown(idx, e)"
    />
    <Button
        type="button"
        size="sm"
        variant="outline"
        @click="addSerialNumber(idx)"
    >
        Add
    </Button>
</div>

<!-- Tags display (when serials exist) -->
<div
    v-if="l.serial_numbers && l.serial_numbers.length > 0"
    class="flex flex-wrap gap-2 rounded-md border p-3 min-h-20"
>
    <div
        v-for="serial in l.serial_numbers"
        :key="serial"
        class="inline-flex items-center gap-1 rounded-md bg-primary/10 px-2.5 py-1 text-sm font-mono"
    >
        <span>{{ serial }}</span>
        <button
            type="button"
            @click="removeSerialNumber(idx, serial)"
            class="ml-1 hover:text-destructive"
        >
            <svg width="14" height="14">
                <path d="M18 6 6 18M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>

<!-- Empty state (when no serials) -->
<div
    v-else
    class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground min-h-20"
>
    No serial numbers added yet. Enter a serial number above.
</div>
```

## User Flow

1. **User sees serialized item line** with blue background and "Serialized" badge
2. **User enters serial number** in the input field (e.g., "SN-LPT-2025-001")
3. **User presses Enter** (or clicks Add button)
4. **Serial appears as a chip** below the input
5. **Counter updates** to "1 serial number(s) added → Qty: 1"
6. **User repeats** for each serial number
7. **User can remove** any serial by clicking its X button
8. **User submits form** - all serials sent to backend as array

## Advantages Over Textarea

| Feature                | Textarea           | Tag Input                 |
| ---------------------- | ------------------ | ------------------------- |
| Add new line           | ❌ Broken          | ✅ Works perfectly        |
| Edit individual serial | ❌ Must edit text  | ✅ Click X to remove      |
| Visual feedback        | ⚠️ Text only       | ✅ Colored chips          |
| Duplicate detection    | ⚠️ Console warning | ✅ Alert on add           |
| Mobile friendly        | ⚠️ Text input      | ✅ Touch-friendly buttons |
| Empty state            | ❌ Blank textarea  | ✅ Helpful message        |
| Validation per entry   | ❌ After submit    | ✅ Before adding          |

## Backend Compatibility

✅ **No backend changes needed!**

The payload structure remains identical:

```json
{
    "lines": [
        {
            "purchase_order_line_id": 1,
            "received_quantity": 3,
            "serial_numbers": [
                "SN-LPT-2025-001",
                "SN-LPT-2025-002",
                "SN-LPT-2025-003"
            ]
        }
    ]
}
```

## Testing Steps

1. ✅ Navigate to Goods Receipt form
2. ✅ Select a PO with serialized items
3. ✅ See input field with "Add" button
4. ✅ Type a serial number
5. ✅ Press Enter (or click Add)
6. ✅ See serial appear as a chip
7. ✅ Try adding duplicate - see alert
8. ✅ Add multiple serials
9. ✅ Click X on a chip to remove it
10. ✅ See counter update in real-time
11. ✅ Submit form - verify backend receives array

## Future Enhancements (Optional)

- [ ] Barcode scanner integration
- [ ] Bulk paste from Excel/CSV
- [ ] Serial number validation (regex pattern)
- [ ] Auto-suggest from available serials
- [ ] Drag to reorder serials
- [ ] Export serial list to clipboard
- [ ] Import from file

## Files Modified

- `resources/js/pages/goods-receipts/Form.vue`
    - Replaced textarea with tag input
    - Added `serialInputs` ref
    - Added `addSerialNumber()` function
    - Added `removeSerialNumber()` function
    - Added `handleSerialKeydown()` function
    - Updated template with new UI

## Conclusion

The tag input approach provides:

- ✅ **Better UX** - Visual, intuitive, easy to use
- ✅ **Better validation** - Per-entry duplicate detection
- ✅ **Better editing** - Remove individual items easily
- ✅ **Better feedback** - Clear visual state
- ✅ **Solves the problem** - No more "cannot add new line" issue!

This is the recommended approach for all serial number inputs in the application.
