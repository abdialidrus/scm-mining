import { apiFetch } from '@/services/http';

export type PickingOrderLineDto = {
    id: number;
    picking_order_id: number;
    item_id: number;
    uom_id: number | null;
    source_location_id: number;
    qty: number;
    remarks?: string | null;
    serial_numbers?: string[] | null;
    item?: {
        id: number;
        sku: string;
        name: string;
        is_serialized?: boolean;
    } | null;
    uom?: { id: number; code: string; name: string } | null;
    source_location?: { id: number; code: string; name: string } | null;
};

export type PickingOrderStatusHistoryDto = {
    id: number;
    from_status: string | null;
    to_status: string;
    action: string;
    actor_user_id: number | null;
    actor?: { id: number; name: string; email: string } | null;
    meta?: Record<string, any> | null;
    created_at: string;
};

export type PickingOrderDto = {
    id: number;
    picking_order_number: string;
    status: string;

    department_id: number | null;
    department?: { id: number; name: string } | null;

    warehouse_id: number;
    warehouse?: { id: number; code: string; name: string } | null;

    purpose?: string | null;
    picked_at?: string | null;
    remarks?: string | null;

    created_by_user_id?: number | null;
    posted_at?: string | null;
    posted_by_user_id?: number | null;

    cancelled_at?: string | null;
    cancelled_by_user_id?: number | null;
    cancel_reason?: string | null;

    lines?: PickingOrderLineDto[];
    status_histories?: PickingOrderStatusHistoryDto[];

    created_at?: string;
    updated_at?: string;
};

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: unknown;
};

export async function listPickingOrders(params?: {
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

    const q = qs.toString();
    return apiFetch<{ data: Paginated<PickingOrderDto> }>(
        `/api/picking-orders${q ? `?${q}` : ''}`,
    );
}

export async function getPickingOrder(id: number) {
    return apiFetch<{ data: PickingOrderDto }>(`/api/picking-orders/${id}`);
}

export async function createPickingOrder(payload: {
    warehouse_id: number;
    department_id?: number | null;
    purpose?: string | null;
    picked_at?: string | null;
    remarks?: string | null;
    lines: Array<{
        item_id: number;
        uom_id?: number | null;
        source_location_id: number;
        qty: number;
        remarks?: string | null;
    }>;
}) {
    return apiFetch<{ data: PickingOrderDto }>(`/api/picking-orders`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function postPickingOrder(id: number) {
    return apiFetch<{ data: PickingOrderDto }>(
        `/api/picking-orders/${id}/post`,
        {
            method: 'POST',
            body: JSON.stringify({}),
        },
    );
}

export async function cancelPickingOrder(id: number, reason?: string | null) {
    return apiFetch<{ data: PickingOrderDto }>(
        `/api/picking-orders/${id}/cancel`,
        {
            method: 'POST',
            body: JSON.stringify({ reason: reason ?? null }),
        },
    );
}
