# Feature: Approval Workflow Display in Purchase Order Detail

## Summary

Added "Approval Workflow" section to Purchase Order detail page to display the approval history and status of each approval step.

## Changes Made

### Backend Changes

#### PurchaseOrderController

**File:** `app/Http/Controllers/Api/PurchaseOrderController.php`

Updated the `show()` method to eager load approval relationships:

```php
'approvals.step',
'approvals.approver',      // ← Changed from assignedToUser
'approvals.approvedBy',
'approvals.rejectedBy',
```

### Frontend Changes

#### 1. TypeScript Types

**File:** `resources/js/services/purchaseOrderApi.ts`

- Added `ApprovalDto` type definition (same as PR)
- Added `approvals?: ApprovalDto[]` to `PurchaseOrderDto`

```typescript
export type ApprovalDto = {
    id: number;
    status: string;
    assigned_to_user_id: number | null;
    assigned_to_role: string | null;
    approved_by_user_id: number | null;
    rejected_by_user_id: number | null;
    approved_at: string | null;
    rejected_at: string | null;
    comments: string | null;
    rejection_reason: string | null;
    step?: {
        id: number;
        name: string;
        sequence: number;
        approver_type: string;
    } | null;
    approver?: { id: number; name: string; email: string } | null;
    approved_by?: { id: number; name: string; email: string } | null;
    rejected_by?: { id: number; name: string; email: string } | null;
};

export type PurchaseOrderDto = {
    // ...existing fields
    approvals?: ApprovalDto[];
};
```

#### 2. Show Page Component

**File:** `resources/js/pages/purchase-orders/Show.vue`

Added "Approval Workflow" section between "Purchase Requests" and "Status History" sections.

**Features:**

- Timeline-style display of all approval steps
- Color-coded status badges:
    - 🟢 Green: APPROVED
    - 🔴 Red: REJECTED
    - 🟡 Yellow: PENDING
    - ⚪ Gray: CANCELLED
- Shows step name (e.g., "Finance Review", "GM Approval")
- Shows assigned approver (user name or role)
- For APPROVED steps: displays approver name, timestamp, and optional comments
- For REJECTED steps: displays rejecter name, timestamp, and rejection reason
- Section only appears if `po.approvals && po.approvals.length > 0`

## UI Display Example

### Single-Step Approved PO

```
Approval Workflow
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[1] Finance Review                  [APPROVED]
    John Doe
    Approved by: John Doe
    2025-12-28 11:45:30
    Comments: Budget approved
```

### Multi-Step Workflow (All Approved)

```
Approval Workflow
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[1] Finance Review                  [APPROVED]
    John Doe (Finance)
    Approved by: John Doe
    2025-12-28 10:15:00

[2] General Manager Approval        [APPROVED]
    Jane Smith (GM)
    Approved by: Jane Smith
    2025-12-28 11:30:00
    Comments: Approved with priority
```

### Multi-Step Workflow (Pending)

```
Approval Workflow
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[1] Finance Review                  [APPROVED]
    John Doe
    Approved by: John Doe
    2025-12-28 10:15:00

[2] General Manager Approval        [PENDING]
    Role: general_manager
```

### Rejected Workflow

```
Approval Workflow
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[1] Finance Review                  [REJECTED]
    John Doe
    Rejected by: John Doe
    2025-12-28 10:15:00
    Reason: Budget exceeded limit

[2] General Manager Approval        [CANCELLED]
    Role: general_manager
```

## Location in UI

The "Approval Workflow" section appears on the Purchase Order detail page in this order:

1. Header (PO number, status, buttons)
2. PO Information (supplier, totals, dates)
3. Lines Table
4. Purchase Requests (if any)
5. **⭐ Approval Workflow** ← NEW
6. Status History

## When Section Appears

The section is conditionally rendered based on:

```vue
<div v-if="po.approvals && po.approvals.length > 0">
```

**Scenarios:**

- ✅ Shows: PO has been submitted and workflow initiated (status: IN_APPROVAL, APPROVED, etc.)
- ❌ Hides: PO is still DRAFT (no workflow initiated yet)
- ❌ Hides: No workflow configured for PurchaseOrder model

## Testing Current Data

Based on database query:

- **PO-202512-0001**: Status SENT, 1 approval (APPROVED)
- **PO-202512-0002**: Status APPROVED, 1 approval (APPROVED)
- **PO-202512-0003**: Status SENT, 2 approvals (both APPROVED)

All existing POs should now display their approval history!

## Files Modified

- ✅ `app/Http/Controllers/Api/PurchaseOrderController.php` - Updated eager loading
- ✅ `resources/js/services/purchaseOrderApi.ts` - Added ApprovalDto and approvals field
- ✅ `resources/js/pages/purchase-orders/Show.vue` - Added Approval Workflow section

## Verification Steps

1. **Navigate to any PO detail page:**
    - PO-202512-0001
    - PO-202512-0002
    - PO-202512-0003

2. **Expected to see:**
    - ✅ "Approval Workflow" section appears before "Status History"
    - ✅ Each approval step displays with color-coded badge
    - ✅ Step names show correctly (not fallback "Approval")
    - ✅ Approver names display
    - ✅ Approval timestamps visible
    - ✅ Comments display if available

3. **Hard refresh browser** if section doesn't appear:
    - Windows/Linux: `Ctrl + Shift + R`
    - Mac: `Cmd + Shift + R`

## Related Documentation

- [PR Approval Integration](./PR_APPROVAL_INTEGRATION.md)
- [PR Approval Testing Guide](./PR_APPROVAL_TESTING_GUIDE.md)
- [Bugfix: Step Name Sequence](./BUGFIX_STEP_NAME_SEQUENCE.md)
- [Bugfix: Approver Relationship](./BUGFIX_APPROVER_RELATIONSHIP.md)

## Status

✅ **Complete and Ready**

- Backend eager loading configured
- Frontend types defined
- UI component implemented
- No compilation errors
- Existing PO data available for testing

## Next Steps (Optional Enhancements)

1. **Add Print Support**: Include approval workflow in printed PO
2. **Approval Actions**: Allow approve/reject directly from PO detail page
3. **Approval Notifications**: Real-time updates when approval status changes
4. **Approval Analytics**: Show average approval time per step
5. **Mobile Responsive**: Optimize layout for mobile devices
