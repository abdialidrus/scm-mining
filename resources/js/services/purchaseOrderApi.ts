import { apiFetch } from '@/services/http';

export type PurchaseOrderLineDto = {
    id: number;
    line_no: number;
    item_id: number;
    quantity: number;
    uom_id: number;
    unit_price: number;
    remarks?: string | null;
    item?: { id: number; sku: string; name: string } | null;
    uom?: { id: number; code: string; name: string } | null;
};

export type PurchaseOrderStatusHistoryDto = {
    id: number;
    from_status: string | null;
    to_status: string;
    action: string;
    actor_user_id: number | null;
    actor?: { id: number; name: string; email: string } | null;
    meta?: Record<string, any> | null;
    created_at: string;
};

export type PurchaseOrderDto = {
    id: number;
    po_number: string;
    status: string;

    supplier_id: number;
    supplier?: { id: number; code: string; name: string } | null;

    currency_code: string;
    tax_rate: number;

    subtotal_amount?: number | string | null;
    tax_amount?: number | string | null;
    total_amount?: number | string | null;
    totals_snapshot?: Record<string, any> | null;

    supplier_snapshot?: Record<string, any> | null;
    tax_snapshot?: Record<string, any> | null;

    submitted_at?: string | null;
    approved_at?: string | null;
    sent_at?: string | null;
    closed_at?: string | null;
    cancelled_at?: string | null;
    cancel_reason?: string | null;

    created_at?: string | null;
    updated_at?: string | null;

    purchase_requests?: Array<{
        id: number;
        pr_number: string;
        status: string;
    }>;

    lines: PurchaseOrderLineDto[];
    status_histories?: PurchaseOrderStatusHistoryDto[];
};

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: any;
};

export async function listPurchaseOrders(params?: {
    search?: string;
    status?: string;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.status) qs.set('status', params.status);
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<PurchaseOrderDto> }>(
        `/api/purchase-orders${suffix}`,
    );
}

export async function getPurchaseOrder(id: number) {
    return apiFetch<{ data: PurchaseOrderDto }>(`/api/purchase-orders/${id}`);
}

export async function createPurchaseOrder(payload: {
    supplier_id: number;
    purchase_request_ids: number[];
    currency_code?: string | null;
    tax_rate?: number | null;
}) {
    return apiFetch<{ data: PurchaseOrderDto }>(`/api/purchase-orders`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function submitPurchaseOrder(id: number) {
    return apiFetch<{ data: PurchaseOrderDto }>(
        `/api/purchase-orders/${id}/submit`,
        {
            method: 'POST',
            body: JSON.stringify({}),
        },
    );
}

export async function approvePurchaseOrder(id: number) {
    return apiFetch<{ data: PurchaseOrderDto }>(
        `/api/purchase-orders/${id}/approve`,
        {
            method: 'POST',
            body: JSON.stringify({}),
        },
    );
}

export async function sendPurchaseOrder(id: number) {
    return apiFetch<{ data: PurchaseOrderDto }>(
        `/api/purchase-orders/${id}/send`,
        {
            method: 'POST',
            body: JSON.stringify({}),
        },
    );
}

export async function closePurchaseOrder(id: number) {
    return apiFetch<{ data: PurchaseOrderDto }>(
        `/api/purchase-orders/${id}/close`,
        {
            method: 'POST',
            body: JSON.stringify({}),
        },
    );
}

export async function cancelPurchaseOrder(id: number, reason?: string) {
    return apiFetch<{ data: PurchaseOrderDto }>(
        `/api/purchase-orders/${id}/cancel`,
        {
            method: 'POST',
            body: JSON.stringify({ reason }),
        },
    );
}

export async function updatePurchaseOrderDraft(
    id: number,
    payload: {
        supplier_id: number;
        currency_code: string;
        tax_rate: number;
        lines: Array<{
            id: number;
            unit_price: number;
            remarks?: string | null;
        }>;
    },
) {
    return apiFetch<{ data: PurchaseOrderDto }>(`/api/purchase-orders/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function reopenPurchaseOrder(id: number, reason?: string) {
    return apiFetch<{ data: PurchaseOrderDto }>(
        `/api/purchase-orders/${id}/reopen`,
        {
            method: 'POST',
            body: JSON.stringify({ reason }),
        },
    );
}
