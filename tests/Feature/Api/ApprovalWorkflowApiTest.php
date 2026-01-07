<?php

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create role if not exists
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');
    Sanctum::actingAs($this->user);
});

test('it can list all approval workflows', function () {
    ApprovalWorkflow::create([
        'code' => 'PO_STANDARD',
        'name' => 'Purchase Order Standard Approval',
        'model_type' => 'App\Models\PurchaseOrder',
        'is_active' => true,
    ]);

    ApprovalWorkflow::create([
        'code' => 'PR_STANDARD',
        'name' => 'Purchase Request Standard Approval',
        'model_type' => 'App\Models\PurchaseRequest',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/approval-workflows');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => ['id', 'code', 'name', 'description', 'model_type', 'document_type', 'is_active', 'created_at', 'updated_at']
                ]
            ]
        ]);

    $workflows = $response->json('data.data');
    expect($workflows)->toHaveCount(2);
});

test('it can view a single approval workflow with steps', function () {
    $workflow = ApprovalWorkflow::create([
        'code' => 'PO_STANDARD',
        'name' => 'Purchase Order Standard Approval',
        'model_type' => 'App\Models\PurchaseOrder',
        'is_active' => true,
    ]);

    ApprovalWorkflowStep::create([
        'approval_workflow_id' => $workflow->id,
        'step_order' => 1,
        'step_code' => 'FINANCE',
        'step_name' => 'Finance Review',
        'approver_type' => 'ROLE',
        'approver_value' => 'finance',
        'is_required' => true,
    ]);

    $response = $this->getJson("/api/approval-workflows/{$workflow->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $workflow->id)
        ->assertJsonPath('data.code', 'PO_STANDARD')
        ->assertJsonPath('data.name', 'Purchase Order Standard Approval')
        ->assertJsonStructure([
            'data' => [
                'id',
                'code',
                'name',
                'ordered_steps' => [
                    '*' => ['id', 'step_order', 'step_code', 'step_name', 'approver_type']
                ]
            ]
        ]);
});

test('it returns 404 for non-existent approval workflow', function () {
    $response = $this->getJson('/api/approval-workflows/99999');

    $response->assertNotFound();
});

test('it can create a new approval workflow', function () {
    $response = $this->postJson('/api/approval-workflows', [
        'code' => 'GR_STANDARD',
        'name' => 'Goods Receipt Standard Approval',
        'description' => 'Standard approval for goods receipts',
        'document_type' => 'GOODS_RECEIPT',
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'GR_STANDARD')
        ->assertJsonPath('data.name', 'Goods Receipt Standard Approval')
        ->assertJsonPath('data.document_type', 'GOODS_RECEIPT');

    $this->assertDatabaseHas('approval_workflows', [
        'code' => 'GR_STANDARD',
        'name' => 'Goods Receipt Standard Approval',
        'model_type' => 'App\Models\GoodsReceipt',
    ]);
});

test('it validates required fields when creating', function () {
    $response = $this->postJson('/api/approval-workflows', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'name', 'document_type']);
});

test('it validates code format when creating', function () {
    $response = $this->postJson('/api/approval-workflows', [
        'code' => 'invalid-code-format',
        'name' => 'Test Workflow',
        'document_type' => 'PURCHASE_ORDER',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('it validates code uniqueness when creating', function () {
    ApprovalWorkflow::create([
        'code' => 'PO_STANDARD',
        'name' => 'Existing Workflow',
        'model_type' => 'App\Models\PurchaseOrder',
    ]);

    $response = $this->postJson('/api/approval-workflows', [
        'code' => 'PO_STANDARD',
        'name' => 'New Workflow',
        'document_type' => 'PURCHASE_ORDER',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('it validates document_type values when creating', function () {
    $response = $this->postJson('/api/approval-workflows', [
        'code' => 'TEST_WORKFLOW',
        'name' => 'Test Workflow',
        'document_type' => 'INVALID_TYPE',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['document_type']);
});

test('it can update an approval workflow', function () {
    $workflow = ApprovalWorkflow::create([
        'code' => 'PO_STANDARD',
        'name' => 'Purchase Order Standard',
        'model_type' => 'App\Models\PurchaseOrder',
        'is_active' => true,
    ]);

    $response = $this->putJson("/api/approval-workflows/{$workflow->id}", [
        'code' => 'PO_STANDARD',
        'name' => 'Purchase Order Standard Updated',
        'description' => 'Updated description',
        'document_type' => 'PURCHASE_ORDER',
        'is_active' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Purchase Order Standard Updated')
        ->assertJsonPath('data.description', 'Updated description')
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('approval_workflows', [
        'id' => $workflow->id,
        'name' => 'Purchase Order Standard Updated',
        'is_active' => false,
    ]);
});

test('it can delete an approval workflow', function () {
    $workflow = ApprovalWorkflow::create([
        'code' => 'TEMP_WORKFLOW',
        'name' => 'Temporary Workflow',
        'model_type' => 'App\Models\PurchaseOrder',
    ]);

    $response = $this->deleteJson("/api/approval-workflows/{$workflow->id}");

    $response->assertOk();

    $this->assertDatabaseMissing('approval_workflows', [
        'id' => $workflow->id,
    ]);
});

test('it can filter workflows by document type', function () {
    ApprovalWorkflow::create([
        'code' => 'PO_STANDARD',
        'name' => 'Purchase Order Approval',
        'model_type' => 'App\Models\PurchaseOrder',
    ]);

    ApprovalWorkflow::create([
        'code' => 'PR_STANDARD',
        'name' => 'Purchase Request Approval',
        'model_type' => 'App\Models\PurchaseRequest',
    ]);

    $response = $this->getJson('/api/approval-workflows?document_type=App\Models\PurchaseOrder');

    $response->assertOk();

    $workflows = $response->json('data.data');
    expect($workflows)->toHaveCount(1);
    expect($workflows[0]['document_type'])->toBe('PURCHASE_ORDER');
});

test('it can filter workflows by active status', function () {
    ApprovalWorkflow::create([
        'code' => 'ACTIVE_WF',
        'name' => 'Active Workflow',
        'model_type' => 'App\Models\PurchaseOrder',
        'is_active' => true,
    ]);

    ApprovalWorkflow::create([
        'code' => 'INACTIVE_WF',
        'name' => 'Inactive Workflow',
        'model_type' => 'App\Models\PurchaseOrder',
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/approval-workflows?is_active=1');

    $response->assertOk();

    $workflows = $response->json('data.data');
    expect($workflows)->toHaveCount(1);
    expect($workflows[0]['is_active'])->toBe(true);
});

test('it can search workflows by code or name', function () {
    ApprovalWorkflow::create([
        'code' => 'PO_STANDARD',
        'name' => 'Purchase Order Approval',
        'model_type' => 'App\Models\PurchaseOrder',
    ]);

    ApprovalWorkflow::create([
        'code' => 'PR_STANDARD',
        'name' => 'Purchase Request Approval',
        'model_type' => 'App\Models\PurchaseRequest',
    ]);

    $response = $this->getJson('/api/approval-workflows?search=Purchase Order');

    $response->assertOk();

    $workflows = $response->json('data.data');
    expect($workflows)->toHaveCount(1);
    expect($workflows[0]['code'])->toBe('PO_STANDARD');
});

test('it converts document_type to model_type correctly', function () {
    $workflow = new ApprovalWorkflow();
    $workflow->code = 'TEST_WF';
    $workflow->name = 'Test Workflow';
    $workflow->document_type = 'PURCHASE_REQUEST'; // Use setter
    $workflow->save();

    expect($workflow->model_type)->toBe('App\Models\PurchaseRequest');
    expect($workflow->document_type)->toBe('PURCHASE_REQUEST');
});
