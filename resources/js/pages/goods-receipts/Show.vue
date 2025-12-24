<script setup lang="ts">
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
import { Head, Link } from '@inertiajs/vue3';
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
                    <div class="grid gap-2 md:grid-cols-2">
                        <div>
                            <span class="text-xs text-muted-foreground"
                                >PO</span
                            >
                            —
                            {{
                                gr.purchaseOrder?.po_number ??
                                gr.purchase_order_id
                            }}
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Warehouse</span
                            >
                            —
                            {{
                                gr.warehouse
                                    ? `${gr.warehouse.code} — ${gr.warehouse.name}`
                                    : gr.warehouse_id
                            }}
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <h2 class="text-sm font-semibold">Lines</h2>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="l in gr.lines ?? []"
                            :key="l.id"
                            class="flex items-start justify-between gap-4 rounded-md border px-3 py-2"
                        >
                            <div>
                                <div class="text-sm font-medium">
                                    {{ l.item?.sku ?? l.item_id }} —
                                    {{ l.item?.name ?? '' }}
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
