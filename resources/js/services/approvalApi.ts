import { apiFetch } from '@/services/http';

export type ApprovalStepDto = {
    id: number;
    step_name: string;
    sequence: number;
};

export type ApprovalDocumentDto = {
    type: string;
    number: string;
    url: string;
    amount: number | null;
    submitted_at: string | null;
    submitter: {
        id: number;
        name: string;
        email: string;
    } | null;
};

export type PendingApprovalDto = {
    id: number;
    status: string;
    comments: string | null;
    created_at: string;
    step: ApprovalStepDto | null;
    assigned_to_role: string | null;
    assigned_to_user_id: number | null;
    document: ApprovalDocumentDto;
};

export type ApprovalStatisticsDto = {
    pending_count: number;
    approved_last_30_days: number;
    rejected_last_30_days: number;
    average_approval_time_hours: number | null;
};

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: unknown;
};

export async function getMyPendingApprovals(params?: {
    search?: string;
    document_type?: string;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.document_type) qs.set('document_type', params.document_type);
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<PendingApprovalDto> }>(
        `/api/approvals/my-pending${suffix}`,
    );
}

export async function getApprovalStatistics() {
    return apiFetch<{ data: ApprovalStatisticsDto }>(
        '/api/approvals/statistics',
    );
}
