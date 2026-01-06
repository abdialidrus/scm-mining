<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\Accounting\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreSupplierInvoiceRequest;
use App\Http\Requests\Accounting\UpdateSupplierInvoiceRequest;
use App\Http\Resources\Accounting\SupplierInvoiceResource;
use App\Models\Accounting\SupplierInvoice;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SupplierInvoiceController extends Controller
{
    /**
     * Display a listing of supplier invoices
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', SupplierInvoice::class);

        $query = SupplierInvoice::with([
            'supplier:id,code,name',
            'purchaseOrder:id,po_number',
            'createdBy:id,name'
        ]);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by matching status
        if ($request->filled('matching_status')) {
            $query->where('matching_status', $request->matching_status);
        }

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('invoice_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('invoice_date', '<=', $request->date_to);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('internal_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Special filters
        if ($request->boolean('unpaid_only')) {
            $query->unpaid();
        }
        if ($request->boolean('pending_only')) {
            $query->pending();
        }
        if ($request->boolean('need_approval_only')) {
            $query->needApproval();
        }
        if ($request->boolean('overdue_only')) {
            $query->overdue();
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $invoices = $query->paginate($request->get('per_page', 15));

        return Inertia::render('Accounting/Invoices/Index', [
            'invoices' => SupplierInvoiceResource::collection($invoices),
            'filters' => $request->only([
                'status',
                'matching_status',
                'payment_status',
                'supplier_id',
                'date_from',
                'date_to',
                'search',
                'unpaid_only',
                'pending_only',
                'need_approval_only',
                'overdue_only',
                'sort_by',
                'sort_order'
            ]),
            'suppliers' => Supplier::select('id', 'code', 'name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new invoice
     */
    public function create()
    {
        $this->authorize('create', SupplierInvoice::class);

        return Inertia::render('Accounting/Invoices/Create', [
            'suppliers' => Supplier::select('id', 'code', 'name')->orderBy('name')->get(),
            'purchaseOrders' => PurchaseOrder::select('id', 'po_number', 'supplier_id')
                ->whereIn('status', ['approved', 'partially_received', 'fully_received'])
                ->with('supplier:id,name')
                ->get(),
        ]);
    }

    /**
     * Store a newly created invoice
     */
    public function store(StoreSupplierInvoiceRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Generate internal number
            $data['internal_number'] = SupplierInvoice::generateInternalNumber();
            $data['created_by'] = $request->user()->id;

            // Handle file uploads
            if ($request->hasFile('invoice_file')) {
                $data['invoice_file_path'] = $request->file('invoice_file')
                    ->store('invoices/invoice-files', 'public');
            }
            if ($request->hasFile('tax_invoice_file')) {
                $data['tax_invoice_file_path'] = $request->file('tax_invoice_file')
                    ->store('invoices/tax-invoice-files', 'public');
            }

            // Create invoice
            $invoice = SupplierInvoice::create($data);

            // Create lines
            foreach ($data['lines'] as $lineData) {
                $lineData['supplier_invoice_id'] = $invoice->id;
                $invoice->lines()->create($lineData);
            }

            DB::commit();

            return redirect()
                ->route('accounting.invoices.show', $invoice)
                ->with('success', 'Invoice berhasil dibuat dengan nomor ' . $invoice->internal_number);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Gagal membuat invoice: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified invoice
     */
    public function show(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('view', $supplierInvoice);

        $supplierInvoice->load([
            'supplier',
            'purchaseOrder.lines.item',
            'lines.item',
            'lines.uom',
            'lines.purchaseOrderLine',
            'lines.goodsReceiptLine.goodsReceipt',
            'matchingResult.config',
            'matchingResult.matchedBy',
            'payments.createdBy',
            'createdBy',
            'updatedBy',
            'matchedBy',
            'approvedBy',
            'rejectedBy',
        ]);

        return Inertia::render('Accounting/Invoices/Show', [
            'invoice' => new SupplierInvoiceResource($supplierInvoice),
        ]);
    }

    /**
     * Show the form for editing the specified invoice
     */
    public function edit(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('update', $supplierInvoice);

        $supplierInvoice->load([
            'supplier',
            'purchaseOrder',
            'lines.item',
            'lines.uom',
            'lines.purchaseOrderLine',
            'lines.goodsReceiptLine',
        ]);

        return Inertia::render('Accounting/Invoices/Edit', [
            'invoice' => new SupplierInvoiceResource($supplierInvoice),
            'suppliers' => Supplier::select('id', 'code', 'name')->orderBy('name')->get(),
            'purchaseOrders' => PurchaseOrder::select('id', 'po_number', 'supplier_id')
                ->whereIn('status', ['approved', 'partially_received', 'fully_received'])
                ->with('supplier:id,name')
                ->get(),
        ]);
    }

    /**
     * Update the specified invoice
     */
    public function update(UpdateSupplierInvoiceRequest $request, SupplierInvoice $supplierInvoice)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $data['updated_by'] = $request->user()->id;

            // Handle file uploads
            if ($request->hasFile('invoice_file')) {
                // Delete old file
                if ($supplierInvoice->invoice_file_path) {
                    Storage::disk('public')->delete($supplierInvoice->invoice_file_path);
                }
                $data['invoice_file_path'] = $request->file('invoice_file')
                    ->store('invoices/invoice-files', 'public');
            }
            if ($request->hasFile('tax_invoice_file')) {
                // Delete old file
                if ($supplierInvoice->tax_invoice_file_path) {
                    Storage::disk('public')->delete($supplierInvoice->tax_invoice_file_path);
                }
                $data['tax_invoice_file_path'] = $request->file('tax_invoice_file')
                    ->store('invoices/tax-invoice-files', 'public');
            }

            // Update invoice
            $supplierInvoice->update($data);

            // Update lines if provided
            if (isset($data['lines'])) {
                // Delete lines that are not in the request
                $lineIds = collect($data['lines'])
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                $supplierInvoice->lines()
                    ->whereNotIn('id', $lineIds)
                    ->delete();

                // Update or create lines
                foreach ($data['lines'] as $lineData) {
                    if (isset($lineData['id'])) {
                        $supplierInvoice->lines()
                            ->where('id', $lineData['id'])
                            ->update($lineData);
                    } else {
                        $lineData['supplier_invoice_id'] = $supplierInvoice->id;
                        $supplierInvoice->lines()->create($lineData);
                    }
                }
            }

            DB::commit();

            return redirect()
                ->route('accounting.invoices.show', $supplierInvoice)
                ->with('success', 'Invoice berhasil diupdate');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Gagal mengupdate invoice: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified invoice
     */
    public function destroy(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('delete', $supplierInvoice);

        try {
            DB::beginTransaction();

            // Delete files
            if ($supplierInvoice->invoice_file_path) {
                Storage::disk('public')->delete($supplierInvoice->invoice_file_path);
            }
            if ($supplierInvoice->tax_invoice_file_path) {
                Storage::disk('public')->delete($supplierInvoice->tax_invoice_file_path);
            }

            $invoiceNumber = $supplierInvoice->internal_number;
            $supplierInvoice->delete();

            DB::commit();

            return redirect()
                ->route('accounting.invoices.index')
                ->with('success', 'Invoice ' . $invoiceNumber . ' berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->with('error', 'Gagal menghapus invoice: ' . $e->getMessage());
        }
    }

    /**
     * Submit invoice for matching
     */
    public function submit(Request $request, SupplierInvoice $supplierInvoice)
    {
        $this->authorize('update', $supplierInvoice);

        if ($supplierInvoice->status !== InvoiceStatus::DRAFT) {
            return back()->with('error', 'Hanya invoice dengan status DRAFT yang dapat disubmit');
        }

        $supplierInvoice->update([
            'status' => InvoiceStatus::SUBMITTED,
            'submitted_at' => now(),
            'submitted_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Invoice berhasil disubmit untuk matching');
    }

    /**
     * Cancel invoice
     */
    public function cancel(Request $request, SupplierInvoice $supplierInvoice)
    {
        $this->authorize('update', $supplierInvoice);

        $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        if (!in_array($supplierInvoice->status, [InvoiceStatus::DRAFT, InvoiceStatus::SUBMITTED, InvoiceStatus::VARIANCE])) {
            return back()->with('error', 'Invoice tidak dapat dibatalkan pada status saat ini');
        }

        $supplierInvoice->update([
            'status' => InvoiceStatus::CANCELLED,
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('accounting.invoices.show', $supplierInvoice)
            ->with('success', 'Invoice berhasil dibatalkan');
    }

    /**
     * Download invoice file
     */
    public function downloadInvoiceFile(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('view', $supplierInvoice);

        if (!$supplierInvoice->invoice_file_path) {
            abort(404, 'File invoice tidak ditemukan');
        }

        $filePath = Storage::disk('public')->path($supplierInvoice->invoice_file_path);
        $fileName = 'Invoice-' . $supplierInvoice->internal_number . '.' . pathinfo($supplierInvoice->invoice_file_path, PATHINFO_EXTENSION);

        return response()->download($filePath, $fileName);
    }

    /**
     * Download tax invoice file
     */
    public function downloadTaxInvoiceFile(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('view', $supplierInvoice);

        if (!$supplierInvoice->tax_invoice_file_path) {
            abort(404, 'File faktur pajak tidak ditemukan');
        }

        $filePath = Storage::disk('public')->path($supplierInvoice->tax_invoice_file_path);
        $fileName = 'Tax-Invoice-' . $supplierInvoice->internal_number . '.' . pathinfo($supplierInvoice->tax_invoice_file_path, PATHINFO_EXTENSION);

        return response()->download($filePath, $fileName);
    }

    /**
     * Get PO details for invoice creation
     */
    public function getPurchaseOrderDetails(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('create', SupplierInvoice::class);

        $purchaseOrder->load([
            'lines.item',
            'lines.uom',
            'lines.goodsReceiptLines.goodsReceipt' => function ($query) {
                $query->where('status', 'completed');
            }
        ]);

        return response()->json([
            'purchase_order' => $purchaseOrder,
        ]);
    }
}
