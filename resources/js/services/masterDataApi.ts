import { apiFetch } from '@/services/http';

export type ItemDto = {
    id: number;
    sku: string;
    name: string;
    base_uom_id: number | null;
    base_uom_code: string | null;
    base_uom_name: string | null;
};

export type UomDto = {
    id: number;
    code: string;
    name: string;
};

export type DepartmentDto = {
    id: number;
    code: string;
    name: string;
    parent_id: number | null;
    head_user_id: number | null;
    head?: { id: number; name: string; email: string } | null;
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

export async function fetchDepartments(params?: { search?: string }) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: DepartmentDto[] }>(`/api/departments${suffix}`);
}
