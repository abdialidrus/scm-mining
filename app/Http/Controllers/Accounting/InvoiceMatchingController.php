<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ApproveInvoiceRequest;
use App\Http\Requests\Accounting\RejectInvoiceRequest;
use App\Models\Accounting\SupplierInvoice;
use App\Services\Accounting\InvoiceApprovalService;
use App\Services\Accounting\InvoiceMatchingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceMatchingController extends Controller
{
    public function __construct(
        protected InvoiceMatchingService $matchingService,
        protected InvoiceApprovalService $approvalService
    ) {}

    /**
     * Run 3-way matching for an invoice
     */
    public function match(Request $request, SupplierInvoice $supplierInvoice)
    {
        $this->authorize('match', $supplierInvoice);

        try {
            $result = $this->matchingService->performThreeWayMatch($supplierInvoice, $request->user());

            return redirect()
                ->route('accounting.invoices.matching.show', $supplierInvoice)
                ->with('success', 'Matching berhasil dijalankan');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menjalankan matching: ' . $e->getMessage());
        }
    }

    /**
     * Show matching details
     */
    public function show(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('view', $supplierInvoice);

        if (!$supplierInvoice->matchingResult) {
            return back()->with('error', 'Invoice belum di-match');
        }

        $supplierInvoice->load([
            'supplier',
            'purchaseOrder.lines.item',
            'lines.item',
            'lines.uom',
            'lines.purchaseOrderLine.item',
            'lines.goodsReceiptLine.goodsReceipt',
            'matchingResult.config',
            'matchingResult.matchedBy',
        ]);

        return Inertia::render('Accounting/Invoices/Matching', [
            'invoice' => $supplierInvoice,
            'matchingResult' => $supplierInvoice->matchingResult,
            'matchingDetails' => $supplierInvoice->matchingResult->matching_details,
        ]);
    }

    /**
     * Approve invoice with variance
     */
    public function approve(ApproveInvoiceRequest $request, SupplierInvoice $supplierInvoice)
    {
        try {
            $this->approvalService->approve(
                $supplierInvoice,
                $request->user(),
                $request->notes
            );

            return back()->with('success', 'Invoice berhasil diapprove');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal approve invoice: ' . $e->getMessage());
        }
    }

    /**
     * Reject invoice
     */
    public function reject(RejectInvoiceRequest $request, SupplierInvoice $supplierInvoice)
    {
        try {
            $this->approvalService->reject(
                $supplierInvoice,
                $request->user(),
                $request->rejection_reason
            );

            return back()->with('success', 'Invoice berhasil direject');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal reject invoice: ' . $e->getMessage());
        }
    }

    /**
     * Get tolerance configuration
     */
    public function getToleranceConfig(Request $request)
    {
        $this->authorize('configureTolerance', SupplierInvoice::class);

        $config = \App\Models\Accounting\InvoiceMatchingConfig::query();

        if ($request->filled('supplier_id')) {
            $config->forSupplier($request->supplier_id);
        } else {
            $config->global();
        }

        return response()->json([
            'config' => $config->active()->first(),
        ]);
    }

    /**
     * Update tolerance configuration
     */
    public function updateToleranceConfig(Request $request)
    {
        $this->authorize('configureTolerance', SupplierInvoice::class);

        $validated = $request->validate([
            'config_type' => 'required|in:global,supplier_specific',
            'supplier_id' => 'required_if:config_type,supplier_specific|nullable|exists:suppliers,id',
            'quantity_tolerance_percent' => 'required|numeric|min:0|max:100',
            'price_tolerance_percent' => 'required|numeric|min:0|max:100',
            'amount_tolerance_percent' => 'required|numeric|min:0|max:100',
            'allow_under_invoicing' => 'boolean',
            'allow_over_invoicing' => 'boolean',
            'auto_approve_if_within_tolerance' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['updated_by'] = $request->user()->id;

        // If supplier-specific, find existing config for that supplier
        if ($validated['config_type'] === 'supplier_specific') {
            $config = \App\Models\Accounting\InvoiceMatchingConfig::updateOrCreate(
                [
                    'config_type' => 'supplier_specific',
                    'supplier_id' => $validated['supplier_id'],
                ],
                $validated
            );
        } else {
            // Update global config
            $config = \App\Models\Accounting\InvoiceMatchingConfig::updateOrCreate(
                ['config_type' => 'global'],
                $validated
            );
        }

        return response()->json([
            'message' => 'Konfigurasi toleransi berhasil diupdate',
            'config' => $config,
        ]);
    }
}
