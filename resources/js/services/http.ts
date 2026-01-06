export type ApiErrorPayload = {
    message?: string;
    errors?: Record<string, string[]>;
};

function getCookie(name: string): string | undefined {
    const match = document.cookie.match(new RegExp(`(^|;\\s*)${name}=([^;]*)`));
    return match ? decodeURIComponent(match[2]) : undefined;
}

function getCsrfToken(): string | undefined {
    // Try XSRF cookie first (Sanctum standard)
    const xsrfToken = getCookie('XSRF-TOKEN');
    if (xsrfToken) return xsrfToken;

    // Fallback to meta tag
    const metaTag = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    );
    return metaTag?.content;
}

let csrfCookieInitialized = false;

async function ensureCsrfCookie(): Promise<void> {
    if (csrfCookieInitialized || getCookie('XSRF-TOKEN')) {
        return;
    }

    // Request CSRF cookie from Sanctum
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'same-origin',
    });

    csrfCookieInitialized = true;
}

export async function apiFetch<T>(
    input: RequestInfo | URL,
    init?: RequestInit,
): Promise<T> {
    // Ensure CSRF cookie is set for state-changing methods
    const method = init?.method?.toUpperCase() || 'GET';
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
        await ensureCsrfCookie();
    }

    const csrfToken = getCsrfToken();

    const res = await fetch(input, {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
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
