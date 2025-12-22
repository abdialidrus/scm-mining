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

    // ----------------------------
    // PR palette
    // ----------------------------
    if (module === 'PR') {
        if (s === 'DRAFT') {
            return { badgeClass: 'bg-slate-500 text-white dark:bg-slate-600' };
        }

        if (s === 'SUBMITTED') {
            return { badgeClass: 'bg-sky-600 text-white dark:bg-sky-700' };
        }

        if (s === 'IN_APPROVAL') {
            return { badgeClass: 'bg-amber-500 text-white dark:bg-amber-600' };
        }

        if (s === 'APPROVED') {
            return {
                badgeClass: 'bg-emerald-600 text-white dark:bg-emerald-700',
            };
        }

        if (s === 'REJECTED') {
            return { badgeClass: 'bg-rose-600 text-white dark:bg-rose-700' };
        }

        if (s === 'CANCELLED') {
            return { badgeClass: 'bg-zinc-700 text-white dark:bg-zinc-800' };
        }

        // PR terminal state (handed off)
        if (s === 'CONVERTED_TO_PO') {
            return { badgeClass: 'bg-teal-600 text-white dark:bg-teal-700' };
        }
    }

    // ----------------------------
    // PO palette
    // ----------------------------
    if (module === 'PO') {
        if (s === 'DRAFT') {
            return { badgeClass: 'bg-slate-500 text-white dark:bg-slate-600' };
        }

        if (s === 'SUBMITTED') {
            return { badgeClass: 'bg-blue-600 text-white dark:bg-blue-700' };
        }

        if (s === 'IN_APPROVAL') {
            return { badgeClass: 'bg-amber-500 text-white dark:bg-amber-600' };
        }

        if (s === 'APPROVED') {
            // Distinguish from PR APPROVED slightly (use green)
            return { badgeClass: 'bg-green-600 text-white dark:bg-green-700' };
        }

        if (s === 'REJECTED') {
            return { badgeClass: 'bg-red-600 text-white dark:bg-red-700' };
        }

        if (s === 'CANCELLED') {
            return { badgeClass: 'bg-zinc-700 text-white dark:bg-zinc-800' };
        }

        if (s === 'SENT') {
            // outbound / vendor-facing
            return {
                badgeClass: 'bg-indigo-600 text-white dark:bg-indigo-700',
            };
        }

        if (s === 'CLOSED') {
            // completed / archived
            return {
                badgeClass: 'bg-neutral-800 text-white dark:bg-neutral-900',
            };
        }
    }

    // ----------------------------
    // Common fallback (if module is not provided)
    // ----------------------------
    if (s === 'DRAFT') {
        return { badgeClass: 'bg-slate-500 text-white dark:bg-slate-600' };
    }

    if (s === 'SUBMITTED') {
        return { badgeClass: 'bg-blue-600 text-white dark:bg-blue-700' };
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

    if (s === 'CONVERTED_TO_PO') {
        return { badgeClass: 'bg-teal-600 text-white dark:bg-teal-700' };
    }

    if (s === 'SENT') {
        return { badgeClass: 'bg-indigo-600 text-white dark:bg-indigo-700' };
    }

    if (s === 'CLOSED') {
        return { badgeClass: 'bg-neutral-800 text-white dark:bg-neutral-900' };
    }

    return fallback();
}
