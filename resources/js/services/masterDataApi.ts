import { apiFetch } from '@/services/http';

export type ItemDto = {
    id: number;
    item_code: string;
    item_name: string;
    uom: string;
};

export type UomDto = {
    id: number;
    code: string;
    name: string;
};

export async function fetchItems(params?: { search?: string; limit?: number }) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.limit) qs.set('limit', String(params.limit));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: ItemDto[] }>(`/api/items${suffix}`);
}

export async function fetchUoms() {
    return apiFetch<{ data: UomDto[] }>(`/api/uoms`);
}
