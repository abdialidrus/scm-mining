<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatQty } from '@/lib/format';
import {
    getGoodsReceipt,
    type GoodsReceiptDto,
} from '@/services/goodsReceiptApi';
import {
    getGoodsReceiptPutAwaySummary,
    listEligibleGoodsReceiptsForPutAway,
    type EligibleGoodsReceiptDto,
    type GoodsReceiptPutAwayLineSummaryDto,
} from '@/services/goodsReceiptEligibleApi';
import { apiFetch } from '@/services/http';
import { fetchWarehouses, type WarehouseDto } from '@/services/masterDataApi';
import { createPutAway } from '@/services/putAwayApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps<{ putAwayId: number | null }>();

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});

const warehouses = ref<WarehouseDto[]>([]);

type WarehouseLocationDto = {
    id: number;
    warehouse_id: number;
    type: string;
    code: string;
    name: string;
    is_active: boolean;
};

type SelectOption = {
    value: number;
    label: string;
    gr: EligibleGoodsReceiptDto;
};

const eligibleLoading = ref(false);
const eligibleError = ref<string | null>(null);
const eligibleGrOptions = ref<SelectOption[]>([]);
const eligibleSearch = ref('');
const selectedGrOption = ref<SelectOption | null>(null);

const form = reactive({
    goods_receipt_id: null as number | null,
    put_away_at: '' as string,
    remarks: '' as string,
    lines: [] as Array<{
        goods_receipt_line_id: number;
        destination_location_id: number | null;
        qty: number;
        remarks: string;
    }>,
});

const gr = ref<GoodsReceiptDto | null>(null);
const storageLocations = ref<WarehouseLocationDto[]>([]);
const putAwaySummaryByLineId = ref<
    Record<number, GoodsReceiptPutAwayLineSummaryDto>
>({});

const isEdit = computed(() => props.putAwayId !== null);

function setApiError(e: any, fallback: string) {
    error.value = e?.payload?.message ?? e?.message ?? fallback;
    fieldErrors.value = (e?.payload?.errors ?? {}) as Record<string, string[]>;
}

function lineError(idx: number, field: 'qty' | 'destination_location_id') {
    const key = `lines.${idx}.${field}`;
    return fieldErrors.value[key] ?? null;
}

async function loadWarehouses() {
    const res = await fetchWarehouses();
    warehouses.value = res.data.filter((w: WarehouseDto) => w.is_active);
}

async function loadStorageLocations(warehouseId: number) {
    const res = await apiFetch<{ data: WarehouseLocationDto[] }>(
        `/api/warehouse-locations?warehouse_id=${warehouseId}&type=STORAGE&is_active=1`,
    );
    storageLocations.value = (res.data ?? []).filter((l) => l.is_active);
}

function formatEligibleGrLabel(dto: EligibleGoodsReceiptDto) {
    const wh = dto.warehouse?.code ?? dto.warehouse_id;
    const po = dto.purchaseOrder?.po_number ?? dto.purchase_order_id;
    const remaining = dto.remaining_total;

    const remainingText =
        remaining === null || remaining === undefined
            ? ''
            : ` — Remaining ${formatQty(remaining)}`;

    return `${dto.gr_number} — ${dto.status}${remainingText} — PO ${po} — WH ${wh}`;
}

async function loadEligibleGrs() {
    eligibleLoading.value = true;
    eligibleError.value = null;

    try {
        const res = await listEligibleGoodsReceiptsForPutAway({
            search: eligibleSearch.value || undefined,
            warehouse_id: selectedWarehouse.value.value || undefined,
            page: 1,
            per_page: 20,
        });

        const paginated = (res as any).data;
        const rows = (paginated?.data ?? []) as EligibleGoodsReceiptDto[];
        eligibleGrOptions.value = rows.map((r) => ({
            value: r.id,
            label: formatEligibleGrLabel(r),
            gr: r,
        }));
    } catch (e: any) {
        eligibleError.value = e?.message ?? 'Failed to load eligible GRs';
    } finally {
        eligibleLoading.value = false;
    }
}

async function loadDefaultStorageLocationId(
    warehouseId: number,
): Promise<number | null> {
    const res = await apiFetch<{ data: WarehouseLocationDto[] }>(
        `/api/warehouse-locations?warehouse_id=${warehouseId}&type=STORAGE&is_active=1&only_default=1`,
    );
    const first = (res.data ?? [])[0];
    return first?.id ?? null;
}

async function loadGoodsReceipt(grId: number) {
    const res = await getGoodsReceipt(grId);
    gr.value = res.data;

    // Load put-away summary to compute remaining per GR line.
    const summaryRes = await getGoodsReceiptPutAwaySummary(grId);
    const summaryRows = summaryRes.data ?? [];
    const map: Record<number, GoodsReceiptPutAwayLineSummaryDto> = {};
    for (const r of summaryRows) {
        map[r.goods_receipt_line_id] = r;
    }
    putAwaySummaryByLineId.value = map;

    let defaultStorageId: number | null = null;
    if (gr.value.warehouse_id) {
        await loadStorageLocations(gr.value.warehouse_id);
        defaultStorageId = await loadDefaultStorageLocationId(
            gr.value.warehouse_id,
        );
    }

    // Initialize lines from GR (default: remaining qty)
    // Only include lines with remaining > 0.
    form.lines = (gr.value.lines ?? [])
        .map((l) => {
            const s = putAwaySummaryByLineId.value[l.id];
            const remaining = Number(
                s?.remaining_qty ?? l.received_quantity ?? 0,
            );
            return {
                goods_receipt_line_id: l.id,
                destination_location_id: defaultStorageId,
                qty: Math.max(0, remaining),
                remarks: '',
                __remaining: remaining,
            };
        })
        .filter((x) => Number(x.__remaining) > 0)
        .map(({ __remaining, ...x }) => x);
}

async function onSelectGoodsReceipt(opt: SelectOption | null) {
    selectedGrOption.value = opt;

    const id = opt?.value ?? null;
    form.goods_receipt_id = id;
    gr.value = null;
    form.lines = [];

    if (!id) return;

    await loadGoodsReceipt(id);
}

async function load() {
    loading.value = true;
    error.value = null;

    try {
        if (isEdit.value) {
            throw new Error('Edit Put Away is not supported in MVP');
        }

        await loadWarehouses();
        form.put_away_at = new Date().toISOString();

        // initial eligible list (so dropdown not empty)
        await loadEligibleGrs();

        // prefill from querystring (GR detail CTA)
        const qs = new URLSearchParams(window.location.search);
        const grId = Number(qs.get('goods_receipt_id'));
        if (Number.isFinite(grId) && grId > 0) {
            // If it exists in current options, select it; otherwise load details directly.
            const found = eligibleGrOptions.value.find((o) => o.value === grId);
            if (found) {
                await onSelectGoodsReceipt(found);
            } else {
                await onSelectGoodsReceipt({
                    value: grId,
                    label: `GR #${grId}`,
                    gr: {
                        id: grId,
                        gr_number: `GR #${grId}`,
                        status: '',
                        purchase_order_id: 0,
                        warehouse_id: 0,
                    },
                });
            }
        }
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load form';
    } finally {
        loading.value = false;
    }
}

async function save() {
    if (!form.goods_receipt_id) {
        error.value = 'Please select a Goods Receipt';
        return;
    }

    saving.value = true;
    error.value = null;
    fieldErrors.value = {};

    try {
        const payload = {
            goods_receipt_id: form.goods_receipt_id,
            put_away_at: form.put_away_at || null,
            remarks: form.remarks || null,
            lines: form.lines.map((l) => ({
                goods_receipt_line_id: l.goods_receipt_line_id,
                destination_location_id: l.destination_location_id as number,
                qty: l.qty,
                remarks: l.remarks || null,
            })),
        };

        const res = await createPutAway(payload);
        router.visit(`/put-aways/${res.data.id}`);
    } catch (e: any) {
        setApiError(e, 'Failed to create put away');
    } finally {
        saving.value = false;
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Put Aways', href: '/put-aways' },
    { title: 'Create', href: '#' },
];

onMounted(load);

type WarehouseOption = { value: number; label: string };
const selectedWarehouse = ref<WarehouseOption>({
    value: 0,
    label: 'All Warehouses',
});

const warehouseOptions = computed<WarehouseOption[]>(() => {
    const opts = warehouses.value.map((w) => ({
        value: w.id,
        label: `${w.code} — ${w.name}`,
    }));
    return [{ value: 0, label: 'All Warehouses' }, ...opts];
});

function onChangeWarehouseFilter() {
    selectedGrOption.value = null;
    form.goods_receipt_id = null;
    gr.value = null;
    form.lines = [];
    loadEligibleGrs();
}
</script>

<template>
    <Head title="Create Put Away" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Create Put Away</h1>
                <Button variant="outline" as-child>
                    <Link href="/put-aways">Back</Link>
                </Button>
            </div>

            <div
                v-if="error"
                class="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm"
            >
                {{ error }}
            </div>

            <div v-if="loading" class="mt-6 text-sm text-muted-foreground">
                Loading...
            </div>

            <div v-else class="mt-6 max-w-3xl space-y-4">
                <div class="rounded-lg border p-4">
                    <div class="grid gap-3 md:grid-cols-12">
                        <div class="md:col-span-3">
                            <label class="text-sm font-medium"
                                >Warehouse filter</label
                            >
                            <div class="mt-1">
                                <select
                                    v-model.number="selectedWarehouse.value"
                                    class="h-10 w-full rounded-md border bg-background px-2 text-sm"
                                    @change="onChangeWarehouseFilter"
                                >
                                    <option
                                        v-for="w in warehouseOptions"
                                        :key="w.value"
                                        :value="w.value"
                                    >
                                        {{ w.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="md:col-span-6">
                            <label class="text-sm font-medium"
                                >Goods Receipt</label
                            >
                            <div class="mt-1">
                                <Multiselect
                                    v-model="selectedGrOption"
                                    :options="eligibleGrOptions"
                                    track-by="value"
                                    label="label"
                                    placeholder="Select Goods Receipt"
                                    :loading="eligibleLoading"
                                    :internal-search="false"
                                    @search-change="
                                        (q: string) => {
                                            eligibleSearch = q;
                                            loadEligibleGrs();
                                        }
                                    "
                                    @input="onSelectGoodsReceipt"
                                    class="w-full"
                                />
                            </div>
                            <p
                                v-if="eligibleError"
                                class="mt-1 text-xs text-destructive"
                            >
                                {{ eligibleError }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Eligible: GR status POSTED / PUT_AWAY_PARTIAL.
                            </p>
                        </div>

                        <div class="md:col-span-3">
                            <label class="text-sm font-medium"
                                >Put Away At</label
                            >
                            <Input
                                v-model="form.put_away_at"
                                placeholder="ISO datetime"
                            />
                        </div>

                        <div class="md:col-span-12">
                            <label class="text-sm font-medium">Remarks</label>
                            <Input v-model="form.remarks" placeholder="notes" />
                        </div>
                    </div>
                </div>

                <div v-if="gr" class="rounded-lg border p-4">
                    <div class="text-sm font-semibold">Goods Receipt</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ gr.gr_number }} — status {{ gr.status }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Warehouse: {{ gr.warehouse?.code ?? gr.warehouse_id }}
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <h2 class="text-sm font-semibold">Lines</h2>

                    <div v-if="!gr" class="mt-2 text-sm text-muted-foreground">
                        Select a Goods Receipt first.
                    </div>

                    <div v-else class="mt-3 space-y-3">
                        <div
                            v-for="(l, idx) in form.lines"
                            :key="l.goods_receipt_line_id"
                            class="grid gap-2 rounded-md border p-3 md:grid-cols-12"
                        >
                            <div class="md:col-span-6">
                                <div class="text-sm font-medium">
                                    {{
                                        (gr.lines ?? []).find(
                                            (x) =>
                                                x.id ===
                                                l.goods_receipt_line_id,
                                        )?.item?.sku ?? 'Item'
                                    }}
                                    —
                                    {{
                                        (gr.lines ?? []).find(
                                            (x) =>
                                                x.id ===
                                                l.goods_receipt_line_id,
                                        )?.item?.name ?? ''
                                    }}
                                </div>

                                <div class="mt-1 text-xs text-muted-foreground">
                                    <div>
                                        Received:
                                        {{
                                            formatQty(
                                                (gr.lines ?? []).find(
                                                    (x) =>
                                                        x.id ===
                                                        l.goods_receipt_line_id,
                                                )?.received_quantity,
                                            )
                                        }}
                                        {{
                                            (gr.lines ?? []).find(
                                                (x) =>
                                                    x.id ===
                                                    l.goods_receipt_line_id,
                                            )?.uom?.code ?? ''
                                        }}
                                    </div>
                                    <div>
                                        Already put away:
                                        {{
                                            formatQty(
                                                putAwaySummaryByLineId[
                                                    l.goods_receipt_line_id
                                                ]?.put_away_qty ?? 0,
                                            )
                                        }}
                                        — Remaining:
                                        {{
                                            formatQty(
                                                putAwaySummaryByLineId[
                                                    l.goods_receipt_line_id
                                                ]?.remaining_qty ?? 0,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>

                            <div class="md:col-span-3">
                                <label class="text-sm font-medium">
                                    Destination (STORAGE)
                                </label>
                                <select
                                    v-model.number="l.destination_location_id"
                                    class="mt-1 h-10 w-full rounded-md border bg-background px-2 text-sm"
                                >
                                    <option :value="null">
                                        Select location
                                    </option>
                                    <option
                                        v-for="loc in storageLocations"
                                        :key="loc.id"
                                        :value="loc.id"
                                    >
                                        {{ loc.code }} — {{ loc.name }}
                                    </option>
                                </select>
                                <div
                                    v-if="
                                        lineError(
                                            idx,
                                            'destination_location_id',
                                        )
                                    "
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{
                                        lineError(
                                            idx,
                                            'destination_location_id',
                                        )?.join(', ')
                                    }}
                                </div>
                                <p
                                    v-if="storageLocations.length === 0"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    No STORAGE locations found for this
                                    warehouse.
                                </p>
                            </div>

                            <div class="md:col-span-3">
                                <label class="text-sm font-medium">Qty</label>
                                <Input
                                    v-model.number="l.qty"
                                    type="number"
                                    step="0.0001"
                                    placeholder="0"
                                    :max="
                                        putAwaySummaryByLineId[
                                            l.goods_receipt_line_id
                                        ]?.remaining_qty ?? undefined
                                    "
                                />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Max:
                                    {{
                                        formatQty(
                                            putAwaySummaryByLineId[
                                                l.goods_receipt_line_id
                                            ]?.remaining_qty ?? 0,
                                        )
                                    }}
                                </p>
                                <div
                                    v-if="lineError(idx, 'qty')"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ lineError(idx, 'qty')?.join(', ') }}
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="(form.lines ?? []).length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No lines.
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <Button type="button" :disabled="saving" @click="save">
                        {{ saving ? 'Saving...' : 'Create Put Away Draft' }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
