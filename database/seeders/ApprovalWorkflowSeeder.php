<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use Illuminate\Database\Seeder;

class ApprovalWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedPurchaseOrderWorkflow();
        $this->seedPurchaseRequestWorkflow();
    }

    /**
     * Seed Purchase Order approval workflow.
     */
    private function seedPurchaseOrderWorkflow(): void
    {
        $workflow = ApprovalWorkflow::query()->updateOrCreate(
            ['code' => 'PO_STANDARD'],
            [
                'name' => 'Purchase Order Standard Approval',
                'description' => 'Multi-tier approval workflow for Purchase Orders based on total amount',
                'model_type' => 'App\\Models\\PurchaseOrder',
                'is_active' => true,
            ]
        );

        // Delete existing steps to prevent duplicates
        $workflow->steps()->delete();

        // Step 1: Finance (always required)
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'step_code' => 'FINANCE',
            'step_name' => 'Finance Review',
            'step_description' => 'Financial review and validation of Purchase Order',
            'approver_type' => ApprovalWorkflowStep::APPROVER_TYPE_ROLE,
            'approver_value' => 'finance',
            'is_required' => true,
            'allow_skip' => false,
            'allow_parallel' => false,
        ]);

        // Step 2: GM (only if amount >= 50,000,000)
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 2,
            'step_code' => 'GM',
            'step_name' => 'General Manager Approval',
            'step_description' => 'GM approval required for Purchase Orders >= 50 million IDR',
            'approver_type' => ApprovalWorkflowStep::APPROVER_TYPE_ROLE,
            'approver_value' => 'gm',
            'condition_field' => 'total_amount',
            'condition_operator' => '>=',
            'condition_value' => '50000000',
            'is_required' => true,
            'allow_skip' => false,
            'allow_parallel' => false,
        ]);

        // Step 3: Director (only if amount >= 100,000,000)
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 3,
            'step_code' => 'DIRECTOR',
            'step_name' => 'Director Approval',
            'step_description' => 'Director approval required for Purchase Orders >= 100 million IDR',
            'approver_type' => ApprovalWorkflowStep::APPROVER_TYPE_ROLE,
            'approver_value' => 'director',
            'condition_field' => 'total_amount',
            'condition_operator' => '>=',
            'condition_value' => '100000000',
            'is_required' => true,
            'allow_skip' => false,
            'allow_parallel' => false,
        ]);

        $this->command->info('✓ Purchase Order workflow seeded');
    }

    /**
     * Seed Purchase Request approval workflow.
     */
    private function seedPurchaseRequestWorkflow(): void
    {
        $workflow = ApprovalWorkflow::query()->updateOrCreate(
            ['code' => 'PR_STANDARD'],
            [
                'name' => 'Purchase Request Standard Approval',
                'description' => 'Standard approval workflow for Purchase Requests',
                'model_type' => 'App\\Models\\PurchaseRequest',
                'is_active' => true,
            ]
        );

        // Delete existing steps to prevent duplicates
        $workflow->steps()->delete();

        // Step 1: Department Head (always required)
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'step_code' => 'DEPT_HEAD',
            'step_name' => 'Department Head Approval',
            'step_description' => 'Department head must approve all Purchase Requests',
            'approver_type' => ApprovalWorkflowStep::APPROVER_TYPE_DEPARTMENT_HEAD,
            'approver_value' => null, // Will be resolved dynamically
            'is_required' => true,
            'allow_skip' => false,
            'allow_parallel' => false,
        ]);

        $this->command->info('✓ Purchase Request workflow seeded');
    }
}
