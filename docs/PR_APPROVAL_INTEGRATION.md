# Purchase Request Approval Workflow Integration

## Overview

Successfully integrated the data-driven approval workflow system with the Purchase Request module, matching the same pattern used in Purchase Orders.

## Changes Made

### Backend Changes

#### 1. **PurchaseRequest Model** (`app/Models/PurchaseRequest.php`)

- **Added Status Constants:**
    ```php
    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const STATUS_REJECTED = 'REJECTED';
    ```
- **Added Relationship:**
    ```php
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('id');
    }
    ```

#### 2. **PurchaseRequestService** (`app/Services/PurchaseRequest/PurchaseRequestService.php`)

- **Constructor:** Injected `ApprovalWorkflowService`
- **submit() method:**
    - Changed status from `SUBMITTED` → `PENDING_APPROVAL`
    - Calls `$this->approvalWorkflowService->initiate()` with workflow code `PR_STANDARD`
    - Added try-catch with fallback logging
    - Eager loads `approvals.step`, `approvals.approver` relationships

- **approve() method:**
    - Replaced hardcoded department head logic
    - Now uses `ApprovalWorkflowService::getNextPendingApproval()` to get current approval
    - Calls `ApprovalWorkflowService::approve()` to process approval
    - Checks `isWorkflowComplete()` before changing status to `APPROVED`
    - Accepts optional `$comments` parameter
    - Only records status history when workflow is complete

- **reject() method:**
    - Replaced hardcoded department head logic
    - Now uses `ApprovalWorkflowService::getNextPendingApproval()` to get current approval
    - Calls `ApprovalWorkflowService::reject()` to process rejection (auto-cancels remaining approvals)
    - Changes PR status to `REJECTED` (no longer back to `DRAFT`)
    - `$reason` is now required (not optional)

#### 3. **PurchaseRequestController** (`app/Http/Controllers/Api/PurchaseRequestController.php`)

- **show() method:** Added eager loading for approvals:

    ```php
    'approvals.step',
    'approvals.approver',
    'approvals.approvedBy',
    'approvals.rejectedBy',
    ```

- **approve() method:**
    - Now passes optional `comments` from request to service

    ```php
    $comments = $request->input('comments');
    $pr = $this->service->approve($request->user(), $purchaseRequest->id, $comments);
    ```

- **reject() method:**
    - Made `reason` required (removed `?? null`)
    ```php
    $pr = $this->service->reject($request->user(), $purchaseRequest->id, $request->validated()['reason']);
    ```

### Frontend Changes

#### 1. **TypeScript Types** (`resources/js/services/purchaseRequestApi.ts`)

- **Added `ApprovalDto` type:**

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
        step?: { ... } | null;
        approver?: { ... } | null;
        approved_by?: { ... } | null;
        rejected_by?: { ... } | null;
    };
    ```

- **Updated `PurchaseRequestDto`:**

    ```typescript
    approvals?: ApprovalDto[];
    ```

- **Updated API Functions:**
    ```typescript
    export async function approvePurchaseRequest(id: number, comments?: string);
    export async function rejectPurchaseRequest(id: number, reason: string);
    ```

#### 2. **Show Page** (`resources/js/pages/purchase-requests/Show.vue`)

- **Added Approvals Section** (before Audit History):
    - Displays all approvals in workflow order
    - Shows step sequence number in colored badge (green=approved, red=rejected, yellow=pending, gray=cancelled)
    - Displays step name, assigned approver (user or role)
    - Shows approval status badge
    - For approved steps: shows approver name, timestamp, optional comments
    - For rejected steps: shows rejecter name, timestamp, rejection reason

- **Updated Approval Dialog:**
    - Changed from direct approve action to dialog-based
    - Added optional comments field
    - Added `approveOpen`, `approveComments`, `approveSubmitting` state
    - Created `confirmApprove()` function to submit approval with comments

- **Updated Reject Dialog:**
    - Changed description (removed "return to DRAFT" text)
    - Reason is now required

## Workflow Behavior

### Submit Flow

1. User submits PR
2. Status changes to `PENDING_APPROVAL`
3. System initiates `PR_STANDARD` workflow
4. Creates approval instances based on workflow steps
5. Returns PR with approvals array populated

### Approve Flow

1. User clicks Approve button
2. Dialog opens with optional comments field
3. User submits approval
4. Backend validates:
    - PR must be in `PENDING_APPROVAL` status
    - Requester cannot approve their own PR
    - User must be authorized for current step (by user_id or role)
5. Current approval is marked as `APPROVED`
6. If all approvals complete → PR status changes to `APPROVED`
7. Status history recorded only when fully approved

### Reject Flow

1. User clicks Reject button
2. Dialog opens with required reason field
3. User submits rejection
4. Backend validates:
    - PR must be in `PENDING_APPROVAL` status
    - Requester cannot reject their own PR
    - User must be authorized for current step
5. Current approval is marked as `REJECTED`
6. All remaining pending approvals are `CANCELLED`
7. PR status changes to `REJECTED`
8. Status history recorded with reason in meta

## Status Flow Diagram

```
DRAFT → submit() → PENDING_APPROVAL
                        ↓
                     approve()
                        ↓
                  [all approved?]
                   ↓           ↓
                  YES         NO
                   ↓           ↓
                APPROVED   (stay PENDING_APPROVAL)

PENDING_APPROVAL → reject() → REJECTED
```

## Testing Checklist

- [ ] Create PR with PR_STANDARD workflow seeded
- [ ] Submit PR → verify status = PENDING_APPROVAL
- [ ] Verify approval workflow section shows on PR detail page
- [ ] Department head approves with comments → verify comment saved
- [ ] Verify status changes to APPROVED after all approvals
- [ ] Test rejection with reason → verify status = REJECTED
- [ ] Verify rejection cancels remaining approvals
- [ ] Verify requester cannot approve/reject own PR
- [ ] Test role-based approval (if applicable)
- [ ] Verify frontend displays all approval history correctly

## Known Limitations

1. **No "Return to Draft" feature**: Once rejected, PR stays in REJECTED status
    - Future: Add ability to revise rejected PRs back to DRAFT
2. **No approval delegation**: User assigned cannot delegate to another user
    - Future: Add delegation feature in workflow system

3. **No approval reminder notifications**: No automated reminders for pending approvals
    - Future: Add notification system integration

4. **Comments are optional**: Approvers may not provide context
    - Consider: Make comments required for certain approval steps

## Future Enhancements

1. **Revision Flow**: Allow rejected PRs to be revised and resubmitted
2. **Approval Reassignment**: Allow super_admin to reassign pending approvals
3. **Conditional Steps**: Add condition evaluation for PR fields (e.g., if total_value > 10000, add CFO approval)
4. **Parallel Approvals**: Support multiple approvers at same step (requires all/any logic)
5. **Notification System**: Email/in-app notifications for pending approvals
6. **Approval Analytics**: Dashboard showing approval turnaround times, bottlenecks
7. **Audit Trail**: Enhanced logging of approval actions for compliance

## Migration Notes

### Existing PRs

- PRs in `SUBMITTED` status before this integration will need manual handling
- Options:
    1. Keep as `SUBMITTED` (legacy status, manual approval)
    2. Run migration script to convert to `PENDING_APPROVAL` and initialize workflow
    3. Bulk approve all legacy submitted PRs

### Database Seeding

Ensure `PR_STANDARD` workflow is seeded with at least one step:

```bash
php artisan db:seed --class=ApprovalWorkflowSeeder
```

The default seeder creates a single-step workflow:

- Code: `PR_STANDARD`
- Step: "Department Head Approval"
- Approver Type: `DEPARTMENT_HEAD`
- Sequence: 1

## Related Files

**Backend:**

- `app/Models/PurchaseRequest.php`
- `app/Services/PurchaseRequest/PurchaseRequestService.php`
- `app/Http/Controllers/Api/PurchaseRequestController.php`
- `app/Models/Approval.php`
- `app/Services/Approval/ApprovalWorkflowService.php`

**Frontend:**

- `resources/js/pages/purchase-requests/Show.vue`
- `resources/js/services/purchaseRequestApi.ts`

**Database:**

- `approval_workflows` table
- `approval_workflow_steps` table
- `approvals` table (polymorphic)

## Documentation

- [Approval Workflow Backend](./APPROVAL_WORKFLOW_BACKEND.md)
- [Admin UI Guide](./ADMIN_UI_GUIDE.md)
- [Quick Start Guide](./QUICK_START_ADMIN_UI.md)
