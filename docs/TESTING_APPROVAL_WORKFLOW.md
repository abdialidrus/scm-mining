# Testing Approval Workflow System

This guide will help you test the new approval workflow system.

## Prerequisites

```bash
# 1. Run migrations
php artisan migrate

# 2. Seed approval workflows
php artisan db:seed --class=ApprovalWorkflowSeeder

# 3. Ensure you have users with proper roles
php artisan db:seed --class=RolePermissionSeeder
```

## Test Scenarios

### Scenario 1: PO Amount < 50M (Finance Only)

```bash
# Create test data via tinker
php artisan tinker
```

```php
// In tinker:
use App\Models\{PurchaseOrder, User, Supplier};
use App\Services\PurchaseOrder\PurchaseOrderService;

$procurementUser = User::whereHas('roles', fn($q) => $q->where('name', 'procurement'))->first();
$financeUser = User::whereHas('roles', fn($q) => $q->where('name', 'finance'))->first();

$service = app(PurchaseOrderService::class);

// Create PO with amount 30M (below 50M threshold)
$po = $service->createDraftFromPurchaseRequests($procurementUser, [
    'supplier_id' => Supplier::first()->id,
    'purchase_request_ids' => [1], // Adjust based on your PR IDs
    'currency_code' => 'IDR',
    'tax_rate' => 0.11,
]);

// Submit PO (will initialize approval workflow)
$po = $service->submit($procurementUser, $po->id);

// Check approval progress
php artisan test:approval-workflow {$po->id}

// Finance approves
$po = $service->approve($financeUser, $po->id);

// Check status
php artisan test:approval-workflow {$po->id}
// Should show: All approvals completed, PO status = APPROVED
```

### Scenario 2: PO Amount 50M - 99M (Finance + GM)

```php
// Create PO with amount 75M
$po = $service->createDraftFromPurchaseRequests($procurementUser, [
    'supplier_id' => Supplier::first()->id,
    'purchase_request_ids' => [1, 2, 3], // PRs totaling ~75M
    'currency_code' => 'IDR',
    'tax_rate' => 0.11,
]);

$po = $service->submit($procurementUser, $po->id);

// Check workflow
php artisan test:approval-workflow {$po->id}
// Should show: Finance (PENDING), GM (PENDING)

// Finance approves
$financeUser = User::whereHas('roles', fn($q) => $q->where('name', 'finance'))->first();
$po = $service->approve($financeUser, $po->id);

// Check workflow
php artisan test:approval-workflow {$po->id}
// Should show: Finance (APPROVED), GM (PENDING), PO status = IN_APPROVAL

// GM approves
$gmUser = User::whereHas('roles', fn($q) => $q->where('name', 'gm'))->first();
$po = $service->approve($gmUser, $po->id);

// Check status
php artisan test:approval-workflow {$po->id}
// Should show: All approvals completed, PO status = APPROVED
```

### Scenario 3: PO Amount >= 100M (Finance + GM + Director)

```php
// Create PO with amount 150M
$po = $service->createDraftFromPurchaseRequests($procurementUser, [
    'supplier_id' => Supplier::first()->id,
    'purchase_request_ids' => [1, 2, 3, 4, 5], // PRs totaling ~150M
    'currency_code' => 'IDR',
    'tax_rate' => 0.11,
]);

$po = $service->submit($procurementUser, $po->id);

// Check workflow
php artisan test:approval-workflow {$po->id}
// Should show: Finance (PENDING), GM (PENDING), Director (PENDING)

// Approval flow: Finance → GM → Director
$financeUser = User::whereHas('roles', fn($q) => $q->where('name', 'finance'))->first();
$gmUser = User::whereHas('roles', fn($q) => $q->where('name', 'gm'))->first();
$directorUser = User::whereHas('roles', fn($q) => $q->where('name', 'director'))->first();

$po = $service->approve($financeUser, $po->id);
$po = $service->approve($gmUser, $po->id);
$po = $service->approve($directorUser, $po->id);

// Check final status
php artisan test:approval-workflow {$po->id}
// Should show: All APPROVED, PO status = APPROVED
```

### Scenario 4: Rejection

```php
// Create and submit PO
$po = $service->createDraftFromPurchaseRequests($procurementUser, [
    'supplier_id' => Supplier::first()->id,
    'purchase_request_ids' => [1],
]);

$po = $service->submit($procurementUser, $po->id);

// Finance rejects
$financeUser = User::whereHas('roles', fn($q) => $q->where('name', 'finance'))->first();
$po = $service->reject($financeUser, $po->id, 'Budget tidak tersedia');

// Check status
php artisan test:approval-workflow {$po->id}
// Should show: Finance (REJECTED), remaining (CANCELLED), PO status = REJECTED
```

### Scenario 5: Unauthorized Approval

```php
// Try to approve without proper role
$randomUser = User::whereDoesntHave('roles', fn($q) => $q->whereIn('name', ['finance', 'gm', 'director']))->first();

try {
    $po = $service->approve($randomUser, $po->id);
} catch (\Illuminate\Auth\Access\AuthorizationException $e) {
    echo "✅ Authorization check working: " . $e->getMessage();
}
```

## API Testing with cURL

### Get Approval Progress

```bash
# Get approvals for PO #1
curl -X GET http://localhost:8000/api/purchase-orders/1/approvals \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Approve PO

```bash
curl -X POST http://localhost:8000/api/purchase-orders/1/approve \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### Reject PO

```bash
curl -X POST http://localhost:8000/api/purchase-orders/1/reject \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Budget exceeded"}'
```

## Database Queries for Verification

### Check workflow configuration

```sql
-- List all workflows
SELECT * FROM approval_workflows;

-- List steps for PO workflow
SELECT
    w.code,
    s.step_order,
    s.step_code,
    s.step_name,
    s.approver_type,
    s.approver_value,
    s.condition_field,
    s.condition_operator,
    s.condition_value
FROM approval_workflow_steps s
JOIN approval_workflows w ON s.approval_workflow_id = w.id
WHERE w.code = 'PO_STANDARD'
ORDER BY s.step_order;
```

### Check approval instances for a PO

```sql
-- Get approval progress for PO #1
SELECT
    p.po_number,
    p.status AS po_status,
    p.total_amount,
    s.step_order,
    s.step_name,
    a.status AS approval_status,
    a.assigned_to_role,
    u_assigned.name AS assigned_to,
    u_approved.name AS approved_by,
    a.approved_at,
    a.rejection_reason
FROM approvals a
JOIN approval_workflow_steps s ON a.approval_workflow_step_id = s.id
JOIN purchase_orders p ON a.approvable_id = p.id AND a.approvable_type = 'App\\Models\\PurchaseOrder'
LEFT JOIN users u_assigned ON a.assigned_to_user_id = u_assigned.id
LEFT JOIN users u_approved ON a.approved_by_user_id = u_approved.id
WHERE p.id = 1
ORDER BY s.step_order;
```

### Check approval audit trail

```sql
-- Complete audit trail for all POs
SELECT
    p.po_number,
    s.step_name,
    a.status,
    u.name AS actor,
    a.approved_at,
    a.comments,
    a.rejection_reason,
    a.created_at
FROM approvals a
JOIN approval_workflow_steps s ON a.approval_workflow_step_id = s.id
JOIN purchase_orders p ON a.approvable_id = p.id AND a.approvable_type = 'App\\Models\\PurchaseOrder'
LEFT JOIN users u ON a.approved_by_user_id = u.id
ORDER BY p.id, s.step_order;
```

## Expected Results

### ✅ Success Indicators

1. **Workflow initialization**: After submit, approvals should be created based on amount
2. **Sequential approval**: Each approval should update status correctly
3. **Authorization**: Only assigned role can approve each step
4. **Rejection**: Should cancel remaining approvals and update PO status
5. **Completion**: After all approvals, PO status should be APPROVED

### ❌ Common Issues

1. **No approvals created**: Check if workflow seeded correctly
2. **Authorization failed**: Verify user has correct role
3. **Condition not working**: Check PO total_amount matches condition threshold

## Troubleshooting

```bash
# Reset and re-seed workflows
php artisan migrate:fresh --seed

# Check if ApprovalWorkflowService is registered
php artisan tinker
app(App\Services\Approval\ApprovalWorkflowService::class)

# Enable query logging to see what's happening
DB::enableQueryLog();
// ... run your test
dd(DB::getQueryLog());
```

## Next: Frontend Integration

After backend testing is successful, integrate with frontend:

1. Create `ApprovalProgressWidget.vue` component
2. Add approve/reject buttons in PO show page
3. Update TypeScript DTOs for approval types
4. Add API client methods in `purchaseOrderApi.ts`
