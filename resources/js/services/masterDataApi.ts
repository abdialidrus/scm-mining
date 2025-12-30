import { apiFetch } from '@/services/http';

export type ItemDto = {
    id: number;
    sku: string;
    name: string;
    is_serialized?: boolean;
    criticality_level?: number | null;
    base_uom_id: number | null;
    base_uom_code?: string | null;
    base_uom_name?: string | null;
    base_uom?: UomDto | null;
    item_category_id?: number | null;
    category_code?: string | null;
    category_name?: string | null;
    category_color?: string | null;
    category?: ItemCategoryDto | null;
    created_at?: string;
    updated_at?: string;
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

export type ItemCategoryDto = {
    id: number;
    code: string;
    name: string;
    description?: string | null;
    parent_id: number | null;
    is_active: boolean;
    requires_approval: boolean;
    color_code?: string | null;
    sort_order: number;
    full_path?: string;
    parent?: ItemCategoryDto | null;
    children?: ItemCategoryDto[];
    items_count?: number;
    created_at?: string;
    updated_at?: string;
};

export type ItemCategoryTreeNode = {
    id: number;
    code: string;
    name: string;
    parent_id: number | null;
    full_path: string;
    children: ItemCategoryTreeNode[];
};

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: unknown;
    last_page: number;
    current_page: number;
};

export async function listItems(params?: {
    search?: string;
    category_ids?: number[];
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.category_ids && params.category_ids.length > 0) {
        params.category_ids.forEach((id) =>
            qs.append('category_ids[]', String(id)),
        );
    }
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<ItemDto> }>(`/api/items${suffix}`);
}

export async function getItem(id: number) {
    return apiFetch<{ data: ItemDto }>(`/api/items/${id}`);
}

export async function createItem(data: {
    sku: string;
    name: string;
    is_serialized?: boolean;
    criticality_level?: number;
    base_uom_id: number;
    item_category_id?: number | null;
}) {
    return apiFetch<{ data: ItemDto }>('/api/items', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateItem(
    id: number,
    data: {
        sku?: string;
        name?: string;
        is_serialized?: boolean;
        criticality_level?: number;
        base_uom_id?: number;
        item_category_id?: number | null;
    },
) {
    return apiFetch<{ data: ItemDto }>(`/api/items/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function deleteItem(id: number) {
    return apiFetch<{ message: string }>(`/api/items/${id}`, {
        method: 'DELETE',
    });
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

// Item Categories
export async function listItemCategories(params?: {
    search?: string;
    is_active?: boolean;
    parent_id?: number | null;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.is_active !== undefined)
        qs.set('is_active', params.is_active ? '1' : '0');
    if (params?.parent_id !== undefined && params.parent_id !== null)
        qs.set('parent_id', String(params.parent_id));
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<ItemCategoryDto> }>(
        `/api/item-categories${suffix}`,
    );
}

export async function getItemCategory(id: number) {
    return apiFetch<{ data: ItemCategoryDto }>(`/api/item-categories/${id}`);
}

export async function createItemCategory(data: {
    code: string;
    name: string;
    description?: string;
    parent_id?: number | null;
    is_active?: boolean;
    requires_approval?: boolean;
    color_code?: string;
    sort_order?: number;
}) {
    return apiFetch<{ data: ItemCategoryDto }>('/api/item-categories', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateItemCategory(
    id: number,
    data: {
        code?: string;
        name?: string;
        description?: string;
        parent_id?: number | null;
        is_active?: boolean;
        requires_approval?: boolean;
        color_code?: string;
        sort_order?: number;
    },
) {
    return apiFetch<{ data: ItemCategoryDto }>(`/api/item-categories/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function deleteItemCategory(id: number) {
    return apiFetch<{ message: string }>(`/api/item-categories/${id}`, {
        method: 'DELETE',
    });
}

export async function getItemCategoryTree() {
    return apiFetch<{ data: ItemCategoryTreeNode[] }>(
        '/api/item-categories/tree',
    );
}
