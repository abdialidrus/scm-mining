import { apiFetch } from '@/services/http';

export type GoodsReceiptLineDto = {
    id: number;
    line_no: number;
    purchase_order_line_id: number;
    item_id: number;
    uom_id: number;
    ordered_quantity: number;
    received_quantity: number;
    remarks?: string | null;
    item?: { id: number; sku: string; name: string } | null;
    uom?: { id: number; code: string; name: string } | null;
};

export type GoodsReceiptStatusHistoryDto = {
    id: number;
    from_status: string | null;
    to_status: string;
    action: string;
    actor_user_id: number | null;
    actor?: { id: number; name: string; email: string } | null;
    meta?: Record<string, any> | null;
    created_at: string;
};

export type GoodsReceiptDto = {
    id: number;
    gr_number: string;
    status: string;

    purchase_order_id: number;
    purchaseOrder?: { id: number; po_number: string } | null;

    warehouse_id: number;
    warehouse?: { id: number; code: string; name: string } | null;

    received_at?: string | null;
    remarks?: string | null;

    posted_at?: string | null;
    posted_by_user_id?: number | null;

    cancel_reason?: string | null;

    lines?: GoodsReceiptLineDto[];
    status_histories?: GoodsReceiptStatusHistoryDto[];
};

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: unknown;
};

export async function listGoodsReceipts(params?: {
    search?: string;
    status?: string;
    page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.status) qs.set('status', params.status);
    if (params?.page) qs.set('page', String(params.page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<GoodsReceiptDto> }>(
        `/api/goods-receipts${suffix}`,
    );
}

export async function getGoodsReceipt(id: number) {
    return apiFetch<{ data: GoodsReceiptDto }>(`/api/goods-receipts/${id}`);
}

export async function createGoodsReceipt(payload: {
    purchase_order_id: number;
    warehouse_id: number;
    received_at?: string | null;
    remarks?: string | null;
    lines: Array<{
        purchase_order_line_id: number;
        received_quantity: number;
        remarks?: string | null;
    }>;
}) {
    return apiFetch<{ data: GoodsReceiptDto }>(`/api/goods-receipts`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function postGoodsReceipt(id: number) {
    return apiFetch<{ data: GoodsReceiptDto }>(
        `/api/goods-receipts/${id}/post`,
        {
            method: 'POST',
            body: JSON.stringify({}),
        },
    );
}

export async function cancelGoodsReceipt(id: number, reason?: string | null) {
    return apiFetch<{ data: GoodsReceiptDto }>(
        `/api/goods-receipts/${id}/cancel`,
        {
            method: 'POST',
            body: JSON.stringify({ reason: reason ?? null }),
        },
    );
}
