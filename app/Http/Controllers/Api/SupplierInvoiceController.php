<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreSupplierInvoiceRequest;
use App\Http\Resources\Accounting\SupplierInvoiceResource;
use App\Models\Accounting\SupplierInvoice;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierInvoiceController extends Controller
{
    /**
     * Display a listing of supplier invoices (API endpoint)
     */
    public function index(Request $request): JsonResponse
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

        $perPage = $request->get('per_page', 10);
        $invoices = $query->paginate($perPage);

        return response()->json([
            'data' => SupplierInvoiceResource::collection($invoices),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'from' => $invoices->firstItem(),
                'to' => $invoices->lastItem(),
            ],
        ]);
    }

    /**
     * Display the specified supplier invoice (API endpoint)
     */
    public function show(SupplierInvoice $supplierInvoice): JsonResponse
    {
        $this->authorize('view', $supplierInvoice);

        $invoice = $supplierInvoice->load([
            'supplier',
            'purchaseOrder.supplier',
            'lines.item',
            'lines.uom',
            'lines.goodsReceiptLine.goodsReceipt',
            'payments.createdBy',
            'createdBy',
            'updatedBy',
        ]);

        return response()->json([
            'data' => new SupplierInvoiceResource($invoice),
        ]);
    }

    /**
     * Remove the specified supplier invoice (API endpoint)
     */
    public function destroy(SupplierInvoice $supplierInvoice): JsonResponse
    {
        $this->authorize('delete', $supplierInvoice);

        // Only allow deletion of draft invoices
        if ($supplierInvoice->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft invoices can be deleted',
            ], 422);
        }

        $supplierInvoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully',
        ]);
    }

    /**
     * Get create form data (suppliers and purchase orders)
     */
    public function getCreateData(): JsonResponse
    {
        $this->authorize('create', SupplierInvoice::class);

        return response()->json([
            'data' => [
                'purchase_orders' => PurchaseOrder::select('id', 'po_number', 'supplier_id')
                    ->whereIn('status', ['SENT'])
                    ->with('supplier:id,name')
                    ->orderBy('po_number', 'desc')
                    ->get(),
            ],
        ]);
    }

    /**
     * Get purchase order details for invoice creation
     */
    public function getPurchaseOrderDetails(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('create', SupplierInvoice::class);

        $po = $purchaseOrder->load([
            'supplier',
            'lines.item',
            'lines.uom',
            'lines.goodsReceiptLines' => function ($query) {
                $query->whereHas('goodsReceipt', function ($q) {
                    $q->where('status', 'completed');
                })->with('goodsReceipt');
            }
        ]);

        return response()->json([
            'data' => $po,
        ]);
    }

    /**
     * Store a newly created invoice (API endpoint)
     */
    public function store(StoreSupplierInvoiceRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Generate internal number
            $data['internal_number'] = SupplierInvoice::generateInternalNumber();
            $data['created_by_user_id'] = $request->user()->id;

            // Auto-set received_date to today (when invoice is received by company)
            $data['received_date'] = now()->toDateString();

            // Ensure nullable enum fields are explicitly null if not provided
            $data['matching_status'] = $data['matching_status'] ?? null;
            $data['approval_status'] = $data['approval_status'] ?? null;

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

            // Create lines with sequential line numbers
            $lineNumber = 1;
            foreach ($data['lines'] as $lineData) {
                $lineData['supplier_invoice_id'] = $invoice->id;
                $lineData['line_number'] = $lineNumber++;

                // Set default matching_status to PENDING if not provided
                $lineData['matching_status'] = $lineData['matching_status'] ?? 'PENDING';

                $invoice->lines()->create($lineData);
            }

            DB::commit();

            // Reload invoice with relationships
            $invoice->load([
                'supplier',
                'purchaseOrder',
                'lines.item',
                'lines.uom',
            ]);

            return response()->json([
                'data' => new SupplierInvoiceResource($invoice),
                'message' => 'Invoice berhasil dibuat dengan nomor ' . $invoice->internal_number,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal membuat invoice: ' . $e->getMessage(),
            ], 500);
        }
    }
}
