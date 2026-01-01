# My Approvals Dashboard - Implementation Guide

## 📋 Overview

Fitur **My Approvals Dashboard** memberikan view terpusat untuk semua pending approvals yang menunggu action dari user yang sedang login. Dashboard ini menampilkan statistik approval dan list detail dokumen yang perlu di-approve.

## ✨ Features

### 1. **Statistics Cards**

Menampilkan 4 metrics penting:

- **Pending Approvals**: Total dokumen yang menunggu approval
- **Approved (30 days)**: Total dokumen yang sudah di-approve dalam 30 hari terakhir
- **Rejected (30 days)**: Total dokumen yang di-reject dalam 30 hari terakhir
- **Avg. Approval Time**: Rata-rata waktu approval dalam jam (30 hari terakhir)

### 2. **Pending Approvals Table**

Menampilkan list dokumen dengan informasi:

- Document Type (Purchase Request / Purchase Order)
- Document Number (PR-XXXXXX / PO-XXXXXX)
- Approval Step (Finance, GM, Director, Dept Head)
- Amount (untuk PO)
- Submitter (nama & email)
- Submitted At (timestamp)
- Pending Since (berapa lama menunggu dalam jam)
- Review Button (quick access ke detail dokumen)

### 3. **Filters**

- **Search**: Cari berdasarkan document number
- **Document Type**: Filter by Purchase Request atau Purchase Order

### 4. **Pagination**

- Standard pagination dengan navigation buttons
- Showing X to Y of Z results
- Configurable items per page (default: 15)

## 🔧 Technical Implementation

### Backend

#### API Endpoints

**1. Get Pending Approvals**

```http
GET /api/approvals/my-pending
```

Query Parameters:

- `search` (optional): Search by document number
- `document_type` (optional): `purchase_request` or `purchase_order`
- `page` (optional): Page number
- `per_page` (optional): Items per page (default: 15)

Response:

```json
{
    "data": [
        {
            "id": 1,
            "status": "PENDING",
            "comments": null,
            "created_at": "2026-01-02T10:00:00Z",
            "step": {
                "id": 1,
                "step_name": "Finance Approval",
                "sequence": 1
            },
            "assigned_to_role": "finance",
            "assigned_to_user_id": null,
            "document": {
                "type": "Purchase Order",
                "number": "PO-202601-0001",
                "url": "/purchase-orders/1",
                "amount": 50000000,
                "submitted_at": "2026-01-02T09:00:00Z",
                "submitter": {
                    "id": 5,
                    "name": "John Doe",
                    "email": "john@example.com"
                }
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 15,
        "total": 42
    }
}
```

**2. Get Statistics**

```http
GET /api/approvals/statistics
```

Response:

```json
{
    "data": {
        "pending_count": 5,
        "approved_last_30_days": 23,
        "rejected_last_30_days": 2,
        "average_approval_time_hours": 4.5
    }
}
```

#### Controller: `ApprovalController.php`

**Key Logic:**

```php
// Get approvals for user based on:
// 1. Direct assignment (assigned_to_user_id)
// 2. Role-based assignment (assigned_to_role matches user's roles)

$query->where('status', Approval::STATUS_PENDING)
    ->where(function ($q) use ($user, $userRoles) {
        $q->where('assigned_to_user_id', $user->id)
            ->orWhereIn('assigned_to_role', $userRoles);
    });
```

**Eager Loading:**

- `approvable` (PR or PO)
- `step` (approval workflow step)
- Related document fields (submitter, amounts, etc.)

### Frontend

#### Page: `resources/js/pages/approvals/MyApprovals.vue`

**Components Used:**

- `Card` - Statistics cards & table container
- `Table` - Approval list
- `Input` - Search field
- `Multiselect` - Document type filter
- `Button` - Pagination & actions

**State Management:**

```typescript
const approvals = ref<PendingApprovalDto[]>([]);
const statistics = ref<ApprovalStatisticsDto | null>(null);
const searchQuery = ref('');
const selectedDocType = ref<{ name: string; value: string } | null>(null);
const currentPage = ref(1);
const perPage = ref(15);
```

**Key Functions:**

- `loadApprovals()`: Fetch pending approvals & statistics
- `handleSearch()`: Trigger search with filters
- `handleDocTypeChange()`: Filter by document type
- `goToPage(page)`: Navigate pagination
- `handleRowClick(approval)`: Navigate to document detail

#### API Service: `resources/js/services/approvalApi.ts`

**Types:**

```typescript
export type PendingApprovalDto = {
    id: number;
    status: string;
    comments: string | null;
    created_at: string;
    step: ApprovalStepDto | null;
    assigned_to_role: string | null;
    assigned_to_user_id: number | null;
    document: ApprovalDocumentDto;
};
```

**Functions:**

```typescript
getMyPendingApprovals(params?: { ... })
getApprovalStatistics()
```

### Routes

**Web Route**: `routes/approvals.php`

```php
Route::get('/my-approvals', function () {
    return Inertia::render('approvals/MyApprovals');
})->name('approvals.my-approvals');
```

**API Routes**: `routes/api.php`

```php
Route::prefix('approvals')->group(function () {
    Route::get('/my-pending', [ApprovalController::class, 'myPendingApprovals']);
    Route::get('/statistics', [ApprovalController::class, 'statistics']);
});
```

### Navigation

**Sidebar**: Menu "My Approvals" ditambahkan di `AppSidebar.vue`

**Visibility Rules:**

```typescript
const canShowMyApprovals =
    isSuperAdmin || isDeptHead || isFinance || isGm || isDirector;
```

Menu hanya muncul untuk users dengan roles:

- `super_admin`
- `dept_head`
- `finance`
- `gm`
- `director`

## 🚀 Usage

### As Approver

1. **Login** dengan user yang memiliki approval role (finance, GM, director, dept head)

2. **Click "My Approvals"** di sidebar menu

3. **Review Statistics** di dashboard cards:
    - Check berapa banyak pending approvals
    - Monitor average approval time
    - Track approval/rejection count

4. **Filter & Search**:
    - Gunakan search box untuk cari document number
    - Filter by document type (PR/PO)

5. **Click "Review"** atau click row untuk membuka document detail

6. **Approve/Reject** di halaman detail dokumen

## 📊 Database Queries

### Pending Approvals Query

```sql
SELECT a.*, s.step_name, s.sequence
FROM approvals a
LEFT JOIN approval_workflow_steps s ON a.approval_workflow_step_id = s.id
WHERE a.status = 'PENDING'
  AND (
    a.assigned_to_user_id = :user_id
    OR a.assigned_to_role IN (:user_roles)
  )
ORDER BY a.created_at ASC;
```

### Statistics Queries

```sql
-- Pending Count
SELECT COUNT(*) FROM approvals
WHERE status = 'PENDING'
  AND (assigned_to_user_id = :user_id OR assigned_to_role IN (:roles));

-- Approved Last 30 Days
SELECT COUNT(*) FROM approvals
WHERE status = 'APPROVED'
  AND approved_by_user_id = :user_id
  AND approved_at >= NOW() - INTERVAL '30 days';

-- Average Approval Time
SELECT AVG(EXTRACT(EPOCH FROM (approved_at - created_at)) / 3600)
FROM approvals
WHERE approved_by_user_id = :user_id
  AND status = 'APPROVED'
  AND approved_at >= NOW() - INTERVAL '30 days';
```

## 🎨 UI/UX Features

### Design Elements

- **Color-coded badges**: Blue for document types
- **Hover effects**: Row hover for better interaction
- **Responsive layout**: Mobile-friendly grid for statistics cards
- **Status indicators**: Orange text for pending time
- **Icon indicators**: CheckCircle (green), XCircle (red), Clock, TrendingUp

### User Interactions

- **Click row**: Navigate to document detail
- **Review button**: Quick access to document
- **Search on enter**: Submit search with Enter key
- **Auto-refresh on filter**: Instant results when changing filters

## 🔐 Authorization

### Controller Level

```php
// ApprovalController uses auth middleware
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('approvals')->group(function () {
        // Only authenticated users can access
    });
});
```

### Query Level

```php
// Only show approvals assigned to current user
->where(function ($q) use ($user, $userRoles) {
    $q->where('assigned_to_user_id', $user->id)
        ->orWhereIn('assigned_to_role', $userRoles);
})
```

### Navigation Level

```typescript
// Menu only visible for approver roles
const canShowMyApprovals =
    isSuperAdmin || isDeptHead || isFinance || isGm || isDirector;
```

## 📝 Testing Checklist

- [ ] Login as finance user
- [ ] Verify "My Approvals" menu appears in sidebar
- [ ] Check statistics cards show correct numbers
- [ ] Search by PO/PR number works
- [ ] Filter by document type works
- [ ] Pagination works correctly
- [ ] Click row navigates to document detail
- [ ] Review button works
- [ ] Pending time calculation is accurate
- [ ] Average approval time displays correctly
- [ ] Test with user having multiple roles
- [ ] Test with user having no pending approvals

## 🐛 Troubleshooting

### Issue: Menu tidak muncul

**Solution**: Check user roles - must have dept_head, finance, gm, director, or super_admin

### Issue: Statistics showing 0

**Solution**: Check if user has approved/rejected documents in last 30 days

### Issue: Pending approvals empty

**Solution**:

1. Create PR/PO and submit
2. Ensure approval workflow assigned
3. Check approver_role matches user's role

### Issue: Click row tidak navigate

**Solution**: Check document URL field not null in response

## 🔄 Future Enhancements

Potential improvements (from NEXT_STEPS.md):

1. **Quick Actions**: Approve/reject directly from dashboard without opening detail
2. **Bulk Actions**: Approve multiple documents at once
3. **Real-time Updates**: WebSocket notifications for new approvals
4. **Email Notifications**: Alert when new approval assigned
5. **Approval Timeline**: Visual timeline of approval progress
6. **Filters Enhancement**:
    - Date range filter
    - Amount range filter
    - Department filter
7. **Export**: Export approval list to Excel
8. **Analytics**:
    - Approval bottleneck analysis
    - Approver performance metrics
    - Document aging report

## 📚 Related Files

### Backend

- `app/Http/Controllers/Api/ApprovalController.php`
- `app/Models/Approval.php`
- `routes/api.php`
- `routes/approvals.php`

### Frontend

- `resources/js/pages/approvals/MyApprovals.vue`
- `resources/js/services/approvalApi.ts`
- `resources/js/components/AppSidebar.vue`
- `routes/web.php`

### Documentation

- `docs/APPROVAL_WORKFLOW_IMPLEMENTATION.md`
- `docs/PR_APPROVAL_INTEGRATION.md`
- `docs/NEXT_STEPS.md`

## ✅ Completion Status

- ✅ Backend API endpoints
- ✅ Frontend dashboard page
- ✅ Statistics display
- ✅ Pending approvals table
- ✅ Search & filter functionality
- ✅ Pagination
- ✅ Navigation integration
- ✅ Role-based visibility
- ✅ Responsive design
- ✅ Documentation

**Status**: 🟢 **COMPLETED** - Ready for testing and deployment
