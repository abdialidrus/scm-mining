import { apiFetch } from '@/services/http';

export type UomDto = { id: number; code: string; name: string };
export type ItemDto = {
    id: number;
    sku: string;
    name: string;
    base_uom_id: number | null;
    base_uom_code: string | null;
    base_uom_name: string | null;
};

export type PurchaseRequestStatusHistoryDto = {
    id: number;
    from_status: string | null;
    to_status: string;
    action: string;
    actor_user_id: number | null;
    actor?: { id: number; name: string; email: string } | null;
    meta?: Record<string, any> | null;
    created_at: string;
};

export type PurchaseRequestListItemDto = {
    id: number;
    pr_number: string;
    status: string;
    department_id: number;
    requester_user_id: number;
    // optional when backend includes relations
    department?: { id: number; code: string; name: string } | null;
    requester?: { id: number; name: string; email: string } | null;
    created_at: string | null;
};

export type PurchaseRequestLineDto = {
    id?: number;
    line_no?: number;
    item_id: number;
    quantity: number;
    uom_id?: number | null;
    remarks?: string | null;
    item?: { id: number; sku: string; name: string };
    uom?: UomDto;
};

export type PurchaseRequestDto = {
    id: number;
    pr_number: string;
    status: string;
    department_id: number;
    requester_user_id: number;
    department?: { id: number; code: string; name: string } | null;
    requester?: { id: number; name: string; email: string } | null;
    approvedBy?: { id: number; name: string; email: string } | null;
    remarks?: string | null;
    submitted_at?: string | null;
    approved_at?: string | null;
    created_at?: string | null;
    lines: PurchaseRequestLineDto[];
    status_histories?: PurchaseRequestStatusHistoryDto[];
};

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: unknown;
};

export async function fetchUoms() {
    return apiFetch<{ data: UomDto[] }>(`/api/uoms`);
}

export async function fetchItems(params?: { search?: string; limit?: number }) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.limit) qs.set('limit', String(params.limit));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';
    return apiFetch<{ data: ItemDto[] }>(`/api/items${suffix}`);
}

export async function listPurchaseRequests(params?: {
    search?: string;
    status?: string;
    page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.status) qs.set('status', params.status);
    if (params?.page) qs.set('page', String(params.page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<PurchaseRequestListItemDto> }>(
        `/api/purchase-requests${suffix}`,
    );
}

export async function getPurchaseRequest(id: number) {
    return apiFetch<{ data: PurchaseRequestDto }>(
        `/api/purchase-requests/${id}`,
    );
}

export async function createPurchaseRequest(payload: {
    department_id: number;
    remarks?: string | null;
    lines: Array<{
        item_id: number;
        quantity: number;
        uom_id?: number | null;
        remarks?: string | null;
    }>;
}) {
    return apiFetch<{ data: PurchaseRequestDto }>(`/api/purchase-requests`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function updatePurchaseRequest(
    id: number,
    payload: {
        remarks?: string | null;
        lines: Array<{
            item_id: number;
            quantity: number;
            uom_id?: number | null;
            remarks?: string | null;
        }>;
    },
) {
    return apiFetch<{ data: PurchaseRequestDto }>(
        `/api/purchase-requests/${id}`,
        {
            method: 'PUT',
            body: JSON.stringify(payload),
        },
    );
}

export async function submitPurchaseRequest(id: number) {
    return apiFetch<{ data: PurchaseRequestDto }>(
        `/api/purchase-requests/${id}/submit`,
        {
            method: 'POST',
            body: JSON.stringify({}),
        },
    );
}

export async function approvePurchaseRequest(id: number) {
    return apiFetch<{ data: PurchaseRequestDto }>(
        `/api/purchase-requests/${id}/approve`,
        {
            method: 'POST',
            body: JSON.stringify({}),
        },
    );
}

export async function rejectPurchaseRequest(id: number, reason?: string) {
    return apiFetch<{ data: PurchaseRequestDto }>(
        `/api/purchase-requests/${id}/reject`,
        {
            method: 'POST',
            body: JSON.stringify({ reason }),
        },
    );
}
