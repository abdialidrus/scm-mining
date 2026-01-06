import { apiFetch } from '@/services/http';

export type InvoiceDto = {
    id: number;
    internal_number: string;
    invoice_number: string;
    invoice_date: string;
    due_date: string;
    status: string;
    matching_status: string;
    payment_status: string;
    supplier_id: number;
    supplier?: {
        id: number;
        code: string;
        name: string;
    };
    purchase_order_id: number;
    purchase_order?: {
        id: number;
        po_number: string;
    };
    subtotal_amount: number;
    tax_amount: number;
    total_amount: number;
    paid_amount: number;
    remaining_amount: number;
    is_overdue: boolean;
    is_editable: boolean;
    created_at?: string;
    updated_at?: string;
};

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: any;
};

export async function listInvoices(params?: {
    search?: string;
    status?: string;
    matching_status?: string;
    payment_status?: string;
    supplier_id?: number;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.status) qs.set('status', params.status);
    if (params?.matching_status)
        qs.set('matching_status', params.matching_status);
    if (params?.payment_status) qs.set('payment_status', params.payment_status);
    if (params?.supplier_id)
        qs.set('supplier_id', params.supplier_id.toString());
    if (params?.page) qs.set('page', params.page.toString());
    if (params?.per_page) qs.set('per_page', params.per_page.toString());

    const url = `/api/accounting/invoices?${qs.toString()}`;
    return apiFetch(url);
}

export async function getInvoice(id: number) {
    return apiFetch(`/api/accounting/invoices/${id}`);
}

export async function deleteInvoice(id: number) {
    return apiFetch(`/api/accounting/invoices/${id}`, { method: 'DELETE' });
}

export type Supplier = {
    id: number;
    code: string;
    name: string;
};

export type PurchaseOrder = {
    id: number;
    po_number: string;
    supplier_id: number;
    supplier?: {
        id: number;
        name: string;
    };
    lines?: PurchaseOrderLine[];
};

export type PurchaseOrderLine = {
    id: number;
    item_id: number;
    uom_id: number;
    quantity: number;
    unit_price: number;
    item: {
        id: number;
        code: string;
        name: string;
    };
    uom: {
        id: number;
        code: string;
    };
    goods_receipt_lines?: GoodsReceiptLine[];
};

export type GoodsReceiptLine = {
    id: number;
    received_qty: number;
    goods_receipt: {
        id: number;
        status: string;
    };
};

export type CreateInvoiceData = {
    purchase_order_id: number;
    supplier_id: number;
    invoice_number: string;
    invoice_date: string;
    due_date: string;
    tax_invoice_number?: string;
    tax_invoice_date?: string;
    subtotal: number;
    tax_amount: number;
    discount_amount: number;
    other_charges: number;
    total_amount: number;
    notes?: string;
    delivery_note_number?: string;
    currency: string;
    exchange_rate: number;
    status: string;
    lines: InvoiceLine[];
};

export type InvoiceLine = {
    item_id: number;
    uom_id: number;
    purchase_order_line_id: number;
    goods_receipt_line_id?: number;
    description: string;
    invoiced_qty: number;
    unit_price: number;
    line_total: number;
    tax_amount: number;
    discount_amount: number;
    notes?: string;
};

export async function getCreateData() {
    return apiFetch('/api/accounting/invoices/create-data');
}

export async function getPurchaseOrderDetails(poId: number) {
    return apiFetch(`/api/accounting/invoices/purchase-orders/${poId}`);
}

export async function createInvoice(data: CreateInvoiceData) {
    return apiFetch('/api/accounting/invoices', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}
