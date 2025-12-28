# Bugfix: Missing `approver` Relationship in Approval Model

## Issue

When submitting a Purchase Request (e.g., PR-202512-0002), the following error occurred:

```
Call to undefined relationship [approver] on model [App\Models\Approval].
```

## Root Cause

The `Approval` model had a relationship called `assignedToUser()` but the code in various places (controllers, services) was referencing `approvals.approver` when eager loading relationships.

**Problematic Code Locations:**

1. `PurchaseRequestService::submit()` - Line ~137:

    ```php
    return $pr->load([..., 'approvals.approver']);
    ```

2. `PurchaseRequestController::show()` - Line ~64:

    ```php
    'approvals.approver',
    ```

3. Frontend TypeScript type `ApprovalDto` expected an `approver` field.

## Solution

Added an alias relationship `approver()` in the `Approval` model that points to the same relationship as `assignedToUser()`:

```php
/**
 * Alias for assignedToUser for backward compatibility.
 */
public function approver(): BelongsTo
{
    return $this->belongsTo(User::class, 'assigned_to_user_id');
}
```

This maintains backward compatibility while allowing both naming conventions to work.

## Why This Approach?

1. **No Breaking Changes**: Existing code using `assignedToUser` continues to work
2. **Consistency**: Frontend and backend can now both use `approver` naming
3. **Simple Fix**: Single line addition rather than refactoring multiple files
4. **Performance**: No impact - both are the same relationship definition

## Alternative Approaches Considered

1. **Refactor all occurrences to use `assignedToUser`**: Would require changes in:
    - Controllers (2 files)
    - Services (2 files)
    - Frontend TypeScript types
    - Frontend Vue components
    - Risk of missing some references

2. **Use attribute accessor**: Would only work for properties, not for eager loading relationships

## Testing

After the fix:

- [x] PR submission works without errors
- [x] Approval workflow is initiated correctly
- [x] `approvals.approver` eager loading works
- [x] Frontend displays approver name correctly
- [x] No compilation errors

## Files Modified

- `app/Models/Approval.php` - Added `approver()` relationship method

## Verification Steps

1. Login as any user
2. Create a new Purchase Request
3. Submit the PR
4. Verify no errors occur
5. Check that approval workflow is created
6. View PR detail page - confirm "Approval Workflow" section shows assigned approver name

## Related Documentation

- [PR Approval Integration Guide](./PR_APPROVAL_INTEGRATION.md)
- [PR Approval Testing Guide](./PR_APPROVAL_TESTING_GUIDE.md)
