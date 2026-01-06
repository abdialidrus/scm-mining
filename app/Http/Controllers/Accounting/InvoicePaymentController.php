<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\RecordPaymentRequest;
use App\Models\Accounting\SupplierInvoice;
use App\Services\Accounting\InvoicePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class InvoicePaymentController extends Controller
{
    public function __construct(
        protected InvoicePaymentService $paymentService
    ) {}

    /**
     * Show payment history for an invoice
     */
    public function index(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('view', $supplierInvoice);

        $payments = $this->paymentService->getPaymentHistory($supplierInvoice);

        return Inertia::render('Accounting/Invoices/Payments', [
            'invoice' => $supplierInvoice->load('supplier', 'purchaseOrder'),
            'payments' => $payments['payments'],
            'summary' => [
                'total_amount' => $supplierInvoice->total_amount,
                'paid_amount' => $payments['total_paid'],
                'remaining_amount' => $payments['remaining'],
            ],
        ]);
    }

    /**
     * Record a new payment
     */
    public function store(RecordPaymentRequest $request, SupplierInvoice $supplierInvoice)
    {
        try {
            $paymentData = $request->validated();

            // Handle payment proof upload
            $paymentProofFile = $request->file('payment_proof');

            $payment = $this->paymentService->recordPayment(
                $supplierInvoice,
                $paymentData,
                $request->user(),
                $paymentProofFile
            );

            return back()->with(
                'success',
                'Pembayaran berhasil dicatat dengan nomor ' . $payment->payment_number .
                    '. Sisa tagihan: Rp ' . number_format($supplierInvoice->fresh()->remaining_amount, 0, ',', '.')
            );
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Gagal mencatat pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * Download payment proof
     */
    public function downloadProof(SupplierInvoice $supplierInvoice, $paymentId)
    {
        $this->authorize('view', $supplierInvoice);

        $payment = $supplierInvoice->payments()->findOrFail($paymentId);

        if (!$payment->payment_proof_path) {
            abort(404, 'Bukti pembayaran tidak ditemukan');
        }

        $filePath = Storage::disk('public')->path($payment->payment_proof_path);
        $fileName = 'Payment-Proof-' . $payment->payment_number . '.' . pathinfo($payment->payment_proof_path, PATHINFO_EXTENSION);

        return response()->download($filePath, $fileName);
    }

    /**
     * Get payment summary statistics
     */
    public function getSummary(Request $request)
    {
        $this->authorize('viewAny', SupplierInvoice::class);

        $query = SupplierInvoice::query();

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('invoice_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('invoice_date', '<=', $request->date_to);
        }

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $summary = [
            'total_invoices' => $query->count(),
            'total_amount' => $query->sum('total_amount'),
            'total_paid' => $query->sum('paid_amount'),
            'total_remaining' => $query->sum('remaining_amount'),
            'unpaid_count' => (clone $query)->unpaid()->count(),
            'overdue_count' => (clone $query)->overdue()->count(),
        ];

        return response()->json($summary);
    }

    /**
     * Export payment report
     */
    public function exportReport(Request $request)
    {
        $this->authorize('export', SupplierInvoice::class);

        // TODO: Implement export to Excel/PDF
        // For now, return JSON
        $query = SupplierInvoice::with(['supplier', 'payments']);

        if ($request->filled('date_from')) {
            $query->whereDate('invoice_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('invoice_date', '<=', $request->date_to);
        }

        $invoices = $query->get();

        return response()->json([
            'invoices' => $invoices,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }
}
