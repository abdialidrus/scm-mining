export type StatusUi = {
    /** Tailwind classes applied to the Badge component */
    badgeClass: string;
};

export type StatusModule = 'PR' | 'PO';

export type GetStatusUiInput =
    | string
    | null
    | undefined
    | {
          module?: StatusModule;
          status: string | null | undefined;
      };

function fallback(): StatusUi {
    return {
        badgeClass: '',
    };
}

function normalizeStatus(status: string | null | undefined): string {
    return String(status ?? '').toUpperCase();
}

function resolveStatus(input: GetStatusUiInput): {
    status: string;
    module?: StatusModule;
} {
    if (typeof input === 'object' && input !== null && 'status' in input) {
        return {
            status: normalizeStatus(input.status),
            module: input.module,
        };
    }

    return {
        status: normalizeStatus(input as any),
        module: undefined,
    };
}

/**
 * Centralized status->UI mapping.
 * Keep it generic so multiple modules (PR/PO/etc) can share it.
 *
 * Usage:
 *  - getStatusUi('DRAFT')
 *  - getStatusUi({ module: 'PO', status: po.status })
 */
export function getStatusUi(input: GetStatusUiInput): StatusUi {
    const { status: s, module } = resolveStatus(input);

    // Module overrides (only when needed). Leave empty by default.
    // Example for future conflicts:
    // if (module === 'PO' && s === 'APPROVED') return { badgeClass: '...' };
    if (module === 'PR') {
        // currently no overrides
    }

    if (module === 'PO') {
        // currently no overrides
    }

    // Common statuses across modules
    if (s === 'DRAFT') {
        return { badgeClass: 'bg-gray-500 text-white dark:bg-gray-600' };
    }

    if (s === 'SUBMITTED') {
        return { badgeClass: 'bg-blue-500 text-white dark:bg-blue-600' };
    }

    if (s === 'IN_APPROVAL') {
        return { badgeClass: 'bg-amber-500 text-white dark:bg-amber-600' };
    }

    if (s === 'APPROVED') {
        return { badgeClass: 'bg-green-600 text-white dark:bg-green-700' };
    }

    if (s === 'REJECTED') {
        return { badgeClass: 'bg-red-600 text-white dark:bg-red-700' };
    }

    if (s === 'CANCELLED') {
        return { badgeClass: 'bg-zinc-700 text-white dark:bg-zinc-800' };
    }

    // Module-specific but still shareable
    if (s === 'CONVERTED_TO_PO') {
        return { badgeClass: 'bg-green-600 text-white dark:bg-green-700' };
    }

    if (s === 'SENT') {
        return { badgeClass: 'bg-indigo-600 text-white dark:bg-indigo-700' };
    }

    if (s === 'CLOSED') {
        return { badgeClass: 'bg-slate-700 text-white dark:bg-slate-800' };
    }

    return fallback();
}
