import { apiFetch } from '@/services/http';

export type ItemSerialNumberDto = {
    id: number;
    item_id: number;
    serial_number: string;
    status: string;
    current_location_id: number | null;
    received_at?: string | null;
    goods_receipt_line_id?: number | null;
    picked_at?: string | null;
    picking_order_line_id?: number | null;
    remarks?: string | null;
    item?: { id: number; sku: string; name: string } | null;
    currentLocation?: {
        id: number;
        code: string;
        name: string;
        type: string;
    } | null;
};

/**
 * Get available serial numbers for picking.
 */
export async function getAvailableSerialNumbers(params: {
    item_id: number;
    location_id?: number;
    status?: string;
}) {
    const qs = new URLSearchParams();
    qs.set('item_id', String(params.item_id));
    if (params.location_id) qs.set('location_id', String(params.location_id));
    if (params.status) qs.set('status', params.status);

    return apiFetch<{ data: ItemSerialNumberDto[] }>(
        `/api/stock/serial-numbers?${qs.toString()}`,
    );
}

/**
 * Get serial number details.
 */
export async function getSerialNumber(serialNumber: string) {
    return apiFetch<{ data: ItemSerialNumberDto }>(
        `/api/stock/serial-numbers/${serialNumber}`,
    );
}
