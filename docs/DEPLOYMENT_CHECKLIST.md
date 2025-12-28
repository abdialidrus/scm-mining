# 🚀 Approval Workflow System - Deployment Checklist

## ✅ Pre-Deployment Checklist

### 1. Code Review

- [ ] Review all migration files
- [ ] Review model relationships
- [ ] Review service layer logic
- [ ] Review policy changes
- [ ] Review controller endpoints

### 2. Database Preparation

- [ ] Backup current database
- [ ] Test migrations on staging/dev environment first
- [ ] Verify no conflicts with existing tables

### 3. Dependencies

- [ ] Confirm Laravel version compatibility (12.x)
- [ ] Verify all required packages installed
- [ ] Run `composer install` if needed

---

## 🔧 Deployment Steps

### Step 1: Run Migrations

```bash
# Backup database first!
# Then run migrations
php artisan migrate

# Verify tables created
php artisan tinker
DB::table('approval_workflows')->count()
DB::table('approval_workflow_steps')->count()
DB::table('approvals')->count()
```

**Expected Output**: 3 new tables created successfully

### Step 2: Seed Workflows

```bash
php artisan db:seed --class=ApprovalWorkflowSeeder
```

**Expected Output**:

```
✓ Purchase Order workflow seeded
✓ Purchase Request workflow seeded
```

**Verify**:

```bash
php artisan tinker
App\Models\ApprovalWorkflow::count() // Should be 2
App\Models\ApprovalWorkflowStep::count() // Should be 4 (3 for PO + 1 for PR)
```

### Step 3: (Optional) Migrate Existing POs

⚠️ **Only if you have existing POs in SUBMITTED/IN_APPROVAL status**

```bash
php artisan db:seed --class=MigrateExistingPurchaseOrdersSeeder
```

### Step 4: Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Step 5: Restart Queue Workers (if using)

```bash
php artisan queue:restart
```

---

## 🧪 Post-Deployment Testing

### Test 1: Workflow Configuration

```bash
php artisan tinker
```

```php
// Check PO workflow
$workflow = App\Models\ApprovalWorkflow::where('code', 'PO_STANDARD')->first();
$workflow->orderedSteps->pluck('step_name', 'step_code');
// Should show: FINANCE, GM, DIRECTOR

// Check PR workflow
$workflow = App\Models\ApprovalWorkflow::where('code', 'PR_STANDARD')->first();
$workflow->orderedSteps->pluck('step_name', 'step_code');
// Should show: DEPT_HEAD
```

### Test 2: Create & Submit Test PO

```php
// In tinker:
use App\Models\{PurchaseOrder, User, Supplier};
use App\Services\PurchaseOrder\PurchaseOrderService;

$service = app(PurchaseOrderService::class);
$procurementUser = User::whereHas('roles', fn($q) => $q->where('name', 'procurement'))->first();

// Create and submit a test PO
$po = $service->createDraftFromPurchaseRequests($procurementUser, [
    'supplier_id' => Supplier::first()->id,
    'purchase_request_ids' => [1], // Adjust based on your data
    'currency_code' => 'IDR',
    'tax_rate' => 0.11,
]);

$po = $service->submit($procurementUser, $po->id);

// Check approvals created
$po->approvals()->count() // Should be > 0
```

### Test 3: Approval Flow

```php
// Check pending approval
$service = app(App\Services\Approval\ApprovalWorkflowService::class);
$nextApproval = $service->getNextPendingApproval($po);
$nextApproval->step->step_name // Should be "Finance Review"

// Test approve
$financeUser = User::whereHas('roles', fn($q) => $q->where('name', 'finance'))->first();
$po = app(App\Services\PurchaseOrder\PurchaseOrderService::class)
    ->approve($financeUser, $po->id);

// Verify
$po->status // Should be IN_APPROVAL (if more steps) or APPROVED (if no more steps)
```

### Test 4: Authorization

```php
// Try to approve with wrong user
$randomUser = User::whereDoesntHave('roles', fn($q) => $q->where('name', 'finance'))->first();

try {
    $service->approve($randomUser, $nextApproval);
} catch (\Illuminate\Auth\Access\AuthorizationException $e) {
    echo "✅ Authorization working: " . $e->getMessage();
}
```

### Test 5: Rejection

```php
// Test rejection
$po2 = $service->createDraftFromPurchaseRequests($procurementUser, [...]);
$po2 = $service->submit($procurementUser, $po2->id);

$nextApproval = app(App\Services\Approval\ApprovalWorkflowService::class)
    ->getNextPendingApproval($po2);

app(App\Services\Approval\ApprovalWorkflowService::class)
    ->reject($financeUser, $nextApproval, 'Test rejection');

$po2->fresh()->status // Should be REJECTED
```

### Test 6: API Endpoints

```bash
# Login and get token first
TOKEN="your_auth_token"

# Get approval progress
curl -X GET "http://localhost:8000/api/purchase-orders/1/approvals" \
  -H "Authorization: Bearer $TOKEN"

# Approve
curl -X POST "http://localhost:8000/api/purchase-orders/1/approve" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json"

# Reject
curl -X POST "http://localhost:8000/api/purchase-orders/1/reject" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Test rejection"}'
```

### Test 7: Use Test Command

```bash
# Check approval progress for any PO
php artisan test:approval-workflow 1

# Should display approval table with status
```

---

## 📊 Monitoring & Verification

### Database Queries

```sql
-- Check workflow setup
SELECT w.code, w.name, COUNT(s.id) as steps_count
FROM approval_workflows w
LEFT JOIN approval_workflow_steps s ON w.id = s.approval_workflow_id
GROUP BY w.id;

-- Check active approvals
SELECT
    p.po_number,
    p.status,
    COUNT(CASE WHEN a.status = 'PENDING' THEN 1 END) as pending_approvals,
    COUNT(CASE WHEN a.status = 'APPROVED' THEN 1 END) as approved_count,
    COUNT(CASE WHEN a.status = 'REJECTED' THEN 1 END) as rejected_count
FROM purchase_orders p
LEFT JOIN approvals a ON a.approvable_id = p.id AND a.approvable_type = 'App\\Models\\PurchaseOrder'
WHERE p.status IN ('SUBMITTED', 'IN_APPROVAL', 'APPROVED')
GROUP BY p.id;

-- Check approval performance (average time to approve)
SELECT
    s.step_name,
    AVG(EXTRACT(EPOCH FROM (a.approved_at - a.created_at))/3600) as avg_hours_to_approve,
    COUNT(*) as total_approvals
FROM approvals a
JOIN approval_workflow_steps s ON a.approval_workflow_step_id = s.id
WHERE a.status = 'APPROVED'
GROUP BY s.id;
```

---

## ⚠️ Rollback Plan

### If Something Goes Wrong

1. **Database Rollback**:

```bash
# Rollback last 3 migrations
php artisan migrate:rollback --step=3

# Or restore from backup
# pg_restore ...
```

2. **Code Rollback**:

```bash
git revert HEAD~1  # Revert last commit
# or
git checkout <previous-commit>
```

3. **Temporary Disable**:

```php
// In PurchaseOrderService, temporarily bypass workflow
// Comment out: $this->approvalWorkflowService->initiate(...)
// Use old approval logic
```

---

## 🎯 Success Criteria

### ✅ All Checks Must Pass

1. [ ] Migrations run successfully
2. [ ] Workflow seeder completes
3. [ ] Can create and submit PO
4. [ ] Approvals created automatically on submit
5. [ ] Approval flow works (Finance → GM → Director)
6. [ ] Authorization enforced correctly
7. [ ] Rejection works and cancels remaining approvals
8. [ ] API endpoints return correct data
9. [ ] No errors in Laravel log
10. [ ] Performance acceptable (< 200ms for approval operations)

### 📈 Metrics to Monitor

- Average time to approve by step
- Rejection rate
- Number of pending approvals
- API response times
- Error rates

---

## 📞 Support & Troubleshooting

### Common Issues

#### Issue: "Workflow not found"

**Solution**: Run ApprovalWorkflowSeeder

#### Issue: "No pending approval found"

**Solution**: Check if PO was submitted (status should be SUBMITTED or IN_APPROVAL)

#### Issue: Authorization failed

**Solution**: Verify user has correct role assigned

#### Issue: Approvals not created on submit

**Solution**: Check if ApprovalWorkflowService is properly injected in PurchaseOrderService

### Debug Mode

```php
// Enable query log
DB::enableQueryLog();

// Run your operation
// ...

// Check queries
dd(DB::getQueryLog());
```

### Logs

```bash
# Watch Laravel logs
tail -f storage/logs/laravel.log

# Or use Pail
php artisan pail
```

---

## 🎉 Post-Deployment

### 1. Documentation

- [ ] Update API documentation
- [ ] Update user manual
- [ ] Train users on new rejection feature

### 2. Communication

- [ ] Notify stakeholders of new workflow system
- [ ] Announce new rejection feature
- [ ] Provide training materials

### 3. Monitoring

- [ ] Set up alerts for approval bottlenecks
- [ ] Monitor system performance
- [ ] Track user adoption

---

## 📚 Next Steps (Future Enhancements)

1. **Frontend Integration**
    - ApprovalProgressWidget component
    - Approve/Reject buttons with modals
    - Real-time notifications

2. **Advanced Features**
    - Parallel approvals (2 of 3 approvers)
    - Delegation support
    - Auto-escalation after X hours
    - Email notifications

3. **Reporting**
    - Approval dashboard
    - Bottleneck analysis
    - Performance metrics

---

**Deployment Approved By**: ******\_\_\_******  
**Deployment Date**: ******\_\_\_******  
**Deployed By**: ******\_\_\_******

✅ **System Ready for Production!**
