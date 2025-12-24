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

export type WarehouseDto = {
    id: number;
    code: string;
    name: string;
    address?: string | null;
    is_active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: unknown;
};

export async function listItems(params?: {
    search?: string;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<ItemDto> }>(`/api/items${suffix}`);
}

export async function listUoms(params?: { page?: number; per_page?: number }) {
    const qs = new URLSearchParams();
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<UomDto> }>(`/api/uoms${suffix}`);
}

export async function listDepartments(params?: {
    search?: string;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<DepartmentDto> }>(
        `/api/departments${suffix}`,
    );
}

export async function listWarehouses(params?: {
    search?: string;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<WarehouseDto> }>(
        `/api/warehouses${suffix}`,
    );
}

// Backward compatible helpers (some pages may still call these)
export async function fetchItems(params?: { search?: string; limit?: number }) {
    // When backend moved to pagination, limit is mapped to per_page.
    return listItems({
        search: params?.search,
        per_page: params?.limit ?? 20,
    }).then((res) => ({ data: (res as any).data?.data ?? [] }));
}

export async function fetchUoms() {
    return listUoms({ per_page: 100 }).then((res) => ({
        data: (res as any).data?.data ?? [],
    }));
}

export async function fetchDepartments(params?: { search?: string }) {
    return listDepartments({ search: params?.search, per_page: 100 }).then(
        (res) => ({
            data: (res as any).data?.data ?? [],
        }),
    );
}

export async function fetchWarehouses(params?: { search?: string }) {
    return listWarehouses({ search: params?.search, per_page: 100 }).then(
        (res) => ({
            data: (res as any).data?.data ?? [],
        }),
    );
}
