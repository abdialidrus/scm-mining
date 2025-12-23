<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { createGoodsReceipt } from '@/services/goodsReceiptApi';
import { apiFetch } from '@/services/http';
import { fetchWarehouses, type WarehouseDto } from '@/services/masterDataApi';
import {
    listPurchaseOrders,
    PurchaseOrderDto,
} from '@/services/purchaseOrderApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps<{ goodsReceiptId: number | null }>();

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});

const warehouses = ref<WarehouseDto[]>([]);

type PoLine = {
    id: number;
    line_no: number;
    item_id: number;
    quantity: number;
    uom_id: number;
    item?: { id: number; sku: string; name: string } | null;
    uom?: { id: number; code: string; name: string } | null;
};

type PurchaseOrderDtoLite = {
    id: number;
    po_number: string;
    status: string;
    supplier?: { id: number; code: string; name: string } | null;
    lines?: PoLine[];
};

const form = reactive({
    purchase_order_id: null as number | null,
    warehouse_id: null as number | null,
    remarks: '' as string,
    lines: [] as Array<{
        purchase_order_line_id: number;
        received_quantity: number;
        remarks: string;
    }>,
});

const po = ref<PurchaseOrderDtoLite | null>(null);
const pos = ref<PurchaseOrderDto[]>([]);

const isEdit = computed(() => props.goodsReceiptId !== null);

function setApiError(e: any, fallback: string) {
    error.value = e?.payload?.message ?? e?.message ?? fallback;
    fieldErrors.value = (e?.payload?.errors ?? {}) as Record<string, string[]>;
}

function lineError(
    idx: number,
    field: 'purchase_order_line_id' | 'received_quantity' | 'remarks',
) {
    const key = `lines.${idx}.${field}`;
    return fieldErrors.value[key] ?? null;
}

async function loadWarehouses() {
    const res = await fetchWarehouses();
    warehouses.value = res.data.filter((w) => w.is_active);
}

async function loadPo(poId: number) {
    const res = await apiFetch<{ data: PurchaseOrderDtoLite }>(
        `/api/purchase-orders/${poId}`,
    );
    po.value = res.data;

    // initialize receipt lines for each PO line with 0 qty
    form.lines = (po.value.lines ?? []).map((l) => ({
        purchase_order_line_id: l.id,
        received_quantity: 0,
        remarks: '',
    }));
}

async function loadPOs() {
    const res = await listPurchaseOrders({
        search: undefined,
        status: 'APPROVED',
        page: 1,
    });
    const paginated = (res as any).data;
    pos.value = (paginated?.data ?? []) as PurchaseOrderDto[];
}

async function load() {
    loading.value = true;
    error.value = null;

    try {
        if (isEdit.value) {
            // MVP: create-only page
            throw new Error('Edit GR is not supported in MVP');
        }
        await Promise.all([loadWarehouses(), loadPOs()]);
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load form';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = null;
    fieldErrors.value = {};

    try {
        if (!form.purchase_order_id) {
            throw new Error('Purchase Order is required');
        }
        if (!form.warehouse_id) {
            throw new Error('Warehouse is required');
        }

        const payload = {
            purchase_order_id: form.purchase_order_id,
            warehouse_id: form.warehouse_id,
            remarks: form.remarks || null,
            lines: form.lines
                .filter((l) => Number(l.received_quantity) > 0)
                .map((l) => ({
                    purchase_order_line_id: l.purchase_order_line_id,
                    received_quantity: Number(l.received_quantity),
                    remarks: l.remarks || null,
                })),
        };

        const res = await createGoodsReceipt(payload);
        router.visit(`/goods-receipts/${res.data.id}`);
    } catch (e: any) {
        setApiError(e, 'Failed to create goods receipt');
    } finally {
        saving.value = false;
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Goods Receipts',
        href: '/goods-receipts',
    },
    {
        title: 'Create',
        href: '#',
    },
];

onMounted(load);
</script>

<template>
    <Head title="Create Goods Receipt" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Create Goods Receipt</h1>
                <Button variant="outline" as-child>
                    <Link href="/goods-receipts">Back</Link>
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

            <div v-else class="mt-6 space-y-6">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-sm font-medium"
                            >Purchase Order ID</label
                        >
                        <select
                            v-model.number="form.purchase_order_id"
                            class="mt-1 w-full rounded-md border bg-background px-2 py-2"
                            @change="
                                form.purchase_order_id &&
                                loadPo(form.purchase_order_id)
                            "
                        >
                            <option :value="null">Select Purchase Order</option>
                            <option
                                v-for="po in pos"
                                :key="po.id"
                                :value="po.id"
                            >
                                {{ po.po_number }}
                            </option>
                        </select>
                        <!-- <Input
                        v-model.number="form.purchase_order_id"
                        placeholder="Enter PO id"
                        type="number"
                        @change="
                            form.purchase_order_id > 0 &&
                            loadPo(form.purchase_order_id)
                        "
                    />
                    <p class="mt-1 text-xs text-muted-foreground">
                        MVP: input PO ID manually.
                    </p> -->
                    </div>

                    <div>
                        <label class="text-sm font-medium">Warehouse</label>
                        <select
                            v-model.number="form.warehouse_id"
                            class="mt-1 w-full rounded-md border bg-background px-2 py-2"
                        >
                            <option :value="null">Select warehouse</option>
                            <option
                                v-for="w in warehouses"
                                :key="w.id"
                                :value="w.id"
                            >
                                {{ w.code }} — {{ w.name }}
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-3">
                        <label class="text-sm font-medium">Remarks</label>
                        <textarea
                            v-model="form.remarks"
                            rows="2"
                            class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <h2 class="text-sm font-semibold">Lines</h2>

                    <div v-if="!po" class="mt-2 text-sm text-muted-foreground">
                        Load a PO first.
                    </div>

                    <div v-else class="mt-2 space-y-4">
                        <div class="text-sm text-muted-foreground">
                            PO: {{ po.po_number }} ({{ po.status }})
                        </div>

                        <div
                            v-for="(l, idx) in form.lines"
                            :key="l.purchase_order_line_id"
                            class="grid gap-2 rounded-md border p-3 md:grid-cols-12"
                        >
                            <div class="md:col-span-5">
                                <div class="text-sm font-medium">
                                    {{
                                        (po.lines ?? []).find(
                                            (x) =>
                                                x.id ===
                                                l.purchase_order_line_id,
                                        )?.item?.sku ?? 'Item'
                                    }}
                                    —
                                    {{
                                        (po.lines ?? []).find(
                                            (x) =>
                                                x.id ===
                                                l.purchase_order_line_id,
                                        )?.item?.name ?? ''
                                    }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    Ordered:
                                    {{
                                        (po.lines ?? []).find(
                                            (x) =>
                                                x.id ===
                                                l.purchase_order_line_id,
                                        )?.quantity ?? 0
                                    }}
                                    {{
                                        (po.lines ?? []).find(
                                            (x) =>
                                                x.id ===
                                                l.purchase_order_line_id,
                                        )?.uom?.code ?? ''
                                    }}
                                </div>
                            </div>

                            <div class="md:col-span-3">
                                <label class="text-xs font-medium"
                                    >Receive Qty</label
                                >
                                <Input
                                    v-model.number="l.received_quantity"
                                    type="number"
                                />
                                <div
                                    v-if="lineError(idx, 'received_quantity')"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{
                                        lineError(
                                            idx,
                                            'received_quantity',
                                        )!.join(', ')
                                    }}
                                </div>
                            </div>

                            <div class="md:col-span-4">
                                <label class="text-xs font-medium"
                                    >Remarks</label
                                >
                                <Input v-model="l.remarks" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button :disabled="saving" type="button" @click="save">
                        {{ saving ? 'Saving...' : 'Create' }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
