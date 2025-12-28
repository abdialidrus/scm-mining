# Testing Guide: Purchase Request Approval Integration

## Prerequisites

1. **Database Seeded**: Ensure approval workflows are seeded

    ```bash
    php artisan db:seed --class=ApprovalWorkflowSeeder
    ```

2. **Test Users**: Create test users with appropriate roles
    - Regular user (requester)
    - Department head user
    - Other approvers (if multi-step workflow)

3. **Test Department**: Ensure department has a head assigned
    ```sql
    UPDATE departments SET head_user_id = [user_id] WHERE code = 'TEST_DEPT';
    ```

## Test Scenarios

### Scenario 1: Happy Path - Single Approval

**Steps:**

1. Login as regular user (requester)
2. Navigate to Purchase Requests → Create New
3. Fill in form:
    - Department: Any department
    - Add at least one line item
    - Add remarks (optional)
4. Click "Save" → PR created in DRAFT status
5. Click "Submit" → PR changes to PENDING_APPROVAL
6. Logout and login as department head
7. Navigate to PR detail page
8. Verify "Approval Workflow" section appears with:
    - Step 1: "Department Head Approval" - Status: PENDING
    - Your name as assigned approver
9. Click "Approve" button
10. Dialog opens → Enter optional comments: "Approved for purchase"
11. Click "Approve" in dialog
12. **Expected Results:**
    - Approval status changes to APPROVED
    - Approver name, timestamp, and comments display
    - PR status changes to APPROVED
    - "Approved By" field populates in header

### Scenario 2: Rejection Flow

**Steps:**

1. Follow steps 1-6 from Scenario 1
2. On PR detail page, click "Reject" button
3. Dialog opens → Enter reason: "Budget constraints, please revise"
4. Click "Reject" in dialog
5. **Expected Results:**
    - Approval status changes to REJECTED
    - Rejecter name, timestamp, and reason display in red text
    - PR status changes to REJECTED
    - Any remaining approvals are CANCELLED

### Scenario 3: Authorization Tests

**Test 3A: Requester Cannot Approve Own PR**

1. Login as user A
2. Create and submit a PR
3. Verify PR is in PENDING_APPROVAL
4. Try to approve the PR as user A
5. **Expected Result:** Error message "Requester cannot approve their own PR"

**Test 3B: Non-authorized User Cannot Approve**

1. Login as user A (requester)
2. Create and submit a PR in Department X (head = user B)
3. Logout and login as user C (not department head, not assigned)
4. Navigate to PR detail page
5. Try to approve the PR as user C
6. **Expected Result:** Error message "You are not authorized to approve this PR at this stage"

**Test 3C: Cannot Approve Non-Pending PR**

1. Create PR with DRAFT status
2. Try to approve directly
3. **Expected Result:** Error message "Only PENDING_APPROVAL PR can be approved"
4. Repeat with APPROVED status PR
5. **Expected Result:** Same error message

### Scenario 4: Multi-Step Workflow (if configured)

**Prerequisites:** Create a multi-step PR workflow via Admin UI:

- Step 1: Department Head Approval
- Step 2: Procurement Manager Approval

**Steps:**

1. Create and submit PR (same as Scenario 1)
2. Login as department head → Approve
3. **Expected Results:**
    - Step 1 shows APPROVED
    - Step 2 shows PENDING
    - PR status remains PENDING_APPROVAL (not yet fully approved)
4. Login as procurement manager → Approve
5. **Expected Results:**
    - Step 2 shows APPROVED
    - PR status changes to APPROVED

### Scenario 5: Frontend Display

**Verify Approvals Section Displays Correctly:**

For a PR with 2 steps (1 approved, 1 pending):

```
Approval Workflow
━━━━━━━━━━━━━━━━━━━━━━━

[1] Department Head Approval        [APPROVED]
    John Doe
    Approved by: John Doe
    2025-01-15 10:30:00
    Comments: Looks good, approved

[2] Procurement Manager Approval    [PENDING]
    Role: procurement_manager
```

For a rejected PR:

```
[1] Department Head Approval        [REJECTED]
    John Doe
    Rejected by: John Doe
    2025-01-15 10:30:00
    Reason: Budget constraints, please revise

[2] Procurement Manager Approval    [CANCELLED]
    Role: procurement_manager
```

### Scenario 6: API Testing (Postman/Insomnia)

**Test Submit PR:**

```bash
POST /api/purchase-requests/{id}/submit
Authorization: Bearer {token}

Response 200:
{
  "data": {
    "id": 123,
    "status": "PENDING_APPROVAL",
    "approvals": [
      {
        "id": 1,
        "status": "PENDING",
        "assigned_to_user_id": 5,
        "step": {
          "name": "Department Head Approval"
        }
      }
    ]
  }
}
```

**Test Approve with Comments:**

```bash
POST /api/purchase-requests/{id}/approve
Authorization: Bearer {token}
Content-Type: application/json

{
  "comments": "Approved for immediate purchase"
}

Response 200:
{
  "data": {
    "id": 123,
    "status": "APPROVED", // if all steps complete
    "approvals": [
      {
        "id": 1,
        "status": "APPROVED",
        "approved_by_user_id": 5,
        "approved_at": "2025-01-15T10:30:00Z",
        "comments": "Approved for immediate purchase"
      }
    ]
  }
}
```

**Test Reject with Reason:**

```bash
POST /api/purchase-requests/{id}/reject
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "Insufficient budget allocation"
}

Response 200:
{
  "data": {
    "id": 123,
    "status": "REJECTED",
    "approvals": [
      {
        "id": 1,
        "status": "REJECTED",
        "rejected_by_user_id": 5,
        "rejected_at": "2025-01-15T10:30:00Z",
        "rejection_reason": "Insufficient budget allocation"
      }
    ]
  }
}
```

## Error Scenarios to Test

| Scenario                           | Expected Error                                               |
| ---------------------------------- | ------------------------------------------------------------ |
| Approve without pending approval   | "No pending approval found for this PR"                      |
| Approve already approved PR        | "Only PENDING_APPROVAL PR can be approved"                   |
| Reject DRAFT PR                    | "Only PENDING_APPROVAL PR can be rejected"                   |
| Approve as requester               | "Requester cannot approve their own PR"                      |
| Reject as requester                | "Requester cannot reject their own PR"                       |
| Approve without authorization      | "You are not authorized to approve this PR at this stage"    |
| Submit with no workflow configured | PR still submits (fallback behavior), logged in Laravel logs |

## Performance Testing

**Load Test - Multiple Approvals:**

1. Create 100 PRs in DRAFT
2. Submit all 100 → Should create 100 approval instances
3. Measure:
    - Submit time per PR (should be < 200ms)
    - Query count (should use eager loading, ~5-7 queries per submit)
    - Database response time

## Browser Compatibility

Test the frontend UI in:

- [x] Chrome/Edge (latest)
- [x] Firefox (latest)
- [x] Safari (latest)

**Key UI Elements:**

- Approval workflow section layout
- Status badges (colors, text)
- Approval/Reject dialogs
- Comments/reason input fields

## Cleanup After Testing

```sql
-- Reset test PRs
DELETE FROM approvals WHERE approvable_type = 'App\\Models\\PurchaseRequest';
DELETE FROM purchase_request_status_histories WHERE purchase_request_id IN (SELECT id FROM purchase_requests WHERE pr_number LIKE 'TEST%');
DELETE FROM purchase_request_lines WHERE purchase_request_id IN (SELECT id FROM purchase_requests WHERE pr_number LIKE 'TEST%');
DELETE FROM purchase_requests WHERE pr_number LIKE 'TEST%';
```

## Troubleshooting

**Issue:** Approvals section doesn't show

- **Check:** PR status is PENDING_APPROVAL or later
- **Check:** Backend eager loads `approvals` relationship
- **Check:** Workflow was initiated on submit

**Issue:** "No pending approval found"

- **Check:** Workflow exists and is active
- **Check:** `ApprovalWorkflowSeeder` ran successfully
- **Check:** Workflow steps exist for `PR_STANDARD` code

**Issue:** User cannot approve but should be authorized

- **Check:** User has correct role assigned
- **Check:** Approval `assigned_to_role` matches user's role
- **Check:** Department head is set correctly in departments table

**Issue:** Approval doesn't complete workflow

- **Check:** All previous steps are APPROVED
- **Check:** No pending approvals remain
- **Check:** `isWorkflowComplete()` returns true

## Test Coverage Summary

- [x] Submit PR initiates workflow
- [x] Approval workflow displays correctly
- [x] Approve with comments
- [x] Approve without comments
- [x] Reject with reason
- [x] Multi-step approval progression
- [x] Authorization checks (requester, unauthorized user)
- [x] Status validation (can't approve DRAFT, APPROVED, REJECTED)
- [x] Eager loading relationships (no N+1 queries)
- [x] Polymorphic approvals relationship works
- [x] Frontend dialogs function correctly
- [x] API responses include approvals data
- [x] Rejection cancels remaining approvals
