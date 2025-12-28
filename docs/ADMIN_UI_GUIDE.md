# Approval Workflow Admin UI

## Overview

The Approval Workflow Admin UI provides a user-friendly interface for super_admin users to manage approval workflow configurations without writing code. This allows non-technical administrators to:

- Create and edit approval workflows
- Configure approval steps with conditions
- Set up role-based, user-based, or dynamic approvals
- Manage workflow activation status

## Access Control

- **Role Required**: `super_admin`
- **Navigation**: Settings → Approval Workflows (visible only to super_admins)
- **Routes**: `/approval-workflows`

## Features Implemented

### 1. Workflow Management

**List View** (`/approval-workflows`)

- Search workflows by name or code
- Filter by document type (Purchase Request, Purchase Order, Goods Receipt)
- View workflow status (Active/Inactive)
- See step count at a glance
- Click to edit

**Create/Edit Form** (`/approval-workflows/create` or `/approval-workflows/{id}/edit`)

- **Basic Info**:
    - Code (unique identifier, e.g., `PO_STANDARD`)
    - Name (display name)
    - Description (optional)
    - Document Type (PURCHASE_REQUEST, PURCHASE_ORDER, GOODS_RECEIPT)
    - Active status toggle

### 2. Step Management

Within each workflow edit page, admins can:

**Add Steps**:

- Step Order (numeric position in workflow)
- Approver Type:
    - **ROLE**: Approval by any user with specified role
    - **USER**: Approval by specific user ID
    - **DEPARTMENT_HEAD**: Approval by document creator's department head
    - **DYNAMIC**: Custom approver resolution logic
- Role selection (dropdown of available roles when ROLE type selected)
- User ID input (when USER type selected)
- Conditional Logic (optional):
    - Field (e.g., `total_amount`)
    - Operator (`>=`, `>`, `<=`, `<`, `=`, `!=`)
    - Value (e.g., `50000000`)
- Final Step flag (marks last approval step)

**Edit Steps**:

- Click "Edit" button on any step
- Modify any field
- Save changes

**Delete Steps**:

- Click trash icon
- Confirm deletion

### 3. Workflow Deletion

- Delete entire workflow via "Delete Workflow" button
- Protected: Cannot delete workflows currently in use
- Confirmation required

## API Endpoints

All endpoints are under `/api/approval-workflows` and require authentication + super_admin role:

### Workflow Endpoints

- `GET /api/approval-workflows` - List workflows (with filters)
- `POST /api/approval-workflows` - Create workflow
- `GET /api/approval-workflows/{id}` - Get workflow details
- `PUT /api/approval-workflows/{id}` - Update workflow
- `DELETE /api/approval-workflows/{id}` - Delete workflow

### Step Endpoints

- `POST /api/approval-workflows/{workflow}/steps` - Create step
- `PUT /api/approval-workflows/{workflow}/steps/{step}` - Update step
- `DELETE /api/approval-workflows/{workflow}/steps/{step}` - Delete step
- `PUT /api/approval-workflows/{workflow}/steps/reorder` - Reorder steps

## Usage Guide

### Creating a New Approval Workflow

1. Navigate to Settings → Approval Workflows
2. Click "Create" button
3. Fill in workflow details:
    - Code: `PR_DEPARTMENT` (example for PR approval by dept head)
    - Name: "Purchase Request Department Approval"
    - Description: "Simple PR approval by department head"
    - Document Type: "Purchase Request"
    - Active: ✓ (checked)
4. Click "Create Workflow"
5. After creation, you'll be redirected to edit page to add steps

### Adding Approval Steps

**Example: Purchase Order with Amount-Based Approval**

**Step 1: Finance (Always Required)**

- Step Order: 1
- Approver Type: ROLE
- Role: finance
- Condition: (leave empty - always required)
- Final Step: ☐ (unchecked)

**Step 2: General Manager (≥50M)**

- Step Order: 2
- Approver Type: ROLE
- Role: gm
- Condition:
    - Field: `total_amount`
    - Operator: `>=`
    - Value: `50000000`
- Final Step: ☐ (unchecked)

**Step 3: Director (≥100M)**

- Step Order: 3
- Approver Type: ROLE
- Role: director
- Condition:
    - Field: `total_amount`
    - Operator: `>=`
    - Value: `100000000`
- Final Step: ✓ (checked)

### Editing Existing Workflows

1. From list view, click on a workflow row
2. Edit basic info section as needed
3. Click "Update Workflow"
4. To edit steps, click "Edit" button on any step
5. Modify fields and click "Update Step"

### Activating/Deactivating Workflows

1. Open workflow edit page
2. Toggle "Active" checkbox
3. Click "Update Workflow"
4. Inactive workflows won't be used for new documents

## Conditional Logic Examples

### Amount-Based Conditions

```
Field: total_amount
Operator: >=
Value: 50000000
```

Only applies step if PO total is 50M or more

### Status-Based Conditions

```
Field: status
Operator: =
Value: PENDING_APPROVAL
```

Only applies if status matches exactly

### Department-Based Conditions

```
Field: department_id
Operator: !=
Value: 5
```

Skip step if document is from department ID 5

## Best Practices

1. **Use Clear Codes**: Use descriptive workflow codes like `PO_STANDARD`, `PR_URGENT`, `GR_QUALITY_CHECK`

2. **Order Steps Logically**: Start with lowest-level approval (e.g., Department Head) before higher levels (GM, Director)

3. **Mark Final Steps**: Always mark the last step as "Final Step" for clarity

4. **Test Before Activating**: Create workflow as inactive, test thoroughly, then activate

5. **Document Conditions**: Use description field to explain complex conditional logic

6. **Role vs User**: Prefer ROLE approver type over USER for flexibility (allows any user with role to approve)

7. **Department Head Type**: Use DEPARTMENT_HEAD when approval should come from document creator's direct management

## Troubleshooting

### Cannot Delete Workflow

**Error**: "Cannot delete workflow that is currently in use"
**Solution**: This workflow has active approvals. Create a new workflow and deactivate the old one instead.

### Step Not Triggering

**Issue**: Conditional step not appearing for users
**Check**:

1. Verify condition field name matches document model attribute exactly (case-sensitive)
2. Ensure condition value type matches (numeric vs string)
3. Check operator logic (>= vs >)

### Wrong Role Seeing Approval

**Issue**: Approval shown to wrong users
**Check**:

1. Verify role name matches exactly in Spatie permissions
2. Check if multiple roles assigned to user
3. Review step order - earlier steps must complete first

## Technical Details

### Frontend Files

- `resources/js/pages/approval-workflows/Index.vue` - List page
- `resources/js/pages/approval-workflows/Form.vue` - Create/Edit page
- `resources/js/services/approvalWorkflowApi.ts` - API client
- `resources/js/components/AppSidebar.vue` - Navigation (Settings section)

### Backend Files

- `app/Http/Controllers/Api/ApprovalWorkflowController.php` - API controller
- `app/Http/Controllers/ApprovalWorkflowPageController.php` - Page controller
- `app/Policies/ApprovalWorkflowPolicy.php` - Authorization
- `routes/api.php` - API routes
- `routes/approval_workflows.php` - Web routes

### Database Tables

- `approval_workflows` - Workflow definitions
- `approval_workflow_steps` - Step configurations
- `approvals` - Runtime approval instances (created automatically)

## Next Steps

After setting up workflows through the UI:

1. **Test with Real Documents**: Create test POs/PRs and verify approval flow
2. **Train Users**: Show procurement/finance users how to view pending approvals
3. **Monitor Usage**: Check `approvals` table for patterns and bottlenecks
4. **Iterate**: Adjust conditions and add steps based on business needs
5. **Document Policies**: Create internal documentation of which workflows apply to which scenarios

## Support

For technical issues or feature requests, contact your development team with:

- Workflow code/ID
- Steps configuration
- Expected vs actual behavior
- Screenshots from UI
