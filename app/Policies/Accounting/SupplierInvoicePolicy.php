<?php

namespace App\Policies\Accounting;

use App\Enums\Accounting\InvoiceStatus;
use App\Models\Accounting\SupplierInvoice;
use App\Models\User;

class SupplierInvoicePolicy
{
    /**
     * Determine if the user can view any invoices
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('invoices.view');
    }

    /**
     * Determine if the user can view the invoice
     */
    public function view(User $user, SupplierInvoice $invoice): bool
    {
        return $user->hasPermissionTo('invoices.view');
    }

    /**
     * Determine if the user can create invoices
     */
    public function create(User $user): bool
    {
        // Only finance and super_admin can create
        return $user->hasPermissionTo('invoices.create');
    }

    /**
     * Determine if the user can update the invoice
     */
    public function update(User $user, SupplierInvoice $invoice): bool
    {
        // Only finance and super_admin can update
        if (!$user->hasPermissionTo('invoices.update')) {
            return false;
        }

        // Can only update if status is DRAFT or SUBMITTED
        return $invoice->isEditable();
    }

    /**
     * Determine if the user can delete the invoice
     */
    public function delete(User $user, SupplierInvoice $invoice): bool
    {
        // Only finance and super_admin can delete
        if (!$user->hasPermissionTo('invoices.delete')) {
            return false;
        }

        // Can only delete if status is DRAFT
        return $invoice->status === InvoiceStatus::DRAFT;
    }

    /**
     * Determine if the user can run matching on the invoice
     */
    public function match(User $user, SupplierInvoice $invoice): bool
    {
        // Only finance and super_admin can run matching
        if (!$user->hasPermissionTo('invoices.match')) {
            return false;
        }

        // Can only match if status is SUBMITTED and matching status is PENDING
        return $invoice->canBeMatched();
    }

    /**
     * Determine if the user can approve the invoice
     */
    public function approve(User $user, SupplierInvoice $invoice): bool
    {
        // Must have approve permission
        if (!$user->hasPermissionTo('invoices.approve')) {
            return false;
        }

        // Must have BOTH finance AND dept_head roles
        if (!($user->hasRole('finance') && $user->hasRole('dept_head'))) {
            return false;
        }

        // Can only approve if status is VARIANCE and requires approval
        return $invoice->status === InvoiceStatus::VARIANCE && $invoice->requires_approval;
    }

    /**
     * Determine if the user can record payment for the invoice
     */
    public function recordPayment(User $user, SupplierInvoice $invoice): bool
    {
        // Only finance and super_admin can record payment
        if (!$user->hasPermissionTo('invoices.payment.record')) {
            return false;
        }

        // Can only record payment if invoice is APPROVED or partially PAID
        return in_array($invoice->status, [InvoiceStatus::APPROVED, InvoiceStatus::PAID])
            && $invoice->remaining_amount > 0;
    }

    /**
     * Determine if the user can configure tolerance settings
     */
    public function configureTolerance(User $user): bool
    {
        // Only finance and super_admin can configure tolerance
        return $user->hasPermissionTo('invoices.tolerance.configure');
    }

    /**
     * Determine if the user can export invoices
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('invoices.export');
    }
}
