<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import StatusHistoryTable from '@/components/StatusHistoryTable.vue';
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatQty } from '@/lib/format';
import {
    cancelGoodsReceipt,
    getGoodsReceipt,
    postGoodsReceipt,
    type GoodsReceiptDto,
} from '@/services/goodsReceiptApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ goodsReceiptId: number }>();

const loading = ref(true);
const acting = ref(false);
const error = ref<string | null>(null);
const gr = ref<GoodsReceiptDto | null>(null);

const title = computed(() => gr.value?.gr_number ?? 'Goods Receipt');

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await getGoodsReceipt(props.goodsReceiptId);
        gr.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load goods receipt';
    } finally {
        loading.value = false;
    }
}

async function post() {
    if (!gr.value) return;
    if (!confirm(`Post GR ${gr.value.gr_number}?`)) return;

    acting.value = true;
    error.value = null;

    try {
        const res = await postGoodsReceipt(gr.value.id);
        gr.value = res.data;
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to post GR';
    } finally {
        acting.value = false;
    }
}

async function cancel() {
    if (!gr.value) return;
    if (!confirm(`Cancel GR ${gr.value.gr_number}?`)) return;

    acting.value = true;
    error.value = null;

    try {
        const res = await cancelGoodsReceipt(gr.value.id, null);
        gr.value = res.data;
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to cancel GR';
    } finally {
        acting.value = false;
    }
}

function formatDateTime(value?: string | null) {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return new Intl.DateTimeFormat('id-ID', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Goods Receipts',
        href: '/goods-receipts',
    },
    {
        title: 'Goods Receipt Details',
        href: '#',
    },
];

onMounted(load);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">
                        {{ gr?.gr_number ?? 'Goods Receipt' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Status: {{ gr?.status ?? '-' }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/goods-receipts">Back</Link>
                    </Button>

                    <Button
                        v-if="
                            gr?.status === 'POSTED' ||
                            gr?.status === 'PUT_AWAY_PARTIAL'
                        "
                        variant="outline"
                        type="button"
                        @click="
                            router.visit(
                                `/put-aways/create?goods_receipt_id=${gr?.id}`,
                            )
                        "
                    >
                        Put Away
                    </Button>

                    <Button
                        v-if="gr?.status === 'DRAFT'"
                        variant="outline"
                        as-child
                    >
                        <Link :href="`/goods-receipts/${gr.id}/edit`">
                            Edit
                        </Link>
                    </Button>

                    <Button
                        v-if="gr?.status === 'DRAFT'"
                        :disabled="acting"
                        type="button"
                        @click="post"
                    >
                        {{ acting ? 'Working' : 'Post' }}
                    </Button>

                    <Button
                        v-if="gr?.status === 'DRAFT'"
                        variant="destructive"
                        :disabled="acting"
                        type="button"
                        @click="cancel"
                    >
                        {{ acting ? 'Working' : 'Cancel' }}
                    </Button>
                </div>
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

            <div v-else-if="gr" class="mt-6 space-y-6">
                <div class="rounded-lg border p-4">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <div class="text-sm font-semibold">
                                    Goods Receipt
                                </div>
                                <StatusBadge :status="gr.status" />
                            </div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                GR No:
                                <span class="font-medium text-foreground">{{
                                    gr.gr_number
                                }}</span>
                            </div>
                        </div>

                        <div class="text-right text-xs text-muted-foreground">
                            <div>
                                Received at:
                                <span class="text-foreground">{{
                                    formatDateTime(gr.received_at)
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-12">
                        <div class="md:col-span-6">
                            <div class="text-xs text-muted-foreground">
                                Purchase Order
                            </div>
                            <div class="mt-1 text-sm">
                                <Link
                                    v-if="gr.purchase_order_id"
                                    :href="`/purchase-orders/${gr.purchase_order_id}`"
                                    class="font-medium hover:underline"
                                >
                                    {{
                                        gr.purchase_order?.po_number ??
                                        `PO #${gr.purchase_order_id}`
                                    }}
                                </Link>
                                <span v-else class="text-muted-foreground"
                                    >-</span
                                >
                            </div>
                        </div>

                        <div class="md:col-span-6">
                            <div class="text-xs text-muted-foreground">
                                Warehouse
                            </div>
                            <div class="mt-1 text-sm">
                                <Link
                                    v-if="gr.warehouse_id"
                                    :href="`/master-data/warehouses/${gr.warehouse_id}`"
                                    class="font-medium hover:underline"
                                >
                                    {{
                                        gr.warehouse
                                            ? `${gr.warehouse.code} — ${gr.warehouse.name}`
                                            : `Warehouse #${gr.warehouse_id}`
                                    }}
                                </Link>
                                <span v-else class="text-muted-foreground"
                                    >-</span
                                >
                            </div>
                        </div>

                        <div class="md:col-span-12">
                            <div class="text-xs text-muted-foreground">
                                Remarks
                            </div>
                            <div class="mt-1 text-sm">
                                {{ gr.remarks || '-' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <h2 class="text-sm font-semibold">Lines</h2>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="l in gr.lines ?? []"
                            :key="l.id"
                            class="flex flex-col gap-2 rounded-md border px-3 py-2"
                            :class="[
                                l.item?.is_serialized
                                    ? 'border-blue-200 bg-blue-50/30 dark:border-blue-800 dark:bg-blue-950/20'
                                    : '',
                            ]"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <div class="text-sm font-medium">
                                            {{ l.item?.sku ?? l.item_id }} —
                                            {{ l.item?.name ?? '' }}
                                        </div>
                                        <span
                                            v-if="l.item?.is_serialized"
                                            class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/50 dark:text-blue-200"
                                        >
                                            Serialized
                                        </span>
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        Ordered:
                                        {{ formatQty(l.ordered_quantity) }} —
                                        Received:
                                        {{ formatQty(l.received_quantity) }}
                                        {{ l.uom?.code ?? '' }}
                                    </div>
                                    <div
                                        v-if="l.remarks"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Notes: {{ l.remarks }}
                                    </div>
                                </div>
                            </div>

                            <!-- Serial Numbers Display -->
                            <div
                                v-if="
                                    l.item?.is_serialized &&
                                    l.serial_numbers &&
                                    l.serial_numbers.length > 0
                                "
                                class="mt-2 border-t pt-2"
                            >
                                <div
                                    class="mb-2 text-xs font-medium text-muted-foreground"
                                >
                                    Serial Numbers ({{
                                        l.serial_numbers.length
                                    }})
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <div
                                        v-for="serial in l.serial_numbers"
                                        :key="serial.id"
                                        class="inline-flex items-center rounded-md border border-primary/20 bg-primary/10 px-2.5 py-1 font-mono text-xs text-primary"
                                    >
                                        {{ serial.serial_number }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="(gr.lines ?? []).length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No lines.
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <h2 class="text-sm font-semibold">History</h2>
                    <div class="mt-3">
                        <StatusHistoryTable
                            :rows="gr.status_histories ?? []"
                            :format-date-time="formatDateTime"
                            empty-text="No history."
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
