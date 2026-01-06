<?php

namespace App\Services\Accounting;

use App\Enums\Accounting\InvoiceStatus;
use App\Models\Accounting\SupplierInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceApprovalService
{
    /**
     * Approve invoice with variance
     */
    public function approve(SupplierInvoice $invoice, User $user, ?string $notes = null): SupplierInvoice
    {
        // Validate: hanya invoice dengan status VARIANCE yang bisa di-approve
        if ($invoice->status !== InvoiceStatus::VARIANCE) {
            throw new \Exception("Only invoices with VARIANCE status can be approved. Current status: {$invoice->status->value}");
        }

        // Validate: user harus punya permission approve dan role yang sesuai
        if (!$user->hasPermissionTo('invoices.approve')) {
            throw new \Exception("You don't have permission to approve invoices.");
        }

        // Validate: user harus punya role 'finance' DAN 'dept_head'
        $hasFinanceRole = $user->hasRole('finance');
        $hasDeptHeadRole = $user->hasRole('dept_head');

        if (!($hasFinanceRole && $hasDeptHeadRole)) {
            throw new \Exception("Invoice approval requires both 'finance' and 'dept_head' roles.");
        }

        DB::beginTransaction();

        try {
            // Update invoice status
            $invoice->update([
                'status' => InvoiceStatus::APPROVED->value,
                'approval_status' => 'APPROVED',
                'approved_by_user_id' => $user->id,
                'approved_at' => now(),
                'approval_notes' => $notes,
            ]);

            DB::commit();

            return $invoice->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject invoice with variance
     */
    public function reject(SupplierInvoice $invoice, User $user, string $reason): SupplierInvoice
    {
        // Validate: hanya invoice dengan status VARIANCE yang bisa di-reject
        if ($invoice->status !== InvoiceStatus::VARIANCE) {
            throw new \Exception("Only invoices with VARIANCE status can be rejected. Current status: {$invoice->status->value}");
        }

        // Validate: user harus punya permission approve
        if (!$user->hasPermissionTo('invoices.approve')) {
            throw new \Exception("You don't have permission to reject invoices.");
        }

        // Validate: user harus punya role 'finance' DAN 'dept_head'
        $hasFinanceRole = $user->hasRole('finance');
        $hasDeptHeadRole = $user->hasRole('dept_head');

        if (!($hasFinanceRole && $hasDeptHeadRole)) {
            throw new \Exception("Invoice rejection requires both 'finance' and 'dept_head' roles.");
        }

        if (empty($reason)) {
            throw new \Exception("Rejection reason is required.");
        }

        DB::beginTransaction();

        try {
            // Update invoice status
            $invoice->update([
                'status' => InvoiceStatus::REJECTED->value,
                'approval_status' => 'REJECTED',
                'approved_by_user_id' => $user->id,
                'approved_at' => now(),
                'approval_notes' => $reason,
            ]);

            DB::commit();

            return $invoice->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check if user can approve invoices
     */
    public function canApprove(User $user): bool
    {

        // Must have permission
        if (!$user->hasPermissionTo('invoices.approve')) {
            return false;
        }

        // Must have BOTH finance AND dept_head roles
        return $user->hasRole('finance') && $user->hasRole('dept_head');
    }
}
