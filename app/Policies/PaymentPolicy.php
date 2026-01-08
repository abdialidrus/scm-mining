<?php

namespace App\Policies;

use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['finance', 'super_admin', 'gm', 'director']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->hasAnyRole(['finance', 'super_admin', 'gm', 'director']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['finance', 'super_admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SupplierPayment $supplierPayment): bool
    {
        // Only draft payments can be updated
        if (!$supplierPayment->isDraft()) {
            return false;
        }

        // Finance and super_admin can update
        if ($user->hasAnyRole(['super_admin'])) {
            return true;
        }

        // Creator can update their own draft payment
        return $user->hasRole('finance') && $supplierPayment->created_by_user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SupplierPayment $supplierPayment): bool
    {
        // Only draft payments can be deleted
        if (!$supplierPayment->isDraft()) {
            return false;
        }

        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SupplierPayment $supplierPayment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SupplierPayment $supplierPayment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can confirm the payment.
     */
    public function confirm(User $user, SupplierPayment $supplierPayment): bool
    {
        // Only draft payments can be confirmed
        if (!$supplierPayment->isDraft()) {
            return false;
        }

        return $user->hasAnyRole(['finance', 'super_admin']);
    }

    /**
     * Determine whether the user can cancel the payment.
     */
    public function cancel(User $user, SupplierPayment $supplierPayment): bool
    {
        // Can't cancel already cancelled payments
        if ($supplierPayment->isCancelled()) {
            return false;
        }

        return $user->hasAnyRole(['finance', 'super_admin']);
    }
}
