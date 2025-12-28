import { apiFetch } from '@/services/http';

export type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: unknown;
};

export type StockByLocationDto = {
    item_id: number;
    sku: string;
    item_name: string;
    location_id: number;
    location_code: string;
    location_name: string;
    location_type: string;
    warehouse_id: number;
    warehouse_code: string;
    warehouse_name: string;
    qty_on_hand: number;
    uom_code: string | null;
    uom_name: string | null;
};

export type StockSummaryByItemDto = {
    item_id: number;
    sku: string;
    name: string;
    qty_on_hand: number;
    uom_code: string | null;
    uom_name: string | null;
    locations_count: number;
};

export type StockMovementDto = {
    id: number;
    item_id: number;
    uom_id: number | null;
    source_location_id: number | null;
    destination_location_id: number | null;
    qty: number;
    reference_type: string;
    reference_id: number;
    created_by: number | null;
    movement_at: string;
    meta: Record<string, any> | null;
    item?: { id: number; sku: string; name: string } | null;
    uom?: { id: number; code: string; name: string } | null;
    sourceLocation?: {
        id: number;
        warehouse_id: number;
        type: string;
        code: string;
        name: string;
    } | null;
    destinationLocation?: {
        id: number;
        warehouse_id: number;
        type: string;
        code: string;
        name: string;
    } | null;
    creator?: { id: number; name: string; email: string } | null;
};

export type ItemLocationBreakdownDto = {
    location_id: number;
    location_code: string;
    location_name: string;
    location_type: string;
    warehouse_id: number;
    warehouse_code: string;
    warehouse_name: string;
    qty_on_hand: number;
};

export async function getStockByLocation(params?: {
    warehouse_id?: number;
    item_id?: number;
    search?: string;
    location_type?: string;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.warehouse_id)
        qs.set('warehouse_id', String(params.warehouse_id));
    if (params?.item_id) qs.set('item_id', String(params.item_id));
    if (params?.search) qs.set('search', params.search);
    if (params?.location_type) qs.set('location_type', params.location_type);
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{
        data: {
            items: StockByLocationDto[];
            meta: any;
            links: any;
        };
    }>(`/api/stock-reports/by-location${suffix}`);
}

export async function getStockSummaryByItem(params?: {
    warehouse_id?: number;
    search?: string;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.warehouse_id)
        qs.set('warehouse_id', String(params.warehouse_id));
    if (params?.search) qs.set('search', params.search);
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{
        data: {
            items: StockSummaryByItemDto[];
            meta: any;
            links: any;
        };
    }>(`/api/stock-reports/by-item${suffix}`);
}

export async function getStockMovements(params?: {
    item_id?: number;
    warehouse_id?: number;
    location_id?: number;
    reference_type?: string;
    date_from?: string;
    date_to?: string;
    page?: number;
    per_page?: number;
}) {
    const qs = new URLSearchParams();
    if (params?.item_id) qs.set('item_id', String(params.item_id));
    if (params?.warehouse_id)
        qs.set('warehouse_id', String(params.warehouse_id));
    if (params?.location_id) qs.set('location_id', String(params.location_id));
    if (params?.reference_type) qs.set('reference_type', params.reference_type);
    if (params?.date_from) qs.set('date_from', params.date_from);
    if (params?.date_to) qs.set('date_to', params.date_to);
    if (params?.page) qs.set('page', String(params.page));
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{ data: Paginated<StockMovementDto> }>(
        `/api/stock-reports/movements${suffix}`,
    );
}

export async function getItemLocationBreakdown(
    itemId: number,
    warehouseId?: number,
) {
    const qs = new URLSearchParams();
    if (warehouseId) qs.set('warehouse_id', String(warehouseId));
    const suffix = qs.toString() ? `?${qs.toString()}` : '';

    return apiFetch<{
        data: {
            item: {
                id: number;
                sku: string;
                name: string;
                uom_code: string | null;
                uom_name: string | null;
            };
            locations: ItemLocationBreakdownDto[];
            total_qty: number;
        };
    }>(`/api/stock-reports/items/${itemId}/locations${suffix}`);
}
