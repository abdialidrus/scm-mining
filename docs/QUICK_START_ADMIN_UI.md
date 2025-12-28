# Quick Start: Testing the Approval Workflow Admin UI

## Prerequisites

Your approval workflow backend is already implemented and ready. Now you can test the new admin UI.

## Step 1: Start the Development Server

If not already running:

```bash
npm run dev
```

## Step 2: Run Database Migrations

If you haven't already run the approval workflow migrations:

```bash
php artisan migrate
```

## Step 3: Seed Initial Data

Run the seeder to create default workflows and a test super_admin user:

```bash
php artisan db:seed --class=ApprovalWorkflowSeeder
```

This creates:

- `PO_STANDARD`: Purchase Order approval workflow (Finance → GM if ≥50M → Director if ≥100M)
- `PR_STANDARD`: Purchase Request approval workflow (Department Head)

## Step 4: Access the Admin UI

1. **Login** as a user with `super_admin` role
    - If you don't have one, create it:

    ```bash
    php artisan tinker

    $user = User::first(); // or find your user
    $user->assignRole('super_admin');
    ```

2. **Navigate** to the application in your browser

3. **Look for "Settings"** section in the left sidebar
    - You should see "Approval Workflows" menu item with a branch icon
    - This only appears for super_admin users

4. **Click "Approval Workflows"** to access the management interface

## Step 5: Explore the UI

### List View

- You should see 2 workflows from the seeder: `PO_STANDARD` and `PR_STANDARD`
- Try the search box: search for "Purchase"
- Try the document type filter: select "Purchase Order"
- Click on a workflow row to edit it

### Edit View

- You'll see workflow details at the top
- Below that, you'll see the approval steps
- For `PO_STANDARD`, you should see 3 steps:
    - Step 1: Finance (ROLE) - no condition
    - Step 2: GM (ROLE) - condition: total_amount >= 50000000
    - Step 3: Director (ROLE) - condition: total_amount >= 100000000, marked as final

### Try Editing a Step

1. Click "Edit" on Step 2 (GM)
2. Change the amount condition to 75000000
3. Click "Update Step"
4. Verify the change is reflected

### Add a New Step

1. Scroll to "Add New Step" section
2. Set Step Order to 4
3. Select Approver Type: "Role"
4. Select Role: "warehouse"
5. Leave condition empty
6. Check "Final Step"
7. Click "Add Step"
8. Verify new step appears

### Delete a Step

1. Click the trash icon on the step you just created
2. Confirm deletion
3. Verify step is removed

## Step 6: Create Your Own Workflow

1. Click "Back" to return to list
2. Click "Create" button
3. Fill in:
    - Code: `TEST_WORKFLOW`
    - Name: "Test Approval Workflow"
    - Description: "Testing the admin UI"
    - Document Type: "Purchase Order"
    - Active: ✓
4. Click "Create Workflow"
5. You'll be redirected to edit page
6. Add a step:
    - Step Order: 1
    - Approver Type: "Role"
    - Role: "procurement"
    - Final Step: ✓
7. Click "Add Step"
8. Return to list and verify your workflow appears

## Step 7: Test with Real Documents

Now test if your workflow actually works:

1. Create a Purchase Order through the system
2. Submit it for approval
3. Check if the approval workflow is initiated
4. Login as a user with the appropriate role (e.g., finance)
5. Verify they see the pending approval
6. Approve the PO
7. Check if it moves to the next step (GM) if amount >= 50M

You can use the test command:

```bash
php artisan test:approval-workflow
```

## Step 8: Verify in Database

Check the database to see the workflow in action:

```bash
php artisan tinker

// See your workflows
\App\Models\ApprovalWorkflow::with('steps')->get();

// See approvals for a specific PO
$po = \App\Models\PurchaseOrder::first();
$po->approvals;
```

## Common Issues

### "Settings" Section Not Visible

**Problem**: User doesn't have super_admin role
**Solution**:

```bash
php artisan tinker
$user = User::where('email', 'your@email.com')->first();
$user->assignRole('super_admin');
```

### Cannot Delete Workflow

**Problem**: Workflow is in use by existing documents
**Solution**: This is expected behavior. Deactivate the workflow instead:

1. Open workflow edit page
2. Uncheck "Active"
3. Click "Update Workflow"

### Steps Not Triggering

**Problem**: Condition field name doesn't match
**Solution**:

- For PO: Use exact field names like `total_amount`, `supplier_id`, `status`
- Check `app/Models/PurchaseOrder.php` for available fields
- Conditions are case-sensitive

### Role Not Found

**Problem**: Selected role doesn't exist in Spatie permissions
**Solution**:

```bash
php artisan tinker
Spatie\Permission\Models\Role::all(); // See all roles
// If role missing, create it:
Spatie\Permission\Models\Role::create(['name' => 'your_role']);
```

## What's Next?

After testing the UI:

1. ✅ **Configure Real Workflows**: Replace test workflows with your actual approval processes
2. ✅ **Train Users**: Show super_admins how to manage workflows
3. ✅ **Document Policies**: Create internal documentation of approval rules
4. ✅ **Monitor Usage**: Watch for bottlenecks and adjust workflows
5. ✅ **Iterate**: Add more conditional logic as needed

## Features to Explore

- **Conditional Logic**: Test different operators (>=, >, <=, <, =, !=)
- **Multiple Conditions**: Create steps that only trigger under specific circumstances
- **Department Head**: Use DEPARTMENT_HEAD approver type for automatic routing
- **Inactive Workflows**: Test that inactive workflows aren't used for new documents
- **Search & Filter**: Test list view filtering by document type and search

## Documentation

- `ADMIN_UI_GUIDE.md` - Complete user guide with examples
- `ADMIN_UI_IMPLEMENTATION_SUMMARY.md` - Technical implementation details
- `APPROVAL_WORKFLOW_IMPLEMENTATION.md` - Backend workflow system documentation
- `TESTING_APPROVAL_WORKFLOW.md` - Testing guide

## Support

If you encounter issues:

1. Check browser console for JavaScript errors
2. Check Laravel logs: `storage/logs/laravel.log`
3. Verify database migrations ran successfully
4. Confirm user has super_admin role
5. Check network tab in browser dev tools for API errors

Happy testing! 🎉
