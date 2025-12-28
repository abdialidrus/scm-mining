<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApprovalWorkflowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApprovalWorkflow::class);

        $query = ApprovalWorkflow::query()
            ->with('steps')
            ->orderBy('code');

        // Filter by search
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter by document_type (model_type in DB)
        if ($request->filled('document_type')) {
            $query->where('model_type', $request->query('document_type'));
        }

        // Filter by is_active
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->query('is_active') === '1');
        }

        // Pagination
        $perPage = $request->query('per_page', 10);
        $workflows = $query->paginate($perPage);

        return response()->json([
            'data' => $workflows,
        ]);
    }

    public function show(ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        $this->authorize('view', $approvalWorkflow);

        return response()->json([
            'data' => $approvalWorkflow->load('orderedSteps'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ApprovalWorkflow::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:approval_workflows,code', 'regex:/^[A-Z0-9_]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'document_type' => ['required', 'string', 'in:PURCHASE_REQUEST,PURCHASE_ORDER,GOODS_RECEIPT'],
            'is_active' => ['boolean'],
        ]);

        // Map document_type to model_type for database
        $data = $validated;
        $data['model_type'] = $validated['document_type'];
        unset($data['document_type']);

        $workflow = ApprovalWorkflow::create($data);
        $workflow->load('steps');

        return response()->json(['data' => $workflow], 201);
    }

    public function update(Request $request, ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        $this->authorize('update', $approvalWorkflow);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('approval_workflows', 'code')->ignore($approvalWorkflow->id), 'regex:/^[A-Z0-9_]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'document_type' => ['required', 'string', 'in:PURCHASE_REQUEST,PURCHASE_ORDER,GOODS_RECEIPT'],
            'is_active' => ['boolean'],
        ]);

        // Map document_type to model_type for database
        $data = $validated;
        $data['model_type'] = $validated['document_type'];
        unset($data['document_type']);

        $approvalWorkflow->update($data);
        $approvalWorkflow->load('steps');

        return response()->json(['data' => $approvalWorkflow->fresh()]);
    }

    public function destroy(ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        $this->authorize('delete', $approvalWorkflow);

        // Check if workflow is in use
        $inUse = DB::table('approvals')
            ->where('approval_workflow_id', $approvalWorkflow->id)
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'workflow' => 'Cannot delete workflow that is currently in use.',
            ]);
        }

        $approvalWorkflow->delete();

        return response()->json(['message' => 'Workflow deleted successfully']);
    }

    public function storeStep(Request $request, ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        $this->authorize('update', $approvalWorkflow);

        $validated = $request->validate([
            'step_order' => ['required', 'integer', 'min:1'],
            'approver_type' => ['required', 'string', Rule::in(['ROLE', 'USER', 'DEPARTMENT_HEAD', 'DYNAMIC'])],
            'approver_role' => ['nullable', 'string'], // For frontend compatibility
            'approver_user_id' => ['nullable', 'integer'], // For frontend compatibility
            'condition_field' => ['nullable', 'string', 'max:100'],
            'condition_operator' => ['nullable', 'string', Rule::in(['>', '<', '>=', '<=', '=', '==', '!=', 'IN', 'NOT_IN'])],
            'condition_value' => ['nullable', 'string'],
            'is_final_step' => ['nullable', 'boolean'], // Ignored, calculated automatically
            'meta' => ['nullable', 'array'],
        ]);

        // Auto-generate step_code if not provided
        $stepCode = 'STEP_' . $validated['step_order'];
        $stepName = ucfirst(strtolower($validated['approver_type'])) . ' Approval';

        // Convert approver_role or approver_user_id to approver_value
        $approverValue = null;
        if ($validated['approver_type'] === 'ROLE' && !empty($validated['approver_role'])) {
            $approverValue = $validated['approver_role'];
        } elseif ($validated['approver_type'] === 'USER' && !empty($validated['approver_user_id'])) {
            $approverValue = (string) $validated['approver_user_id'];
        }

        $data = [
            'step_order' => $validated['step_order'],
            'step_code' => $stepCode,
            'step_name' => $stepName,
            'step_description' => null,
            'approver_type' => $validated['approver_type'],
            'approver_value' => $approverValue,
            'condition_field' => $validated['condition_field'] ?? null,
            'condition_operator' => $validated['condition_operator'] ?? null,
            'condition_value' => $validated['condition_value'] ?? null,
            'is_required' => true,
            'allow_skip' => false,
            'allow_parallel' => false,
            'meta' => $validated['meta'] ?? null,
        ];

        // Check for duplicate step_order
        $exists = $approvalWorkflow->steps()
            ->where('step_order', $validated['step_order'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'step_order' => 'Step order already exists for this workflow.',
            ]);
        }

        $step = $approvalWorkflow->steps()->create($data);

        return response()->json(['data' => $step->load('workflow')], 201);
    }

    public function updateStep(Request $request, ApprovalWorkflow $approvalWorkflow, ApprovalWorkflowStep $step): JsonResponse
    {
        $this->authorize('update', $approvalWorkflow);

        if ($step->approval_workflow_id !== $approvalWorkflow->id) {
            abort(404);
        }

        $validated = $request->validate([
            'step_order' => ['required', 'integer', 'min:1'],
            'approver_type' => ['required', 'string', Rule::in(['ROLE', 'USER', 'DEPARTMENT_HEAD', 'DYNAMIC'])],
            'approver_role' => ['nullable', 'string'], // For frontend compatibility
            'approver_user_id' => ['nullable', 'integer'], // For frontend compatibility
            'condition_field' => ['nullable', 'string', 'max:100'],
            'condition_operator' => ['nullable', 'string', Rule::in(['>', '<', '>=', '<=', '=', '==', '!=', 'IN', 'NOT_IN'])],
            'condition_value' => ['nullable', 'string'],
            'is_final_step' => ['nullable', 'boolean'], // Ignored
            'meta' => ['nullable', 'array'],
        ]);

        // Convert approver_role or approver_user_id to approver_value
        $approverValue = null;
        if ($validated['approver_type'] === 'ROLE' && !empty($validated['approver_role'])) {
            $approverValue = $validated['approver_role'];
        } elseif ($validated['approver_type'] === 'USER' && !empty($validated['approver_user_id'])) {
            $approverValue = (string) $validated['approver_user_id'];
        }

        $data = [
            'step_order' => $validated['step_order'],
            'approver_type' => $validated['approver_type'],
            'approver_value' => $approverValue,
            'condition_field' => $validated['condition_field'] ?? null,
            'condition_operator' => $validated['condition_operator'] ?? null,
            'condition_value' => $validated['condition_value'] ?? null,
            'meta' => $validated['meta'] ?? null,
        ];

        // Check for duplicate step_order (excluding current step)
        $exists = $approvalWorkflow->steps()
            ->where('step_order', $validated['step_order'])
            ->where('id', '!=', $step->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'step_order' => 'Step order already exists for this workflow.',
            ]);
        }

        $step->update($data);

        return response()->json(['data' => $step->fresh()->load('workflow')]);
    }

    public function destroyStep(ApprovalWorkflow $approvalWorkflow, ApprovalWorkflowStep $step): JsonResponse
    {
        $this->authorize('update', $approvalWorkflow);

        if ($step->approval_workflow_id !== $approvalWorkflow->id) {
            abort(404);
        }

        $step->delete();

        return response()->json(['message' => 'Step deleted successfully']);
    }

    public function reorderSteps(Request $request, ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        $this->authorize('update', $approvalWorkflow);

        $validated = $request->validate([
            'steps' => ['required', 'array'],
            'steps.*.id' => ['required', 'integer', 'exists:approval_workflow_steps,id'],
            'steps.*.step_order' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($validated, $approvalWorkflow) {
            foreach ($validated['steps'] as $stepData) {
                ApprovalWorkflowStep::where('id', $stepData['id'])
                    ->where('approval_workflow_id', $approvalWorkflow->id)
                    ->update(['step_order' => $stepData['step_order']]);
            }
        });

        return response()->json([
            'data' => $approvalWorkflow->fresh()->load('orderedSteps'),
        ]);
    }
}
