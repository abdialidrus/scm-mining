import { apiFetch } from '@/services/http';

export type PutAwayLineDto = {
    id: number;
    goods_receipt_line_id: number;
    item_id: number;
    uom_id: number | null;
    source_location_id: number | null;
    destination_location_id: number | null;
    qty: number;
    remarks?: string | null;
    item?: { id: number; sku: string; name: string } | null;
    uom?: { id: number; code: string; name: string } | null;
    sourceLocation?: { id: number; code: string; name: string } | null;
    destinationLocation?: { id: number; code: string; name: string } | null;
};

export type PutAwayStatusHistoryDto = {
    id: number;
    from_status: string | null;
    to_status: string;
    action: string;
    actor_user_id: number | null;
    actor?: { id: number; name: string; email: string } | null;
    meta?: Record<string, any> | null;
    created_at: string;
};

export type PutAwayDto = {
    id: number;
    put_away_number: string;
    status: string;

    goods_receipt_id: number;
    goodsReceipt?: { id: number; gr_number: string; status: string } | null;

    warehouse_id: number;
    warehouse?: { id: number; code: string; name: string } | null;

    put_away_at?: string | null;
    remarks?: string | null;

    posted_at?: string | null;
    posted_by_user_id?: number | null;

    cancelled_at?: string | null;
    cancelled_by_user_id?: number | null;
    cancel_reason?: string | null;

    lines?: PutAwayLineDto[];
    status_histories?: PutAwayStatusHistoryDto[];
};

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: unknown;
};

export async function listPutAways(params?: {
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
    return apiFetch<{ data: Paginated<PutAwayDto> }>(
        `/api/put-aways${q ? `?${q}` : ''}`,
    );
}

export async function getPutAway(id: number) {
    return apiFetch<{ data: PutAwayDto }>(`/api/put-aways/${id}`);
}

export async function createPutAway(payload: {
    goods_receipt_id: number;
    put_away_at?: string | null;
    remarks?: string | null;
    lines: Array<{
        goods_receipt_line_id: number;
        destination_location_id: number;
        qty: number;
        remarks?: string | null;
    }>;
}) {
    return apiFetch<{ data: PutAwayDto }>(`/api/put-aways`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function postPutAway(id: number) {
    return apiFetch<{ data: PutAwayDto }>(`/api/put-aways/${id}/post`, {
        method: 'POST',
        body: JSON.stringify({}),
    });
}

export async function cancelPutAway(id: number, reason?: string | null) {
    return apiFetch<{ data: PutAwayDto }>(`/api/put-aways/${id}/cancel`, {
        method: 'POST',
        body: JSON.stringify({ reason: reason ?? null }),
    });
}
