<?php

namespace App\Services\Accounting;

use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Accounting\PaymentStatus;
use App\Models\Accounting\InvoicePayment;
use App\Models\Accounting\SupplierInvoice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoicePaymentService
{
    /**
     * Record payment for invoice
     */
    public function recordPayment(
        SupplierInvoice $invoice,
        array $paymentData,
        User $user,
        ?UploadedFile $proofFile = null
    ): InvoicePayment {
        // Validate: invoice harus sudah approved
        if ($invoice->status !== InvoiceStatus::APPROVED && $invoice->status !== InvoiceStatus::PAID) {
            throw new \Exception("Cannot record payment for invoice with status: {$invoice->status->value}. Invoice must be APPROVED first.");
        }

        // Validate: payment amount tidak boleh > remaining amount
        $remainingAmount = (float) $invoice->remaining_amount;
        $paymentAmount = (float) $paymentData['payment_amount'];

        if ($paymentAmount > $remainingAmount) {
            throw new \Exception("Payment amount (Rp " . number_format($paymentAmount, 2) . ") exceeds remaining amount (Rp " . number_format($remainingAmount, 2) . ")");
        }

        if ($paymentAmount <= 0) {
            throw new \Exception("Payment amount must be greater than zero.");
        }

        DB::beginTransaction();

        try {
            // Handle file upload if provided
            $proofPath = null;
            if ($proofFile) {
                $proofPath = $this->uploadPaymentProof($proofFile, $invoice);
            }

            // Create payment record
            $payment = InvoicePayment::create([
                'supplier_invoice_id' => $invoice->id,
                'payment_date' => $paymentData['payment_date'],
                'payment_method' => $paymentData['payment_method'] ?? 'BANK_TRANSFER',
                'payment_amount' => $paymentAmount,
                'bank_name' => $paymentData['bank_name'] ?? null,
                'bank_account_number' => $paymentData['bank_account_number'] ?? null,
                'bank_account_name' => $paymentData['bank_account_name'] ?? null,
                'transaction_reference' => $paymentData['transaction_reference'] ?? null,
                'payment_proof_path' => $proofPath,
                'notes' => $paymentData['notes'] ?? null,
                'remarks' => $paymentData['remarks'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            // Update invoice payment status
            $this->updateInvoicePaymentStatus($invoice, $paymentAmount);

            DB::commit();

            return $payment;
        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded file if transaction failed
            if (isset($proofPath) && $proofPath) {
                Storage::disk(config('accounting.uploads.payment_proof.disk'))->delete($proofPath);
            }

            throw $e;
        }
    }

    /**
     * Upload payment proof file
     */
    private function uploadPaymentProof(UploadedFile $file, SupplierInvoice $invoice): string
    {
        $config = config('accounting.uploads.payment_proof');
        $disk = $config['disk'];
        $basePath = $config['path'];

        // Validate file
        $maxSize = $config['max_size'] * 1024; // Convert KB to bytes
        if ($file->getSize() > $maxSize) {
            throw new \Exception("File size exceeds maximum allowed size of " . $config['max_size'] . "KB");
        }

        $allowedTypes = $config['allowed_types'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedTypes)) {
            throw new \Exception("File type not allowed. Allowed types: " . implode(', ', $allowedTypes));
        }

        // Generate unique filename
        $date = now()->format('Y/m');
        $filename = 'payment-' . $invoice->internal_number . '-' . time() . '.' . $extension;
        $path = "{$basePath}/{$date}/{$filename}";

        // Store file
        $file->storeAs(dirname($path), basename($path), $disk);

        return $path;
    }

    /**
     * Update invoice payment status after payment recorded
     */
    private function updateInvoicePaymentStatus(SupplierInvoice $invoice, float $paymentAmount): void
    {
        $currentPaidAmount = (float) $invoice->paid_amount;
        $totalAmount = (float) $invoice->total_amount;

        $newPaidAmount = $currentPaidAmount + $paymentAmount;
        $newRemainingAmount = $totalAmount - $newPaidAmount;

        // Determine payment status
        $paymentStatus = PaymentStatus::UNPAID;
        $invoiceStatus = $invoice->status;

        if ($newRemainingAmount <= 0.01) { // Fully paid (with small rounding tolerance)
            $paymentStatus = PaymentStatus::PAID;
            $invoiceStatus = InvoiceStatus::PAID;
            $newRemainingAmount = 0;
            $newPaidAmount = $totalAmount; // Ensure exact match
        } elseif ($newPaidAmount > 0) {
            $paymentStatus = PaymentStatus::PARTIAL_PAID;
        }

        // Update invoice
        $invoice->update([
            'paid_amount' => $newPaidAmount,
            'remaining_amount' => $newRemainingAmount,
            'payment_status' => $paymentStatus->value,
            'status' => $invoiceStatus->value,
        ]);
    }

    /**
     * Get payment history for invoice
     */
    public function getPaymentHistory(SupplierInvoice $invoice): array
    {
        $payments = $invoice->payments()
            ->with('createdBy')
            ->orderBy('payment_date', 'desc')
            ->get();

        return [
            'total_amount' => (float) $invoice->total_amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'remaining_amount' => (float) $invoice->remaining_amount,
            'payment_status' => $invoice->payment_status->value,
            'payments' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'payment_method' => $payment->payment_method,
                    'payment_amount' => (float) $payment->payment_amount,
                    'bank_name' => $payment->bank_name,
                    'transaction_reference' => $payment->transaction_reference,
                    'has_proof' => !empty($payment->payment_proof_path),
                    'created_by' => $payment->createdBy->name,
                    'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ];
    }
}
