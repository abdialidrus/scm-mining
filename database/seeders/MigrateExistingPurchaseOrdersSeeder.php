<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\ApprovalWorkflow;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatusHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * OPTIONAL SEEDER: Migrate existing POs to new approval workflow system.
 *
 * This seeder will:
 * 1. Find all POs in SUBMITTED or IN_APPROVAL status
 * 2. Create approval workflow instances for them
 * 3. Mark already completed steps as APPROVED based on status_histories
 *
 * Run this ONLY if you have existing POs that need to use the new workflow.
 */
class MigrateExistingPurchaseOrdersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!$this->confirm('This will migrate existing POs to the new approval workflow system. Continue?')) {
            $this->command->info('Migration cancelled.');
            return;
        }

        $workflow = ApprovalWorkflow::where('code', 'PO_STANDARD')->first();

        if (!$workflow) {
            $this->command->error('PO_STANDARD workflow not found. Please run ApprovalWorkflowSeeder first.');
            return;
        }

        $pos = PurchaseOrder::whereIn('status', [
            PurchaseOrder::STATUS_SUBMITTED,
            PurchaseOrder::STATUS_IN_APPROVAL,
        ])->get();

        if ($pos->isEmpty()) {
            $this->command->info('No existing POs found in SUBMITTED or IN_APPROVAL status.');
            return;
        }

        $this->command->info("Found {$pos->count()} POs to migrate.");

        $migrated = 0;

        foreach ($pos as $po) {
            DB::transaction(function () use ($po, $workflow, &$migrated) {
                // Check if already has approvals
                $existingApprovals = Approval::where('approvable_type', get_class($po))
                    ->where('approvable_id', $po->id)
                    ->exists();

                if ($existingApprovals) {
                    $this->command->warn("PO #{$po->id} already has approvals, skipping.");
                    return;
                }

                // Get applicable steps based on amount
                $steps = $workflow->orderedSteps()
                    ->get()
                    ->filter(function ($step) use ($po) {
                        return $this->evaluateStepCondition($step, $po);
                    });

                // Get already approved steps from status_histories
                $approvedSteps = PurchaseOrderStatusHistory::where('purchase_order_id', $po->id)
                    ->where('action', 'approve')
                    ->get()
                    ->pluck('meta.step')
                    ->filter()
                    ->map(fn($step) => strtoupper((string) $step))
                    ->unique()
                    ->values()
                    ->toArray();

                $this->command->info("Migrating PO #{$po->id} ({$po->po_number}) - Amount: Rp " . number_format($po->total_amount, 0, ',', '.'));
                $this->command->info("  Already approved steps: " . implode(', ', $approvedSteps));

                foreach ($steps as $step) {
                    $isAlreadyApproved = in_array($step->step_code, $approvedSteps, true);

                    // Resolve approver
                    $assignedToUserId = null;
                    $assignedToRole = null;

                    if ($step->approver_type === 'ROLE') {
                        $assignedToRole = $step->approver_value;
                    }

                    // Create approval instance
                    Approval::create([
                        'approval_workflow_id' => $workflow->id,
                        'approval_workflow_step_id' => $step->id,
                        'approvable_type' => get_class($po),
                        'approvable_id' => $po->id,
                        'status' => $isAlreadyApproved ? Approval::STATUS_APPROVED : Approval::STATUS_PENDING,
                        'assigned_to_user_id' => $assignedToUserId,
                        'assigned_to_role' => $assignedToRole,
                        'approved_at' => $isAlreadyApproved ? now() : null,
                        'approved_by_user_id' => null, // Can't determine from old system
                        'meta' => [
                            'migrated_from_old_system' => true,
                            'migration_date' => now()->toISOString(),
                        ],
                    ]);

                    $status = $isAlreadyApproved ? '✅ APPROVED' : '⏳ PENDING';
                    $this->command->info("    - {$step->step_name}: {$status}");
                }

                $migrated++;
            });
        }

        $this->command->info("✅ Successfully migrated {$migrated} POs to new approval workflow system.");
    }

    /**
     * Evaluate if a step's condition is met.
     */
    private function evaluateStepCondition($step, $po): bool
    {
        if (!$step->condition_field) {
            return true;
        }

        $fieldValue = data_get($po, $step->condition_field);
        $conditionValue = $step->condition_value;

        switch ($step->condition_operator) {
            case '>':
                return $fieldValue > $conditionValue;
            case '>=':
                return $fieldValue >= $conditionValue;
            case '<':
                return $fieldValue < $conditionValue;
            case '<=':
                return $fieldValue <= $conditionValue;
            case '=':
            case '==':
                return $fieldValue == $conditionValue;
            default:
                return true;
        }
    }

    /**
     * Helper to ask for confirmation.
     */
    private function confirm(string $message): bool
    {
        if (!$this->command) {
            return true;
        }

        return $this->command->confirm($message);
    }
}
