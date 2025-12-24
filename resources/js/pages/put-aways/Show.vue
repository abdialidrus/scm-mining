<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import StatusHistoryTable from '@/components/StatusHistoryTable.vue';
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatQty } from '@/lib/format';
import {
    cancelPutAway,
    getPutAway,
    postPutAway,
    type PutAwayDto,
} from '@/services/putAwayApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ putAwayId: number }>();

const loading = ref(true);
const acting = ref(false);
const error = ref<string | null>(null);
const pa = ref<PutAwayDto | null>(null);

const title = computed(() => pa.value?.put_away_number ?? 'Put Away');

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await getPutAway(props.putAwayId);
        pa.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load put away';
    } finally {
        loading.value = false;
    }
}

async function post() {
    if (!pa.value) return;
    if (!confirm(`Post Put Away ${pa.value.put_away_number}?`)) return;

    acting.value = true;
    error.value = null;

    try {
        const res = await postPutAway(pa.value.id);
        pa.value = res.data;
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to post';
    } finally {
        acting.value = false;
    }
}

async function cancel() {
    if (!pa.value) return;
    if (!confirm(`Cancel Put Away ${pa.value.put_away_number}?`)) return;

    acting.value = true;
    error.value = null;

    try {
        const res = await cancelPutAway(pa.value.id, null);
        pa.value = res.data;
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to cancel';
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
    { title: 'Put Aways', href: '/put-aways' },
    { title: 'Put Away Details', href: '#' },
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
                        {{ pa?.put_away_number ?? 'Put Away' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Status: {{ pa?.status ?? '-' }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/put-aways">Back</Link>
                    </Button>

                    <Button
                        v-if="pa?.status === 'DRAFT'"
                        :disabled="acting"
                        type="button"
                        @click="post"
                    >
                        {{ acting ? 'Working' : 'Post' }}
                    </Button>

                    <Button
                        v-if="pa?.status === 'DRAFT'"
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

            <div v-else-if="pa" class="mt-6 space-y-6">
                <div class="rounded-lg border p-4">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <div class="text-sm font-semibold">
                                    Put Away
                                </div>
                                <StatusBadge :status="pa.status" />
                            </div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                PA No:
                                <span class="font-medium text-foreground">
                                    {{ pa.put_away_number }}
                                </span>
                            </div>
                        </div>

                        <div class="text-right text-xs text-muted-foreground">
                            <div>
                                Put away at:
                                <span class="text-foreground">
                                    {{ formatDateTime(pa.put_away_at) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-12">
                        <div class="md:col-span-6">
                            <div class="text-xs text-muted-foreground">
                                Goods Receipt
                            </div>
                            <div class="mt-1 text-sm">
                                <Link
                                    v-if="pa.goods_receipt_id"
                                    :href="`/goods-receipts/${pa.goods_receipt_id}`"
                                    class="font-medium hover:underline"
                                >
                                    {{
                                        pa.goodsReceipt?.gr_number ??
                                        `GR #${pa.goods_receipt_id}`
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
                                    v-if="pa.warehouse_id"
                                    :href="`/master-data/warehouses/${pa.warehouse_id}`"
                                    class="font-medium hover:underline"
                                >
                                    {{
                                        pa.warehouse
                                            ? `${pa.warehouse.code} — ${pa.warehouse.name}`
                                            : `Warehouse #${pa.warehouse_id}`
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
                                {{ pa.remarks || '-' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <h2 class="text-sm font-semibold">Lines</h2>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="l in pa.lines ?? []"
                            :key="l.id"
                            class="rounded-md border px-3 py-2"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-sm font-medium">
                                        {{ l.item?.sku ?? l.item_id }} —
                                        {{ l.item?.name ?? '' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        Qty: {{ formatQty(l.qty) }}
                                        {{ l.uom?.code ?? '' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        From:
                                        {{
                                            l.sourceLocation
                                                ? `${l.sourceLocation.code} — ${l.sourceLocation.name}`
                                                : l.source_location_id
                                        }}
                                        → To:
                                        {{
                                            l.destinationLocation
                                                ? `${l.destinationLocation.code} — ${l.destinationLocation.name}`
                                                : l.destination_location_id
                                        }}
                                    </div>
                                    <div
                                        v-if="l.remarks"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Notes: {{ l.remarks }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="(pa.lines ?? []).length === 0"
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
                            :rows="pa.status_histories ?? []"
                            :format-date-time="formatDateTime"
                            empty-text="No history."
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
