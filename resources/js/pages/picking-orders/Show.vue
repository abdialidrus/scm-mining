<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import StatusHistoryTable from '@/components/StatusHistoryTable.vue';
import Button from '@/components/ui/button/Button.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatQty } from '@/lib/format';
import {
    cancelPickingOrder,
    getPickingOrder,
    postPickingOrder,
    type PickingOrderDto,
} from '@/services/pickingOrderApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ pickingOrderId: number }>();

const loading = ref(true);
const acting = ref(false);
const error = ref<string | null>(null);
const pk = ref<PickingOrderDto | null>(null);

const title = computed(() => pk.value?.picking_order_number ?? 'Picking Order');

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await getPickingOrder(props.pickingOrderId);
        pk.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load picking order';
    } finally {
        loading.value = false;
    }
}

async function post() {
    if (!pk.value) return;
    if (!confirm(`Post Picking Order ${pk.value.picking_order_number}?`))
        return;

    acting.value = true;
    error.value = null;

    try {
        const res = await postPickingOrder(pk.value.id);
        pk.value = res.data;
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to post';
    } finally {
        acting.value = false;
    }
}

async function cancel() {
    if (!pk.value) return;

    const reason = prompt(
        `Cancel Picking Order ${pk.value.picking_order_number}?\n\nOptional reason:`,
    );
    if (reason === null) return; // User clicked Cancel

    acting.value = true;
    error.value = null;

    try {
        const res = await cancelPickingOrder(pk.value.id, reason || null);
        pk.value = res.data;
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
    { title: 'Picking Orders', href: '/picking-orders' },
    { title: 'Picking Order Details', href: '#' },
];

onMounted(load);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div v-if="loading" class="text-center">Loading...</div>

            <div v-else-if="!pk" class="text-center text-destructive">
                {{ error || 'Picking Order not found' }}
            </div>

            <div v-else class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-semibold">
                            {{ pk.picking_order_number }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Status: {{ pk.status }}
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <Button variant="outline" as-child>
                            <Link href="/picking-orders">Back</Link>
                        </Button>

                        <Button
                            v-if="pk.status === 'DRAFT'"
                            :disabled="acting"
                            type="button"
                            @click="post"
                        >
                            {{ acting ? 'Working...' : 'Post' }}
                        </Button>

                        <Button
                            v-if="pk.status === 'DRAFT'"
                            :disabled="acting"
                            variant="destructive"
                            type="button"
                            @click="cancel"
                        >
                            {{ acting ? 'Working...' : 'Cancel' }}
                        </Button>
                    </div>
                </div>

                <p v-if="error" class="text-sm text-destructive">
                    {{ error }}
                </p>

                <!-- Header Info -->
                <div class="rounded-lg border p-4">
                    <div class="text-sm font-semibold">Header Information</div>

                    <div class="mt-3 grid gap-3 md:grid-cols-12">
                        <div class="md:col-span-6">
                            <div class="text-xs text-muted-foreground">
                                Status
                            </div>
                            <div class="mt-1">
                                <StatusBadge :status="pk.status" />
                            </div>
                        </div>

                        <div class="md:col-span-6">
                            <div class="text-xs text-muted-foreground">
                                Picked At
                            </div>
                            <div class="mt-1 text-sm">
                                <span class="text-foreground">
                                    {{ formatDateTime(pk.picked_at) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-12">
                        <div class="md:col-span-6">
                            <div class="text-xs text-muted-foreground">
                                Warehouse
                            </div>
                            <div class="mt-1 text-sm">
                                <Link
                                    v-if="pk.warehouse_id"
                                    :href="`/warehouses/${pk.warehouse_id}`"
                                    class="font-medium hover:underline"
                                >
                                    {{
                                        pk.warehouse
                                            ? `${pk.warehouse.code} — ${pk.warehouse.name}`
                                            : `WH #${pk.warehouse_id}`
                                    }}
                                </Link>
                                <span v-else class="text-muted-foreground"
                                    >-</span
                                >
                            </div>
                        </div>

                        <div class="md:col-span-6">
                            <div class="text-xs text-muted-foreground">
                                Department
                            </div>
                            <div class="mt-1 text-sm">
                                {{ pk.department?.name ?? '-' }}
                            </div>
                        </div>

                        <div class="md:col-span-6">
                            <div class="text-xs text-muted-foreground">
                                Purpose
                            </div>
                            <div class="mt-1 text-sm">
                                {{ pk.purpose ?? '-' }}
                            </div>
                        </div>

                        <div class="md:col-span-6">
                            <div class="text-xs text-muted-foreground">
                                Remarks
                            </div>
                            <div class="mt-1 text-sm">
                                {{ pk.remarks ?? '-' }}
                            </div>
                        </div>
                    </div>

                    <div v-if="pk.status === 'CANCELLED'" class="mt-4">
                        <div class="text-xs text-muted-foreground">
                            Cancel Reason
                        </div>
                        <div class="mt-1 text-sm text-destructive">
                            {{ pk.cancel_reason ?? 'No reason provided' }}
                        </div>
                    </div>
                </div>

                <!-- Lines Table -->
                <div class="rounded-lg border p-4">
                    <div class="text-sm font-semibold">Picked Items</div>

                    <div class="mt-3 overflow-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Source Location</TableHead>
                                    <TableHead class="text-right"
                                        >Qty</TableHead
                                    >
                                    <TableHead>UOM</TableHead>
                                    <TableHead>Remarks</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="line in pk.lines ?? []"
                                    :key="line.id"
                                >
                                    <TableCell>
                                        <div class="font-medium">
                                            {{ line.item?.sku ?? 'Item' }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ line.item?.name ?? '' }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {{
                                            line.sourceLocation
                                                ? `${line.sourceLocation.code} — ${line.sourceLocation.name}`
                                                : '-'
                                        }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        {{ formatQty(line.qty) }}
                                    </TableCell>
                                    <TableCell>
                                        {{ line.uom?.code ?? '-' }}
                                    </TableCell>
                                    <TableCell>
                                        {{ line.remarks ?? '-' }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <!-- Status History -->
                <div class="rounded-lg border p-4">
                    <div class="text-sm font-semibold">Status History</div>
                    <div class="mt-3">
                        <StatusHistoryTable
                            :rows="pk.status_histories ?? []"
                            :format-date-time="formatDateTime"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
