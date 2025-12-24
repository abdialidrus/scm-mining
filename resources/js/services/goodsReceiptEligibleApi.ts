import { apiFetch } from '@/services/http';

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: unknown;
};

export type EligibleGoodsReceiptDto = {
    id: number;
    gr_number: string;
    status: string;
    purchase_order_id: number;
    purchaseOrder?: { id: number; po_number: string } | null;
    warehouse_id: number;
    warehouse?: { id: number; code: string; name: string } | null;
    posted_at?: string | null;

    // computed by backend (eligible-for-put-away)
    received_total?: number;
    put_away_total?: number;
    remaining_total?: number;
};

export type GoodsReceiptPutAwayLineSummaryDto = {
    goods_receipt_line_id: number;
    received_qty: number;
    put_away_qty: number;
    remaining_qty: number;
};

export async function listEligibleGoodsReceiptsForPutAway(params?: {
    search?: string;
    warehouse_id?: number;
    only_with_remaining?: boolean;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.search) qs.set('search', params.search);
    if (params?.warehouse_id)
        qs.set('warehouse_id', String(params.warehouse_id));

    // default: true (backend also defaults true)
    if (params?.only_with_remaining === false) {
        qs.set('only_with_remaining', '0');
    } else {
        qs.set('only_with_remaining', '1');
    }

    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<EligibleGoodsReceiptDto> }>(
        `/api/goods-receipts/eligible-for-put-away${suffix}`,
    );
}

export async function getGoodsReceiptPutAwaySummary(goodsReceiptId: number) {
    return apiFetch<{ data: GoodsReceiptPutAwayLineSummaryDto[] }>(
        `/api/goods-receipts/${goodsReceiptId}/put-away-summary`,
    );
}
