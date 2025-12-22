export type ApiErrorPayload = {
    message?: string;
    errors?: Record<string, string[]>;
};

function getCookie(name: string): string | undefined {
    const match = document.cookie.match(new RegExp(`(^|;\\s*)${name}=([^;]*)`));
    return match ? decodeURIComponent(match[2]) : undefined;
}

export async function apiFetch<T>(
    input: RequestInfo | URL,
    init?: RequestInit,
): Promise<T> {
    const xsrfToken = getCookie('XSRF-TOKEN');

    const res = await fetch(input, {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
            ...(init?.headers ?? {}),
        },
        credentials: 'same-origin',
        ...init,
    });

    if (!res.ok) {
        let payload: ApiErrorPayload | undefined;
        try {
            payload = (await res.json()) as ApiErrorPayload;
        } catch {
            // ignore
        }

        const message = payload?.message || `Request failed (${res.status})`;
        const err = new Error(message) as Error & {
            status?: number;
            payload?: ApiErrorPayload;
        };
        err.status = res.status;
        err.payload = payload;
        throw err;
    }

    return (await res.json()) as T;
}
