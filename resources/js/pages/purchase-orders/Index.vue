<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency, formatDateTime } from '@/lib/format';
import {
    listPurchaseOrders,
    type PurchaseOrderDto,
} from '@/services/purchaseOrderApi';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const loading = ref(true);
const error = ref<string | null>(null);
const items = ref<PurchaseOrderDto[]>([]);

const search = ref('');
const status = ref<{ value: string; label: string }>({
    value: '',
    label: 'All',
});
const page = ref(1);
const hasNext = ref(false);
const hasPrev = computed(() => page.value > 1);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await listPurchaseOrders({
            search: search.value || undefined,
            status: status.value.value || undefined,
            page: page.value,
        });

        const paginated = (res as any).data;
        items.value = (paginated?.data ?? []) as PurchaseOrderDto[];

        const meta = paginated?.meta;
        const currentPage = Number(meta?.current_page ?? page.value);
        const lastPage = Number(meta?.last_page ?? currentPage);
        page.value = currentPage;
        hasNext.value = currentPage < lastPage;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load purchase orders';
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    page.value = 1;
    load();
}

function nextPage() {
    if (!hasNext.value) return;
    page.value += 1;
    load();
}

function prevPage() {
    if (!hasPrev.value) return;
    page.value -= 1;
    load();
}

onMounted(load);
</script>

<template>
    <Head title="Purchase Orders" />

    <AppLayout>
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Purchase Orders</h1>
                    <p class="text-sm text-muted-foreground">
                        Procurement purchase orders.
                    </p>
                </div>

                <Button as-child>
                    <Link href="/purchase-orders/create">Create</Link>
                </Button>
            </div>

            <div class="mt-6 grid items-end gap-3 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="text-sm font-medium">Search</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Input
                            v-model="search"
                            class="h-10"
                            placeholder="PO number / supplier"
                        />
                    </div>
                </div>
                <div class="md:col-span-4">
                    <label class="text-sm font-medium">Status</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Multiselect
                            v-model="status"
                            :options="[
                                { value: '', label: 'All' },
                                { value: 'DRAFT', label: 'DRAFT' },
                                { value: 'SUBMITTED', label: 'SUBMITTED' },
                                { value: 'IN_APPROVAL', label: 'IN APPROVAL' },
                                { value: 'APPROVED', label: 'APPROVED' },
                                { value: 'SENT', label: 'SENT' },
                                { value: 'CLOSED', label: 'CLOSED' },
                                { value: 'CANCELLED', label: 'CANCELLED' },
                            ]"
                            track-by="value"
                            label="label"
                            class="w-full"
                        />
                    </div>
                </div>
                <div class="md:col-span-2">
                    <div class="mt-1 flex h-10 items-center">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 w-full"
                            @click="applyFilters"
                        >
                            Apply
                        </Button>
                    </div>
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

            <div v-else class="mt-6 overflow-hidden rounded-lg border">
                <Table>
                    <TableHeader class="bg-muted/40">
                        <TableRow>
                            <TableHead>PO No</TableHead>
                            <TableHead>Supplier</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Currency</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                            <TableHead>Created</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="po in items"
                            :key="po.id"
                            class="cursor-pointer hover:bg-muted/30"
                            @click="router.visit(`/purchase-orders/${po.id}`)"
                        >
                            <TableCell class="font-medium">{{
                                po.po_number
                            }}</TableCell>
                            <TableCell>{{
                                po.supplier?.name ?? po.supplier_id
                            }}</TableCell>
                            <TableCell>
                                <StatusBadge module="PO" :status="po.status" />
                            </TableCell>
                            <TableCell>{{ po.currency_code }}</TableCell>
                            <TableCell class="text-right">{{
                                po.total_amount == null
                                    ? '-'
                                    : formatCurrency(po.total_amount as any, {
                                          currency: po.currency_code,
                                      })
                            }}</TableCell>
                            <TableCell>{{
                                formatDateTime(po.created_at) ?? '-'
                            }}</TableCell>
                        </TableRow>

                        <TableRow v-if="items.length === 0">
                            <TableCell
                                colspan="6"
                                class="py-6 text-center text-muted-foreground"
                            >
                                No purchase orders.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <Button
                    variant="outline"
                    type="button"
                    :disabled="!hasPrev"
                    @click="prevPage"
                    >Previous</Button
                >
                <div class="text-sm text-muted-foreground">Page {{ page }}</div>
                <Button
                    variant="outline"
                    type="button"
                    :disabled="!hasNext"
                    @click="nextPage"
                    >Next</Button
                >
            </div>
        </div>
    </AppLayout>
</template>
