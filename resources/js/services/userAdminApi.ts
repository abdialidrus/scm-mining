import { apiFetch } from '@/services/http';

export type RoleDto = { id: number; name: string };

export type DepartmentDto = { id: number; code: string; name: string };

export type UserListItemDto = {
    id: number;
    name: string;
    email: string;
    department_id?: number | null;
    department?: DepartmentDto | null;
    roles?: Array<{ name: string }>;
};

export type UserDto = {
    id: number;
    name: string;
    email: string;
    department_id?: number | null;
    department?: DepartmentDto | null;
    roles?: Array<{ name: string }>;
};

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: unknown;
};

export async function getRoles() {
    return apiFetch<{ data: RoleDto[] }>(`/api/roles`);
}

export async function listUsers(params?: {
    search?: string;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<UserListItemDto> }>(
        `/api/users${suffix}`,
    );
}

export async function getUser(id: number) {
    return apiFetch<{ data: UserDto }>(`/api/users/${id}`);
}

export async function createUser(payload: {
    name: string;
    email: string;
    password: string;
    department_id?: number | null;
    roles?: string[];
}) {
    return apiFetch<{ data: UserDto }>(`/api/users`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function updateUser(
    id: number,
    payload: {
        name?: string;
        email?: string;
        password?: string | null;
        department_id?: number | null;
        roles?: string[];
    },
) {
    return apiFetch<{ data: UserDto }>(`/api/users/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export async function deleteUser(id: number) {
    return apiFetch<{ message: string }>(`/api/users/${id}`, {
        method: 'DELETE',
        body: JSON.stringify({}),
    });
}
