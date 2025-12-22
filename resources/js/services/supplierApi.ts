import { apiFetch } from '@/services/http';

export type SupplierDto = {
    id: number;
    code: string;
    name: string;
    contact_name: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    created_at?: string | null;
    updated_at?: string | null;
};

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: unknown;
};

export async function listSuppliers(params?: {
    search?: string;
    page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.page) qs.set('page', String(params.page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<SupplierDto> }>(
        `/api/suppliers${suffix}`,
    );
}

export async function getSupplier(id: number) {
    return apiFetch<{ data: SupplierDto }>(`/api/suppliers/${id}`);
}

export async function createSupplier(payload: {
    name: string;
    contact_name?: string | null;
    phone?: string | null;
    email?: string | null;
    address?: string | null;
}) {
    return apiFetch<{ data: SupplierDto }>(`/api/suppliers`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function updateSupplier(
    id: number,
    payload: {
        name?: string | null;
        contact_name?: string | null;
        phone?: string | null;
        email?: string | null;
        address?: string | null;
    },
) {
    return apiFetch<{ data: SupplierDto }>(`/api/suppliers/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function deleteSupplier(id: number) {
    return apiFetch<{ message: string }>(`/api/suppliers/${id}`, {
        method: 'DELETE',
        body: JSON.stringify({}),
    });
}
