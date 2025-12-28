<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Console\Command;

class TestApprovalWorkflow extends Command
{
    protected $signature = 'test:approval-workflow {po_id?}';

    protected $description = 'Test approval workflow for a Purchase Order';

    public function __construct(
        private readonly ApprovalWorkflowService $approvalService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $poId = $this->argument('po_id');

        if (!$poId) {
            $this->error('Please provide PO ID: php artisan test:approval-workflow {po_id}');
            return 1;
        }

        $po = PurchaseOrder::find($poId);

        if (!$po) {
            $this->error("Purchase Order #{$poId} not found.");
            return 1;
        }

        $this->info("=== Purchase Order #{$po->id} ===");
        $this->info("PO Number: {$po->po_number}");
        $this->info("Status: {$po->status}");
        $this->info("Total Amount: Rp " . number_format($po->total_amount, 0, ',', '.'));
        $this->newLine();

        $approvals = $this->approvalService->getApprovals($po);

        if ($approvals->isEmpty()) {
            $this->warn('No approval workflow found for this PO.');
            $this->info('Try submitting the PO first.');
            return 0;
        }

        $this->info('=== Approval Progress ===');
        $this->table(
            ['Step', 'Approver', 'Status', 'Approved By', 'Approved At'],
            $approvals->map(function ($approval) {
                return [
                    $approval->step->step_name,
                    $approval->assigned_to_role ?? "User #{$approval->assigned_to_user_id}",
                    $approval->status,
                    $approval->approvedBy?->name ?? '-',
                    $approval->approved_at?->format('Y-m-d H:i:s') ?? '-',
                ];
            })
        );

        $nextApproval = $this->approvalService->getNextPendingApproval($po);

        if ($nextApproval) {
            $this->newLine();
            $this->info("Next Approval: {$nextApproval->step->step_name}");
            $this->info("Assigned to: {$nextApproval->assigned_to_role}");
        } else {
            $this->newLine();
            if ($this->approvalService->isWorkflowComplete($po)) {
                $this->info('✅ All approvals completed!');
            } elseif ($this->approvalService->isWorkflowRejected($po)) {
                $this->error('❌ Workflow rejected.');
            }
        }

        return 0;
    }
}
