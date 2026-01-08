<?php

namespace App\Services;

use App\Models\SupplierPayment;
use App\Models\PaymentStatusHistory;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentService
{
    /**
     * Record a new payment
     */
    public function recordPayment(array $data): SupplierPayment
    {
        DB::beginTransaction();
        try {
            // Handle file uploads
            if (isset($data['supplier_invoice_file'])) {
                $data['supplier_invoice_file_path'] = $data['supplier_invoice_file']->store('invoices', 'public');
                unset($data['supplier_invoice_file']);
            }

            if (isset($data['payment_proof_file'])) {
                $data['payment_proof_file_path'] = $data['payment_proof_file']->store('payment-proofs', 'public');
                unset($data['payment_proof_file']);
            }

            // Create payment
            $payment = SupplierPayment::create([
                'payment_number' => SupplierPayment::generatePaymentNumber(),
                ...$data,
                'created_by_user_id' => auth()->id(),
            ]);

            // Update PO payment status
            $payment->purchaseOrder->updatePaymentStatus();

            DB::commit();
            return $payment->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded files on error
            if (isset($data['supplier_invoice_file_path'])) {
                Storage::disk('public')->delete($data['supplier_invoice_file_path']);
            }
            if (isset($data['payment_proof_file_path'])) {
                Storage::disk('public')->delete($data['payment_proof_file_path']);
            }

            throw $e;
        }
    }

    /**
     * Update an existing payment
     */
    public function updatePayment(SupplierPayment $payment, array $data): SupplierPayment
    {
        DB::beginTransaction();
        try {
            // Handle file uploads
            if (isset($data['supplier_invoice_file'])) {
                // Delete old file
                if ($payment->supplier_invoice_file_path) {
                    Storage::disk('public')->delete($payment->supplier_invoice_file_path);
                }
                $data['supplier_invoice_file_path'] = $data['supplier_invoice_file']->store('invoices', 'public');
                unset($data['supplier_invoice_file']);
            }

            if (isset($data['payment_proof_file'])) {
                // Delete old file
                if ($payment->payment_proof_file_path) {
                    Storage::disk('public')->delete($payment->payment_proof_file_path);
                }
                $data['payment_proof_file_path'] = $data['payment_proof_file']->store('payment-proofs', 'public');
                unset($data['payment_proof_file']);
            }

            $payment->update($data);

            // Update PO payment status
            $payment->purchaseOrder->updatePaymentStatus();

            DB::commit();
            return $payment->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Confirm a payment
     */
    public function confirmPayment(SupplierPayment $payment): bool
    {
        DB::beginTransaction();
        try {
            $payment->update([
                'status' => 'CONFIRMED',
                'approved_by_user_id' => auth()->id(),
                'approved_at' => now(),
            ]);

            $payment->purchaseOrder->updatePaymentStatus();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancel a payment
     */
    public function cancelPayment(SupplierPayment $payment, ?string $reason = null): bool
    {
        DB::beginTransaction();
        try {
            $payment->update([
                'status' => 'CANCELLED',
                'notes' => $payment->notes . ($reason ? "\nCancelled: {$reason}" : ''),
            ]);

            $payment->purchaseOrder->updatePaymentStatus();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get outstanding purchase orders
     */
    public function getOutstandingPOs(array $filters = [])
    {
        return PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_SENT,
                PurchaseOrder::STATUS_CLOSED
            ])
            ->whereIn('payment_status', ['UNPAID', 'PARTIAL', 'OVERDUE', 'PAID'])
            ->with(['supplier', 'goodsReceipts', 'payments' => fn($q) => $q->confirmed()])
            ->when($filters['supplier_id'] ?? null, fn($q, $v) => $q->where('supplier_id', $v))
            ->when($filters['payment_status'] ?? null, fn($q, $v) => $q->where('payment_status', $v))
            ->when($filters['overdue_only'] ?? false, fn($q) => $q->where('payment_due_date', '<', now()))
            ->when(
                $filters['search'] ?? null,
                fn($q, $v) =>
                $q->where('po_number', 'LIKE', "%{$v}%")
                    ->orWhereHas(
                        'supplier',
                        fn($sq) =>
                        $sq->where('name', 'LIKE', "%{$v}%")
                    )
            )
            // ->orderBy('payment_due_date', 'asc')
            // ->orderBy('total_amount', 'desc')
            ->orderBy('outstanding_amount', 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStats(): array
    {
        return [
            'total_outstanding' => PurchaseOrder::whereIn('payment_status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
                ->sum('outstanding_amount'),
            'overdue_count' => PurchaseOrder::where('payment_status', 'OVERDUE')->count(),
            'overdue_amount' => PurchaseOrder::where('payment_status', 'OVERDUE')
                ->sum('outstanding_amount'),
            'this_month_paid' => SupplierPayment::confirmed()
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('payment_amount'),
            'pending_confirmation' => SupplierPayment::draft()->count(),
        ];
    }
}
