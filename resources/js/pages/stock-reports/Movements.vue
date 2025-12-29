<script setup lang="ts">
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
import { formatDateTime, formatQty } from '@/lib/format';
import { listWarehouses, type WarehouseDto } from '@/services/masterDataApi';
import {
    getStockMovements,
    type StockMovementDto,
} from '@/services/stockReportApi';
import { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    Package,
    Search,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Stock Reports',
        href: '/stock-reports',
    },
    {
        title: 'Movements',
        href: '/stock-reports/movements',
    },
];

const loading = ref(false);
const error = ref<string | null>(null);

// Filters
const warehouseId = ref<number | null>(null);
const referenceType = ref<string>('');
const dateFrom = ref<string>('');
const dateTo = ref<string>('');

// Pagination
const currentPage = ref(1);
const perPage = ref(20);

// Data
const movements = ref<StockMovementDto[]>([]);
const meta = ref<any>(null);
const links = ref<any>(null);

// Warehouses dropdown
const warehouses = ref<WarehouseDto[]>([]);

const totalPages = computed(() => meta.value?.last_page ?? 1);

async function loadWarehouses() {
    try {
        const res = await listWarehouses({ per_page: 100 });
        warehouses.value = res.data.data;
    } catch (e: any) {
        console.error('Failed to load warehouses', e);
    }
}

async function loadMovements() {
    loading.value = true;
    error.value = null;
    try {
        const res = await getStockMovements({
            warehouse_id: warehouseId.value ?? undefined,
            reference_type: referenceType.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            page: currentPage.value,
            per_page: perPage.value,
        });
        movements.value = res.data.data;
        meta.value = res.data.meta;
        links.value = res.data.links;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load stock movements';
    } finally {
        loading.value = false;
    }
}

function handleSearch() {
    currentPage.value = 1;
    loadMovements();
}

function clearFilters() {
    warehouseId.value = null;
    referenceType.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    currentPage.value = 1;
    loadMovements();
}

function goToPage(page: number) {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
    loadMovements();
}

function getReferenceTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        GOODS_RECEIPT: 'Goods Receipt',
        PUT_AWAY: 'Put Away',
        PICKING_ORDER: 'Picking Order',
        ADJUSTMENT: 'Adjustment',
    };
    return labels[type] ?? type;
}

function getReferenceTypeBadgeClass(type: string): string {
    const classes: Record<string, string> = {
        GOODS_RECEIPT: 'bg-blue-100 text-blue-800',
        PUT_AWAY: 'bg-green-100 text-green-800',
        PICKING_ORDER: 'bg-red-100 text-red-800',
        ADJUSTMENT: 'bg-yellow-100 text-yellow-800',
    };
    return classes[type] ?? 'bg-gray-100 text-gray-800';
}

onMounted(() => {
    loadWarehouses();
    loadMovements();
});
</script>

<template>
    <Head title="Stock Movements" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Stock Movements</h1>
                    <p class="text-sm text-muted-foreground">
                        View complete history of stock movements
                    </p>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-lg border bg-card p-4">
                <div class="grid gap-4 md:grid-cols-5">
                    <div class="md:col-span-1">
                        <label class="mb-2 block text-sm font-medium"
                            >Warehouse</label
                        >
                        <select
                            v-model="warehouseId"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option :value="null">All warehouses</option>
                            <option
                                v-for="wh in warehouses"
                                :key="wh.id"
                                :value="wh.id"
                            >
                                {{ wh.code }} — {{ wh.name }}
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-1">
                        <label class="mb-2 block text-sm font-medium"
                            >Movement Type</label
                        >
                        <select
                            v-model="referenceType"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="">All types</option>
                            <option value="GOODS_RECEIPT">Goods Receipt</option>
                            <option value="PUT_AWAY">Put Away</option>
                            <option value="PICKING_ORDER">Picking Order</option>
                            <option value="ADJUSTMENT">Adjustment</option>
                        </select>
                    </div>

                    <div class="md:col-span-1">
                        <label class="mb-2 block text-sm font-medium"
                            >Date From</label
                        >
                        <Input v-model="dateFrom" type="date" />
                    </div>

                    <div class="md:col-span-1">
                        <label class="mb-2 block text-sm font-medium"
                            >Date To</label
                        >
                        <Input v-model="dateTo" type="date" />
                    </div>

                    <div class="flex items-end gap-2 md:col-span-1">
                        <Button @click="handleSearch">
                            <Search class="mr-2 h-4 w-4" />
                            Search
                        </Button>
                        <Button variant="outline" @click="clearFilters">
                            Clear
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Movements Table -->
            <div class="rounded-lg border">
                <div
                    v-if="loading"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    Loading movements...
                </div>
                <div
                    v-else-if="error"
                    class="p-8 text-center text-sm text-destructive"
                >
                    {{ error }}
                </div>
                <div
                    v-else-if="movements.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No movements found
                </div>
                <div v-else class="overflow-hidden">
                    <Table>
                        <TableHeader class="bg-muted/40">
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Item</TableHead>
                                <TableHead>From Location</TableHead>
                                <TableHead class="text-center">→</TableHead>
                                <TableHead>To Location</TableHead>
                                <TableHead class="text-right">Qty</TableHead>
                                <TableHead>UOM</TableHead>
                                <TableHead>Reference</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="movement in movements"
                                :key="movement.id"
                            >
                                <TableCell class="text-xs">
                                    {{ formatDateTime(movement.movement_at) }}
                                </TableCell>
                                <TableCell>
                                    <span
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                        :class="
                                            getReferenceTypeBadgeClass(
                                                movement.reference_type,
                                            )
                                        "
                                    >
                                        {{
                                            getReferenceTypeLabel(
                                                movement.reference_type,
                                            )
                                        }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <div
                                        v-if="movement.item"
                                        class="flex items-center gap-2"
                                    >
                                        <Package class="h-4 w-4 shrink-0" />
                                        <div>
                                            <div class="font-medium">
                                                {{ movement.item.sku }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ movement.item.name }}
                                            </div>
                                        </div>
                                    </div>
                                    <span v-else class="text-muted-foreground"
                                        >Item #{{ movement.item_id }}</span
                                    >
                                </TableCell>
                                <TableCell>
                                    <div
                                        v-if="movement.sourceLocation"
                                        class="text-sm"
                                    >
                                        <div class="font-medium">
                                            {{ movement.sourceLocation.code }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ movement.sourceLocation.type }}
                                        </div>
                                    </div>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <TableCell class="text-center">
                                    <ArrowRight
                                        class="mx-auto h-4 w-4 text-muted-foreground"
                                    />
                                </TableCell>
                                <TableCell>
                                    <div
                                        v-if="movement.destinationLocation"
                                        class="text-sm"
                                    >
                                        <div class="font-medium">
                                            {{
                                                movement.destinationLocation
                                                    .code
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                movement.destinationLocation
                                                    .type
                                            }}
                                        </div>
                                    </div>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <TableCell class="text-right font-mono">
                                    {{ formatQty(movement.qty) }}
                                </TableCell>
                                <TableCell>
                                    {{ movement.uom?.code ?? '-' }}
                                </TableCell>
                                <TableCell class="text-sm">
                                    <div v-if="movement.meta">
                                        <template
                                            v-if="
                                                movement.reference_type ===
                                                'GOODS_RECEIPT'
                                            "
                                        >
                                            GR:
                                            {{
                                                movement.meta.gr_number ??
                                                movement.reference_id
                                            }}
                                        </template>
                                        <template
                                            v-else-if="
                                                movement.reference_type ===
                                                'PUT_AWAY'
                                            "
                                        >
                                            PA:
                                            {{
                                                movement.meta.put_away_number ??
                                                movement.reference_id
                                            }}
                                        </template>
                                        <template
                                            v-else-if="
                                                movement.reference_type ===
                                                'PICKING_ORDER'
                                            "
                                        >
                                            PK:
                                            {{
                                                movement.meta
                                                    .picking_order_number ??
                                                movement.reference_id
                                            }}
                                        </template>
                                        <template v-else>
                                            {{ movement.reference_type }} #{{
                                                movement.reference_id
                                            }}
                                        </template>
                                    </div>
                                    <div v-else class="text-muted-foreground">
                                        {{ movement.reference_type }} #{{
                                            movement.reference_id
                                        }}
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="!loading && meta && totalPages > 1"
                class="flex items-center justify-between rounded-lg border bg-card p-4"
            >
                <div class="text-sm text-muted-foreground">
                    Page {{ currentPage }} of {{ totalPages }}
                    <span v-if="meta.total">
                        — Showing {{ meta.from ?? 0 }} to {{ meta.to ?? 0 }} of
                        {{ meta.total }} movements
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="currentPage === 1 || loading"
                        @click="goToPage(currentPage - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                        <span class="ml-1 hidden sm:inline">Previous</span>
                    </Button>

                    <!-- Page number display -->
                    <div class="hidden items-center gap-1 sm:flex">
                        <Button
                            v-if="currentPage > 2"
                            variant="outline"
                            size="sm"
                            @click="goToPage(1)"
                        >
                            1
                        </Button>
                        <span v-if="currentPage > 3" class="px-2">...</span>

                        <Button
                            v-if="currentPage > 1"
                            variant="outline"
                            size="sm"
                            @click="goToPage(currentPage - 1)"
                        >
                            {{ currentPage - 1 }}
                        </Button>

                        <Button variant="default" size="sm" disabled>
                            {{ currentPage }}
                        </Button>

                        <Button
                            v-if="currentPage < totalPages"
                            variant="outline"
                            size="sm"
                            @click="goToPage(currentPage + 1)"
                        >
                            {{ currentPage + 1 }}
                        </Button>

                        <span v-if="currentPage < totalPages - 2" class="px-2"
                            >...</span
                        >
                        <Button
                            v-if="currentPage < totalPages - 1"
                            variant="outline"
                            size="sm"
                            @click="goToPage(totalPages)"
                        >
                            {{ totalPages }}
                        </Button>
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="currentPage === totalPages || loading"
                        @click="goToPage(currentPage + 1)"
                    >
                        <span class="mr-1 hidden sm:inline">Next</span>
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <!-- Show total count even when no pagination needed -->
            <div
                v-else-if="
                    !loading && meta && totalPages <= 1 && meta.total > 0
                "
                class="rounded-lg border bg-card p-3 text-center text-sm text-muted-foreground"
            >
                Showing all {{ meta.total }} movement{{
                    meta.total !== 1 ? 's' : ''
                }}
            </div>
        </div>
    </AppLayout>
</template>
