import { apiFetch } from '@/services/http';

// ==================== Types ====================

export type PaymentStatus = 'DRAFT' | 'CONFIRMED' | 'CANCELLED';
export type PaymentMethod = 'TRANSFER' | 'CASH' | 'CHECK' | 'GIRO';
export type POPaymentStatus = 'UNPAID' | 'PARTIAL' | 'PAID' | 'OVERDUE';

export type SupplierPaymentDto = {
    id: number;
    payment_number: string;
    purchase_order_id: number;
    supplier_invoice_number: string;
    supplier_invoice_date: string;
    supplier_invoice_amount: number;
    supplier_invoice_file_path: string | null;
    payment_date: string;
    payment_amount: number;
    payment_method: PaymentMethod;
    payment_reference: string | null;
    payment_proof_file_path: string | null;
    bank_account_from: string | null;
    bank_account_to: string | null;
    status: PaymentStatus;
    notes: string | null;
    approved_by_user_id: number | null;
    approved_at: string | null;
    created_by_user_id: number;
    created_at: string;
    updated_at: string;

    // Relationships
    purchase_order?: PurchaseOrderSummary;
    creator?: UserSummary;
    approver?: UserSummary;
};

export type PurchaseOrderSummary = {
    id: number;
    po_number: string;
    total_amount: number;
    payment_status: POPaymentStatus;
    payment_term_days: number;
    payment_due_date: string | null;
    total_paid: number;
    outstanding_amount: number;
    supplier?: SupplierSummary;
    submitted_by?: UserSummary;
    goods_receipts?: GoodsReceiptSummary[];
    payments?: SupplierPaymentDto[];
    payment_status_histories?: PaymentStatusHistoryDto[];
};

export type SupplierSummary = {
    id: number;
    code: string;
    name: string;
    email: string | null;
    phone: string | null;
};

export type UserSummary = {
    id: number;
    name: string;
    email: string;
};

export type GoodsReceiptSummary = {
    id: number;
    gr_number: string;
    received_at: string;
    status: string;
    posted_by?: UserSummary;
};

export type PaymentStatusHistoryDto = {
    id: number;
    purchase_order_id: number;
    old_status: POPaymentStatus;
    new_status: POPaymentStatus;
    changed_by_user_id: number;
    notes: string | null;
    changed_at: string;
    changed_by?: UserSummary;
};

export type PaymentStatsDto = {
    total_outstanding: number;
    overdue_count: number;
    overdue_amount: number;
    this_month_paid: number;
    pending_confirmation: number;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
};

// ==================== API Functions ====================

/**
 * Get payment statistics for dashboard
 */
export async function getPaymentStats() {
    return apiFetch<{ success: boolean; data: PaymentStatsDto }>(
        '/api/payments/stats',
    );
}

/**
 * Get list of outstanding purchase orders
 */
export async function getOutstandingPOs(params?: {
    supplier_id?: number;
    payment_status?: POPaymentStatus;
    overdue_only?: boolean;
    search?: string;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.supplier_id) qs.set('supplier_id', String(params.supplier_id));
    if (params?.payment_status) qs.set('payment_status', params.payment_status);
    if (params?.overdue_only) qs.set('overdue_only', '1');
    if (params?.search) qs.set('search', params.search);
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{
        success: boolean;
        data: {
            purchase_orders: Paginated<PurchaseOrderSummary>;
            stats: PaymentStatsDto;
        };
    }>(`/api/payments/outstanding${suffix}`);
}

/**
 * Get purchase order details with payment history
 */
export async function getPurchaseOrderForPayment(id: number) {
    return apiFetch<{ success: boolean; data: PurchaseOrderSummary }>(
        `/api/payments/purchase-orders/${id}`,
    );
}

/**
 * Get form data for creating new payment
 */
export async function getPurchaseOrderForPaymentCreate(id: number) {
    return apiFetch<{ success: boolean; data: PurchaseOrderSummary }>(
        `/api/payments/purchase-orders/${id}/create`,
    );
}

/**
 * Record a new payment
 */
export async function createPayment(payload: FormData) {
    return apiFetch<{
        success: boolean;
        message: string;
        data: SupplierPaymentDto;
    }>('/api/payments', {
        method: 'POST',
        body: payload,
    });
}

/**
 * Get payment for editing (draft only)
 */
export async function getPaymentForEdit(id: number) {
    return apiFetch<{ success: boolean; data: SupplierPaymentDto }>(
        `/api/payments/${id}/edit`,
    );
}

/**
 * Update payment (draft only)
 */
export async function updatePayment(id: number, payload: FormData) {
    // Laravel requires _method for PUT with FormData
    payload.append('_method', 'PUT');

    return apiFetch<{
        success: boolean;
        message: string;
        data: SupplierPaymentDto;
    }>(`/api/payments/${id}`, {
        method: 'POST', // Use POST with _method override
        body: payload,
    });
}

/**
 * Confirm a payment
 */
export async function confirmPayment(id: number) {
    return apiFetch<{
        success: boolean;
        message: string;
        data: SupplierPaymentDto;
    }>(`/api/payments/${id}/confirm`, {
        method: 'POST',
        body: JSON.stringify({}),
    });
}

/**
 * Cancel a payment
 */
export async function cancelPayment(id: number, reason: string) {
    return apiFetch<{
        success: boolean;
        message: string;
        data: SupplierPaymentDto;
    }>(`/api/payments/${id}/cancel`, {
        method: 'POST',
        body: JSON.stringify({ reason }),
    });
}
