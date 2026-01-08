<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use App\Services\PaymentService;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {
        //
    }

    /**
     * Display a listing of outstanding purchase orders
     */
    public function index(Request $request): JsonResponse
    {
        $stats = $this->paymentService->getPaymentStats();

        $purchaseOrders = $this->paymentService->getOutstandingPOs([
            'supplier_id' => $request->get('supplier_id'),
            'payment_status' => $request->get('payment_status'),
            'overdue_only' => $request->boolean('overdue_only'),
            'search' => $request->get('search'),
            'per_page' => $request->get('per_page', 20),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'purchase_orders' => $purchaseOrders,
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Display the specified purchase order with payment details
     */
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load([
            'supplier',
            'submittedBy',
            'goodsReceipts.postedBy',
            'goodsReceipts.lines.item',
            'payments.creator',
            'payments.approver',
            'paymentStatusHistories.changedBy',
        ]);

        return response()->json([
            'success' => true,
            'data' => $purchaseOrder,
        ]);
    }

    /**
     * Get form data for creating a new payment
     */
    public function create(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load(['supplier', 'goodsReceipts']);

        return response()->json([
            'success' => true,
            'data' => $purchaseOrder,
        ]);
    }

    /**
     * Store a newly created payment
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->recordPayment($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => $payment->load(['purchaseOrder', 'creator']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get payment data for editing
     */
    public function edit(SupplierPayment $payment): JsonResponse
    {
        if (!$payment->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft payments can be edited',
            ], 403);
        }

        $payment->load('purchaseOrder.supplier');

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
    }

    /**
     * Update the specified payment
     */
    public function update(UpdatePaymentRequest $request, SupplierPayment $payment): JsonResponse
    {
        if (!$payment->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft payments can be updated',
            ], 403);
        }

        try {
            $updatedPayment = $this->paymentService->updatePayment($payment, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
                'data' => $updatedPayment->load(['purchaseOrder', 'creator']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Confirm a payment
     */
    public function confirm(SupplierPayment $payment): JsonResponse
    {
        if (!$payment->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft payments can be confirmed',
            ], 403);
        }

        try {
            $this->paymentService->confirmPayment($payment);

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'data' => $payment->fresh(['purchaseOrder', 'approver']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel a payment
     */
    public function cancel(Request $request, SupplierPayment $payment): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->paymentService->cancelPayment($payment, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Payment cancelled successfully',
                'data' => $payment->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel payment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get payment statistics
     */
    public function stats(): JsonResponse
    {
        $stats = $this->paymentService->getPaymentStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
