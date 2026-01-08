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

    // Check if body is FormData - don't set Content-Type for multipart uploads
    const isFormData = init?.body instanceof FormData;

    const headers: HeadersInit = {
        Accept: 'application/json',
        ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
        ...(init?.headers ?? {}),
    };

    // Only set Content-Type for JSON, let browser set it for FormData
    if (!isFormData) {
        (headers as Record<string, string>)['Content-Type'] =
            'application/json';
    }

    const res = await fetch(input, {
        headers,
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
