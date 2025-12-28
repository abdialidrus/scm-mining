# Bugfix: Approval Step Name and Sequence Not Showing

## Issue

In the "Approval Workflow" section on PR detail page, the step name and sequence were not displayed. Only "Approval" (fallback text) was shown instead of the actual step name like "Department Head Approval".

**Affected Pages:**

- Purchase Request Detail (Show.vue)
- Purchase Order Detail (if implemented)

## Root Cause

**Column Name Mismatch Between Model and Database**

The database table `approval_workflow_steps` uses:

- `step_name` (column name in DB)
- `step_order` (column name in DB)

But the frontend TypeScript type `ApprovalDto` and Vue component expected:

- `step.name`
- `step.sequence`

The model `ApprovalWorkflowStep` had the fillable attributes defined correctly but didn't expose `name` and `sequence` as accessible properties for JSON serialization.

## Database Structure

```sql
approval_workflow_steps:
  - id
  - approval_workflow_id
  - step_order          ← Actual column name
  - step_code
  - step_name           ← Actual column name
  - step_description
  - approver_type
  - approver_value
  - condition_field
  - condition_operator
  - condition_value
  - is_required
  - allow_skip
  - allow_parallel
  - meta
  - created_at
  - updated_at
```

## Frontend Expectation

```typescript
// TypeScript type in purchaseRequestApi.ts
step?: {
    id: number;
    name: string;      // ← Expected property
    sequence: number;  // ← Expected property
    approver_type: string;
} | null;
```

```vue
<!-- Vue template in Show.vue -->
<div class="text-sm font-medium">
    {{ approval.step?.name ?? 'Approval' }}
</div>
```

## Solution Applied

Added accessor methods in `ApprovalWorkflowStep` model to alias `step_name` → `name` and `step_order` → `sequence`:

**File:** `app/Models/ApprovalWorkflowStep.php`

```php
protected $appends = [
    'approver_role',
    'approver_user_id',
    'is_final_step',
    'name',      // ← Added
    'sequence'   // ← Added
];

/**
 * Get name attribute (alias for step_name for backward compatibility).
 */
public function getNameAttribute(): ?string
{
    return $this->attributes['step_name'] ?? null;
}

/**
 * Get sequence attribute (alias for step_order for backward compatibility).
 */
public function getSequenceAttribute(): ?int
{
    return $this->attributes['step_order'] ?? null;
}
```

### Why Use Accessors?

1. **No Database Migration Needed**: Keeps existing column names
2. **Backward Compatibility**: Both `step_name` and `name` work
3. **Clean API**: Frontend gets expected property names
4. **Automatic Serialization**: Accessors in `$appends` are included in JSON/array output

## Verification

### Before Fix

```json
{
    "approval": {
        "id": 6,
        "status": "APPROVED",
        "step": {
            "id": 4,
            "step_name": "Department Head Approval", // ← Raw column
            "step_order": 1, // ← Raw column
            "name": null, // ← Missing!
            "sequence": null // ← Missing!
        }
    }
}
```

### After Fix

```json
{
    "approval": {
        "id": 6,
        "status": "APPROVED",
        "step": {
            "id": 4,
            "step_name": "Department Head Approval",
            "step_order": 1,
            "name": "Department Head Approval", // ✅ Accessor
            "sequence": 1, // ✅ Accessor
            "approver_role": null,
            "approver_user_id": null,
            "is_final_step": true
        }
    }
}
```

## Frontend Display Result

The "Approval Workflow" section now correctly shows:

```
Approval Workflow
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[1] Department Head Approval    [APPROVED]
    ENG Head
    Approved by: ENG Head
    2025-12-28 10:32:20
```

Instead of:

```
[1] Approval                    [APPROVED]  ← Generic fallback
    ENG Head
```

## Files Modified

- ✅ `app/Models/ApprovalWorkflowStep.php` - Added `name` and `sequence` accessors

## Testing

### Test Data Verification

```bash
php artisan tinker
```

```php
$step = App\Models\ApprovalWorkflowStep::find(4);
echo $step->step_name;  // "Department Head Approval"
echo $step->name;       // "Department Head Approval" (via accessor)
echo $step->step_order; // 1
echo $step->sequence;   // 1 (via accessor)

// JSON output includes both
$step->toArray();
// Returns array with both step_name and name, step_order and sequence
```

### Frontend Verification

1. ✅ Login and view PR detail page
2. ✅ "Approval Workflow" section displays
3. ✅ Step name shows: "Department Head Approval"
4. ✅ Sequence number badge shows: "1"
5. ✅ All approval details visible

## Related Issues

- Related to: [PR Approval Integration](./PR_APPROVAL_INTEGRATION.md)
- Related to: [Bugfix: Missing approver Relationship](./BUGFIX_APPROVER_RELATIONSHIP.md)
- Related to: [Bugfix: Approve Buttons Not Showing](./BUGFIX_APPROVE_BUTTONS.md)

## Alternative Approaches Considered

### 1. Rename Database Columns

❌ **Not chosen** - Would require migration and might break existing data/queries

### 2. Update Frontend to Use step_name/step_order

❌ **Not chosen** - Would require changes in TypeScript types and all Vue components

### 3. Use Attribute Casting

❌ **Not chosen** - Casting doesn't work for creating new property names

### 4. Transform in Controller

❌ **Not chosen** - Would need to modify every controller that returns approvals

### 5. Use API Resource

❌ **Not chosen** - Adds complexity and requires creating resource classes

## Best Practice Recommendation

For future models, establish naming conventions early:

- **Option A**: Use snake_case for DB columns, add accessors for camelCase if needed for API
- **Option B**: Use simple names (name, order) in DB, avoiding prefixes like step_name, step_order

Current approach (accessors) is a good compromise for existing systems.

## Status

✅ **Fixed and Verified**

- Accessors added to model
- JSON serialization includes name and sequence
- Frontend displays step information correctly
- No breaking changes to existing code
