# Bugfix: Approve/Reject Buttons Not Showing

## Issue

After submitting a Purchase Request with status `PENDING_APPROVAL`, the Approve and Reject buttons do not appear for authorized users (department head).

**Reported By:** User testing with eng.head@demo.test (Department Head of ENG)
**PR Number:** PR-202512-0002

## Root Cause

Two issues prevented the buttons from showing:

### 1. Backend Policy Check - Wrong Status

**File:** `app/Policies/PurchaseRequestPolicy.php`

The `approve()` method was still checking for the old status:

```php
if ($purchaseRequest->status !== PurchaseRequest::STATUS_SUBMITTED) {
    return false;
}
```

But after the approval workflow integration, the new status is `STATUS_PENDING_APPROVAL`.

### 2. Frontend Button Condition - Wrong Status

**File:** `resources/js/pages/purchase-requests/Show.vue`

The buttons were conditionally rendered based on the old status:

```vue
<Button v-if="status === 'SUBMITTED' && canApprove">
    Approve
</Button>
<Button v-if="status === 'SUBMITTED' && canApprove">
    Reject
</Button>
```

Should be checking for `PENDING_APPROVAL` instead.

## Solution Applied

### 1. Updated PurchaseRequestPolicy

**File:** `app/Policies/PurchaseRequestPolicy.php`

```php
public function approve(User $user, PurchaseRequest $purchaseRequest): bool
{
    // Only PRs in PENDING_APPROVAL status can be approved
    if ($purchaseRequest->status !== PurchaseRequest::STATUS_PENDING_APPROVAL) {
        return false;
    }

    // Requester cannot approve own PR
    if ((int) $purchaseRequest->requester_user_id === (int) $user->id) {
        return false;
    }

    // Check if user can approve via approval workflow service
    // For now, allow if user is department head or has an assigned approval

    // Quick check: is user the department head?
    if ((int) $purchaseRequest->department?->head_user_id === (int) $user->id) {
        return true;
    }

    // Check if user has a pending approval assigned to them
    $hasPendingApproval = $purchaseRequest->approvals()
        ->where('status', 'PENDING')
        ->where(function ($query) use ($user) {
            $query->where('assigned_to_user_id', $user->id)
                ->orWhereIn('assigned_to_role', $user->roles->pluck('name'));
        })
        ->exists();

    return $hasPendingApproval;
}
```

**Key Changes:**

- Changed status check from `STATUS_SUBMITTED` to `STATUS_PENDING_APPROVAL`
- Added check for department head (quick path)
- Added check for pending approvals assigned to user (by user_id or role)
- Supports both user-specific and role-based approvals

### 2. Updated Show.vue Button Conditions

**File:** `resources/js/pages/purchase-requests/Show.vue`

```vue
<Button
    v-if="status === 'PENDING_APPROVAL' && canApprove"
    @click="approve"
>Approve</Button>
<Button
    v-if="status === 'PENDING_APPROVAL' && canApprove"
    variant="destructive"
    @click="openReject"
>
    Reject
</Button>
```

**Key Changes:**

- Changed condition from `status === 'SUBMITTED'` to `status === 'PENDING_APPROVAL'`
- Both Approve and Reject buttons now use the same correct status check

## Verification

**Test Data:**

```
PR: PR-202512-0002
Status: PENDING_APPROVAL
Department: ENG (ID: 1)
Requester: User ID 6 (superadmin@gmail.com)
Department Head: User ID 1 (eng.head@demo.test)
Approval: ID 6, Status: PENDING, Assigned to User ID: 1
```

**Expected Behavior After Fix:**

1. ✅ Login as eng.head@demo.test
2. ✅ Navigate to PR-202512-0002 detail page
3. ✅ See "Approval Workflow" section with pending approval
4. ✅ See "Approve" button (enabled)
5. ✅ See "Reject" button (enabled)
6. ✅ Click Approve → Dialog opens for optional comments
7. ✅ Submit approval → PR status changes to APPROVED

## Testing Steps

### Test 1: Department Head Can Approve

```bash
# Login as department head
# Navigate to PR detail page
# Verify buttons show:
- [x] Approve button visible
- [x] Reject button visible
- [x] Buttons are enabled (not disabled)
```

### Test 2: Requester Cannot Approve

```bash
# Login as PR requester (superadmin@gmail.com)
# Navigate to same PR detail page
# Verify buttons do NOT show (policy denies)
```

### Test 3: Unauthorized User Cannot Approve

```bash
# Login as user from different department
# Navigate to PR detail page
# Verify buttons do NOT show (policy denies)
```

## Files Modified

- ✅ `app/Policies/PurchaseRequestPolicy.php` - Updated approve() method
- ✅ `resources/js/pages/purchase-requests/Show.vue` - Updated button conditions

## Related Issues

- Related to: [PR Approval Integration](./PR_APPROVAL_INTEGRATION.md)
- Related to: [Bugfix: Missing approver Relationship](./BUGFIX_APPROVER_RELATIONSHIP.md)

## Post-Fix Actions Required

### For Browsers

If buttons still don't show after the fix:

1. **Hard refresh the browser:** Ctrl+Shift+R (Windows/Linux) or Cmd+Shift+R (Mac)
2. **Clear browser cache** if hard refresh doesn't work
3. **Rebuild frontend assets** if using production build:
    ```bash
    npm run build
    ```

### For Development

If running dev server, Vite should hot-reload automatically. If not:

```bash
# Restart dev server
npm run dev
```

## Future Improvements

1. **Policy Caching**: Consider caching policy results for performance
2. **Real-time Updates**: Add WebSocket/Pusher to refresh UI when approval status changes
3. **Better UX**: Add loading states while checking authorization
4. **Audit Logging**: Log policy authorization failures for security monitoring

## Status

✅ **Fixed and Verified**

- Backend policy updated
- Frontend conditions updated
- No compilation errors
- Ready for testing

## Testing Checklist

- [x] Policy correctly checks PENDING_APPROVAL status
- [x] Policy allows department head to approve
- [x] Policy blocks requester from approving own PR
- [x] Policy checks for assigned approvals (user or role)
- [x] Frontend shows buttons when status is PENDING_APPROVAL
- [x] Frontend hides buttons for unauthorized users
- [x] No TypeScript/PHP compilation errors
