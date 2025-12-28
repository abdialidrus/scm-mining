import { apiFetch } from '@/services/http';

export type ApprovalWorkflowStepDto = {
    id: number;
    step_order: number;
    step_code?: string;
    step_name?: string;
    step_description?: string;
    approver_type: 'ROLE' | 'USER' | 'DEPARTMENT_HEAD' | 'DYNAMIC';
    approver_value?: string | null;
    approver_role?: string | null; // Accessor for ROLE type
    approver_user_id?: number | null; // Accessor for USER type
    condition_field?: string | null;
    condition_operator?: string | null;
    condition_value?: string | null;
    is_required?: boolean;
    allow_skip?: boolean;
    allow_parallel?: boolean;
    is_final_step?: boolean; // Accessor
    meta?: Record<string, any> | null;
};

export type ApprovalWorkflowDto = {
    id: number;
    code: string;
    name: string;
    description?: string | null;
    document_type: string;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
    steps?: ApprovalWorkflowStepDto[];
};

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: unknown;
};

export async function listApprovalWorkflows(params?: {
    search?: string;
    document_type?: string;
    is_active?: boolean;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.document_type) qs.set('document_type', params.document_type);
    if (params?.is_active !== undefined)
        qs.set('is_active', params.is_active ? '1' : '0');
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<ApprovalWorkflowDto> }>(
        `/api/approval-workflows${suffix}`,
    );
}

export async function getApprovalWorkflow(id: number) {
    return apiFetch<{ data: ApprovalWorkflowDto }>(
        `/api/approval-workflows/${id}`,
    );
}

export async function createApprovalWorkflow(payload: {
    code: string;
    name: string;
    description?: string | null;
    document_type: string;
    is_active: boolean;
}) {
    return apiFetch<{ data: ApprovalWorkflowDto }>(`/api/approval-workflows`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function updateApprovalWorkflow(
    id: number,
    payload: {
        code?: string;
        name?: string;
        description?: string | null;
        document_type?: string;
        is_active?: boolean;
    },
) {
    return apiFetch<{ data: ApprovalWorkflowDto }>(
        `/api/approval-workflows/${id}`,
        {
            method: 'PUT',
            body: JSON.stringify(payload),
        },
    );
}

export async function deleteApprovalWorkflow(id: number) {
    return apiFetch<{ message: string }>(`/api/approval-workflows/${id}`, {
        method: 'DELETE',
        body: JSON.stringify({}),
    });
}

export async function createApprovalWorkflowStep(
    workflowId: number,
    payload: {
        step_order: number;
        approver_type: 'ROLE' | 'USER' | 'DEPARTMENT_HEAD' | 'DYNAMIC';
        approver_role?: string | null;
        approver_user_id?: number | null;
        condition_field?: string | null;
        condition_operator?: string | null;
        condition_value?: string | null;
        is_final_step?: boolean;
        meta?: Record<string, any> | null;
    },
) {
    return apiFetch<{ data: ApprovalWorkflowStepDto }>(
        `/api/approval-workflows/${workflowId}/steps`,
        {
            method: 'POST',
            body: JSON.stringify(payload),
        },
    );
}

export async function updateApprovalWorkflowStep(
    workflowId: number,
    stepId: number,
    payload: {
        step_order?: number;
        approver_type?: 'ROLE' | 'USER' | 'DEPARTMENT_HEAD' | 'DYNAMIC';
        approver_role?: string | null;
        approver_user_id?: number | null;
        condition_field?: string | null;
        condition_operator?: string | null;
        condition_value?: string | null;
        is_final_step?: boolean;
        meta?: Record<string, any> | null;
    },
) {
    return apiFetch<{ data: ApprovalWorkflowStepDto }>(
        `/api/approval-workflows/${workflowId}/steps/${stepId}`,
        {
            method: 'PUT',
            body: JSON.stringify(payload),
        },
    );
}

export async function deleteApprovalWorkflowStep(
    workflowId: number,
    stepId: number,
) {
    return apiFetch<{ message: string }>(
        `/api/approval-workflows/${workflowId}/steps/${stepId}`,
        {
            method: 'DELETE',
            body: JSON.stringify({}),
        },
    );
}

export async function reorderApprovalWorkflowSteps(
    workflowId: number,
    stepIds: number[],
) {
    return apiFetch<{ data: ApprovalWorkflowStepDto[] }>(
        `/api/approval-workflows/${workflowId}/steps/reorder`,
        {
            method: 'PUT',
            body: JSON.stringify({ step_ids: stepIds }),
        },
    );
}
