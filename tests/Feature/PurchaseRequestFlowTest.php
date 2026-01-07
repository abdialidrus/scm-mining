<?php

declare(strict_types=1);

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Department;
use App\Models\Item;
use App\Models\Uom;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    // Seed approval workflow for Purchase Request tests
    // Note: PurchaseRequestService.submit() looks for 'PR_STANDARD' workflow
    $this->workflow = ApprovalWorkflow::create([
        'code' => 'PR_STANDARD',
        'name' => 'PR Test Workflow',
        'model_type' => 'App\Models\PurchaseRequest',
        'is_active' => true,
    ]);

    ApprovalWorkflowStep::create([
        'approval_workflow_id' => $this->workflow->id,
        'step_order' => 1,
        'step_code' => 'DEPT_HEAD',
        'step_name' => 'Department Head',
        'approver_type' => ApprovalWorkflowStep::APPROVER_TYPE_DEPARTMENT_HEAD,
        'is_required' => true,
        'allow_skip' => false,
        'allow_parallel' => false,
    ]);

    // Create procurement role for approval tests
    Role::firstOrCreate(['name' => 'procurement', 'guard_name' => 'web']);
});

it('allows requester to create draft, submit, then department head can approve', function () {
    $uom = Uom::query()->create(['code' => 'EA', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-001',
        'name' => 'Test Item',
        'base_uom_id' => $uom->id,
    ]);

    $requester = User::factory()->create(['department_id' => null]);
    $head = User::factory()->create(['department_id' => null]);

    $dept = Department::query()->create([
        'code' => 'D01',
        'name' => 'Ops',
        'head_user_id' => $head->id,
    ]);

    $requester->forceFill(['department_id' => $dept->id])->save();
    $head->forceFill(['department_id' => $dept->id])->save();

    Sanctum::actingAs($requester);

    $create = postJson('/api/purchase-requests', [
        'department_id' => $dept->id,
        'remarks' => 'draft',
        'lines' => [
            [
                'item_id' => $item->id,
                'quantity' => 2,
                'uom_id' => $uom->id,
                'remarks' => 'line',
            ],
        ],
    ])->assertCreated();

    $prId = $create->json('data.id');

    postJson("/api/purchase-requests/{$prId}/submit", [])
        ->assertOk()
        ->assertJsonPath('data.status', 'PENDING_APPROVAL');

    // requester cannot approve
    postJson("/api/purchase-requests/{$prId}/approve", [])
        ->assertForbidden();

    Sanctum::actingAs($head);

    // Department head approves
    postJson("/api/purchase-requests/{$prId}/approve", [])
        ->assertOk()
        ->assertJsonPath('data.status', 'APPROVED');

    // Verify final status
    $response = getJson("/api/purchase-requests/{$prId}");
    $response->assertOk();
    expect($response->json('data.status'))->toBe('APPROVED');
});

it('requires reject reason and records status history', function () {
    $uom = Uom::query()->create(['code' => 'EA2', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-002',
        'name' => 'Test Item 2',
        'base_uom_id' => $uom->id,
    ]);

    $requester = User::factory()->create();
    $head = User::factory()->create();

    $dept = Department::query()->create([
        'code' => 'D02',
        'name' => 'Plant',
        'head_user_id' => $head->id,
    ]);

    $requester->forceFill(['department_id' => $dept->id])->save();
    $head->forceFill(['department_id' => $dept->id])->save();

    Sanctum::actingAs($requester);

    $create = postJson('/api/purchase-requests', [
        'department_id' => $dept->id,
        'remarks' => null,
        'lines' => [
            ['item_id' => $item->id, 'quantity' => 1, 'uom_id' => $uom->id],
        ],
    ])->assertCreated();

    $prId = $create->json('data.id');

    postJson("/api/purchase-requests/{$prId}/submit", [])->assertOk();

    Sanctum::actingAs($head);

    // reject reason required
    postJson("/api/purchase-requests/{$prId}/reject", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    // Reject with valid reason
    postJson("/api/purchase-requests/{$prId}/reject", [
        'reason' => 'Item not needed anymore'
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'REJECTED');

    // Verify final status and status history
    $response = getJson("/api/purchase-requests/{$prId}");
    $response->assertOk();
    expect($response->json('data.status'))->toBe('REJECTED');

    // Check status history recorded rejection
    $history = $response->json('data.status_histories');
    expect($history)->toBeArray();
    expect(count($history))->toBeGreaterThanOrEqual(2); // At least PENDING_APPROVAL and REJECTED
});

it('supports multi-step approval workflow', function () {
    // Create a multi-step workflow: DEPT_HEAD -> PROCUREMENT
    ApprovalWorkflowStep::create([
        'approval_workflow_id' => $this->workflow->id,
        'step_order' => 2,
        'step_code' => 'PROCUREMENT',
        'step_name' => 'Procurement Review',
        'approver_type' => ApprovalWorkflowStep::APPROVER_TYPE_ROLE,
        'approver_value' => 'procurement',
        'is_required' => true,
        'allow_skip' => false,
        'allow_parallel' => false,
    ]);

    $uom = Uom::query()->create(['code' => 'EA3', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-003',
        'name' => 'Multi Step Item',
        'base_uom_id' => $uom->id,
    ]);

    $requester = User::factory()->create(['department_id' => null]);
    $head = User::factory()->create(['department_id' => null]);
    $procurementUser = User::factory()->create(['department_id' => null]);
    $procurementUser->assignRole('procurement');

    $dept = Department::query()->create([
        'code' => 'D03',
        'name' => 'Engineering',
        'head_user_id' => $head->id,
    ]);

    $requester->forceFill(['department_id' => $dept->id])->save();
    $head->forceFill(['department_id' => $dept->id])->save();

    Sanctum::actingAs($requester);

    // Create and submit PR
    $create = postJson('/api/purchase-requests', [
        'department_id' => $dept->id,
        'remarks' => 'Multi-step test',
        'lines' => [
            [
                'item_id' => $item->id,
                'quantity' => 10,
                'uom_id' => $uom->id,
            ],
        ],
    ])->assertCreated();

    $prId = $create->json('data.id');

    postJson("/api/purchase-requests/{$prId}/submit", [])->assertOk();

    // Step 1: Department head approves
    Sanctum::actingAs($head);
    postJson("/api/purchase-requests/{$prId}/approve", [
        'comments' => 'Approved by dept head'
    ])->assertOk();

    // Status should still be PENDING_APPROVAL (waiting for procurement)
    $response = getJson("/api/purchase-requests/{$prId}");
    expect($response->json('data.status'))->toBe('PENDING_APPROVAL');

    // Step 2: Procurement user approves (final step)
    Sanctum::actingAs($procurementUser);
    postJson("/api/purchase-requests/{$prId}/approve", [
        'comments' => 'Approved by procurement'
    ])->assertOk();

    // Switch back to requester to view (policy authorization)
    Sanctum::actingAs($requester);
    $response = getJson("/api/purchase-requests/{$prId}");
    expect($response->json('data.status'))->toBe('APPROVED');
});

it('prevents skipping approval steps when not allowed', function () {
    // Create a two-step workflow where first step cannot be skipped
    ApprovalWorkflowStep::create([
        'approval_workflow_id' => $this->workflow->id,
        'step_order' => 2,
        'step_code' => 'PROCUREMENT',
        'step_name' => 'Procurement Review',
        'approver_type' => ApprovalWorkflowStep::APPROVER_TYPE_ROLE,
        'approver_value' => 'procurement',
        'is_required' => true,
        'allow_skip' => false,
        'allow_parallel' => false,
    ]);

    $uom = Uom::query()->create(['code' => 'EA4', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-004',
        'name' => 'Skip Test Item',
        'base_uom_id' => $uom->id,
    ]);

    $requester = User::factory()->create(['department_id' => null]);
    $head = User::factory()->create(['department_id' => null]);
    $procurementUser = User::factory()->create(['department_id' => null]);
    $procurementUser->assignRole('procurement');

    $dept = Department::query()->create([
        'code' => 'D04',
        'name' => 'Logistics',
        'head_user_id' => $head->id,
    ]);

    $requester->forceFill(['department_id' => $dept->id])->save();
    $head->forceFill(['department_id' => $dept->id])->save();

    Sanctum::actingAs($requester);

    $create = postJson('/api/purchase-requests', [
        'department_id' => $dept->id,
        'remarks' => 'Skip test',
        'lines' => [
            ['item_id' => $item->id, 'quantity' => 5, 'uom_id' => $uom->id],
        ],
    ])->assertCreated();

    $prId = $create->json('data.id');
    postJson("/api/purchase-requests/{$prId}/submit", [])->assertOk();

    // Procurement user tries to approve (skipping dept head step)
    Sanctum::actingAs($procurementUser);
    postJson("/api/purchase-requests/{$prId}/approve", [])
        ->assertForbidden(); // Should fail - cannot skip dept head approval
});

it('allows approver to add comments during approval', function () {
    $uom = Uom::query()->create(['code' => 'EA5', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-005',
        'name' => 'Comment Test Item',
        'base_uom_id' => $uom->id,
    ]);

    $requester = User::factory()->create(['department_id' => null]);
    $head = User::factory()->create(['department_id' => null]);

    $dept = Department::query()->create([
        'code' => 'D05',
        'name' => 'Sales',
        'head_user_id' => $head->id,
    ]);

    $requester->forceFill(['department_id' => $dept->id])->save();
    $head->forceFill(['department_id' => $dept->id])->save();

    Sanctum::actingAs($requester);

    $create = postJson('/api/purchase-requests', [
        'department_id' => $dept->id,
        'remarks' => 'Comment test',
        'lines' => [
            ['item_id' => $item->id, 'quantity' => 3, 'uom_id' => $uom->id],
        ],
    ])->assertCreated();

    $prId = $create->json('data.id');
    postJson("/api/purchase-requests/{$prId}/submit", [])->assertOk();

    Sanctum::actingAs($head);

    $approvalComments = 'Approved with special instructions';
    postJson("/api/purchase-requests/{$prId}/approve", [
        'comments' => $approvalComments
    ])->assertOk();

    // Verify comments are stored in approval record
    $response = getJson("/api/purchase-requests/{$prId}");
    $approvals = $response->json('data.approvals');
    expect($approvals)->toBeArray();
    expect(count($approvals))->toBeGreaterThan(0);

    // Find the approved record
    $approvedRecord = collect($approvals)->firstWhere('status', 'APPROVED');
    expect($approvedRecord)->not->toBeNull();
    expect($approvedRecord['comments'])->toBe($approvalComments);
});

it('tracks approval history with timestamps', function () {
    $uom = Uom::query()->create(['code' => 'EA6', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-006',
        'name' => 'History Test Item',
        'base_uom_id' => $uom->id,
    ]);

    $requester = User::factory()->create(['department_id' => null]);
    $head = User::factory()->create(['department_id' => null]);

    $dept = Department::query()->create([
        'code' => 'D06',
        'name' => 'Finance',
        'head_user_id' => $head->id,
    ]);

    $requester->forceFill(['department_id' => $dept->id])->save();
    $head->forceFill(['department_id' => $dept->id])->save();

    Sanctum::actingAs($requester);

    $create = postJson('/api/purchase-requests', [
        'department_id' => $dept->id,
        'remarks' => 'History test',
        'lines' => [
            ['item_id' => $item->id, 'quantity' => 1, 'uom_id' => $uom->id],
        ],
    ])->assertCreated();

    $prId = $create->json('data.id');

    $submitTime = now();
    postJson("/api/purchase-requests/{$prId}/submit", [])->assertOk();

    Sanctum::actingAs($head);

    $approveTime = now();
    postJson("/api/purchase-requests/{$prId}/approve", [])->assertOk();

    // Verify approval history includes timestamps
    $response = getJson("/api/purchase-requests/{$prId}");
    $approvals = $response->json('data.approvals');
    expect($approvals)->toBeArray();
    expect(count($approvals))->toBeGreaterThan(0);

    $approvedRecord = collect($approvals)->firstWhere('status', 'APPROVED');
    expect($approvedRecord)->not->toBeNull();
    expect($approvedRecord)->toHaveKey('approved_at');
    expect($approvedRecord['approved_at'])->not->toBeNull();
});

it('prevents duplicate approval by same approver', function () {
    $uom = Uom::query()->create(['code' => 'EA7', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-007',
        'name' => 'Duplicate Test Item',
        'base_uom_id' => $uom->id,
    ]);

    $requester = User::factory()->create(['department_id' => null]);
    $head = User::factory()->create(['department_id' => null]);

    $dept = Department::query()->create([
        'code' => 'D07',
        'name' => 'HR',
        'head_user_id' => $head->id,
    ]);

    $requester->forceFill(['department_id' => $dept->id])->save();
    $head->forceFill(['department_id' => $dept->id])->save();

    Sanctum::actingAs($requester);

    $create = postJson('/api/purchase-requests', [
        'department_id' => $dept->id,
        'remarks' => 'Duplicate test',
        'lines' => [
            ['item_id' => $item->id, 'quantity' => 2, 'uom_id' => $uom->id],
        ],
    ])->assertCreated();

    $prId = $create->json('data.id');
    postJson("/api/purchase-requests/{$prId}/submit", [])->assertOk();

    Sanctum::actingAs($head);

    // First approval succeeds
    postJson("/api/purchase-requests/{$prId}/approve", [])->assertOk();

    // Verify status is APPROVED
    Sanctum::actingAs($requester);
    $response = getJson("/api/purchase-requests/{$prId}");
    expect($response->json('data.status'))->toBe('APPROVED');

    // Attempt second approval should fail (policy prevents approval of already approved PR)
    Sanctum::actingAs($head);
    postJson("/api/purchase-requests/{$prId}/approve", [])
        ->assertForbidden(); // Policy check: only PENDING_APPROVAL can be approved
});
