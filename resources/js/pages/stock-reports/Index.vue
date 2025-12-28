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
import { formatQty } from '@/lib/format';
import { listWarehouses, type WarehouseDto } from '@/services/masterDataApi';
import {
    getStockByLocation,
    getStockSummaryByItem,
    type StockByLocationDto,
    type StockSummaryByItemDto,
} from '@/services/stockReportApi';
import { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Stock Reports',
        href: '/stock-reports',
    },
];

const activeTab = ref('by-location');
const loading = ref(false);
const error = ref<string | null>(null);

// Filters
const search = ref('');
const warehouseId = ref<number | null>(null);
const locationType = ref<string>('');

// Pagination
const currentPage = ref(1);
const perPage = ref(20);

// Data
const stockByLocation = ref<StockByLocationDto[]>([]);
const stockByItem = ref<StockSummaryByItemDto[]>([]);
const meta = ref<any>(null);
const links = ref<any>(null);

// Warehouses dropdown
const warehouses = ref<WarehouseDto[]>([]);
const warehousesLoading = ref(false);

const totalPages = computed(() => meta.value?.last_page ?? 1);

async function loadWarehouses() {
    warehousesLoading.value = true;
    try {
        const res = await listWarehouses({ per_page: 100 });
        warehouses.value = res.data.data;
    } catch (e: any) {
        console.error('Failed to load warehouses', e);
    } finally {
        warehousesLoading.value = false;
    }
}

async function loadStockByLocation() {
    loading.value = true;
    error.value = null;
    try {
        const res = await getStockByLocation({
            warehouse_id: warehouseId.value ?? undefined,
            search: search.value.trim() || undefined,
            location_type: locationType.value || undefined,
            page: currentPage.value,
            per_page: perPage.value,
        });
        stockByLocation.value = res.data.items;
        meta.value = res.data.meta;
        links.value = res.data.links;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load stock by location';
    } finally {
        loading.value = false;
    }
}

async function loadStockByItem() {
    loading.value = true;
    error.value = null;
    try {
        const res = await getStockSummaryByItem({
            warehouse_id: warehouseId.value ?? undefined,
            search: search.value.trim() || undefined,
            page: currentPage.value,
            per_page: perPage.value,
        });
        stockByItem.value = res.data.items;
        meta.value = res.data.meta;
        links.value = res.data.links;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load stock by item';
    } finally {
        loading.value = false;
    }
}

function loadData() {
    currentPage.value = 1;
    if (activeTab.value === 'by-location') {
        loadStockByLocation();
    } else {
        loadStockByItem();
    }
}

function handleSearch() {
    loadData();
}

function clearFilters() {
    search.value = '';
    warehouseId.value = null;
    locationType.value = '';
    currentPage.value = 1;
    loadData();
}

function goToPage(page: number) {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
    if (activeTab.value === 'by-location') {
        loadStockByLocation();
    } else {
        loadStockByItem();
    }
}

watch(activeTab, () => {
    currentPage.value = 1;
    loadData();
});

onMounted(() => {
    loadWarehouses();
    loadData();
});
</script>

<template>
    <Head title="Stock Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Stock Reports</h1>
                    <p class="text-sm text-muted-foreground">
                        View current stock levels and inventory details
                    </p>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-lg border bg-card p-4">
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="md:col-span-1">
                        <label class="mb-2 block text-sm font-medium"
                            >Search</label
                        >
                        <div class="relative">
                            <Input
                                v-model="search"
                                placeholder="Item SKU or name..."
                                @keyup.enter="handleSearch"
                            />
                            <Search
                                class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                        </div>
                    </div>

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

                    <div
                        v-if="activeTab === 'by-location'"
                        class="md:col-span-1"
                    >
                        <label class="mb-2 block text-sm font-medium"
                            >Location Type</label
                        >
                        <select
                            v-model="locationType"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="">All types</option>
                            <option value="RECEIVING">Receiving</option>
                            <option value="STORAGE">Storage</option>
                        </select>
                    </div>

                    <div
                        class="flex items-end gap-2"
                        :class="{
                            'md:col-span-1': activeTab === 'by-location',
                            'md:col-span-2': activeTab !== 'by-location',
                        }"
                    >
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

            <!-- Tabs -->
            <div class="rounded-lg border bg-card">
                <div class="flex border-b">
                    <button
                        class="px-4 py-2 text-sm font-medium transition-colors"
                        :class="{
                            'border-b-2 border-primary text-primary':
                                activeTab === 'by-location',
                            'text-muted-foreground hover:text-foreground':
                                activeTab !== 'by-location',
                        }"
                        @click="activeTab = 'by-location'"
                    >
                        By Location
                    </button>
                    <button
                        class="px-4 py-2 text-sm font-medium transition-colors"
                        :class="{
                            'border-b-2 border-primary text-primary':
                                activeTab === 'by-item',
                            'text-muted-foreground hover:text-foreground':
                                activeTab !== 'by-item',
                        }"
                        @click="activeTab = 'by-item'"
                    >
                        By Item
                    </button>
                </div>

                <!-- By Location Content -->
                <div v-show="activeTab === 'by-location'" class="p-4">
                    <div
                        v-if="loading"
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        Loading stock data...
                    </div>
                    <div
                        v-else-if="error"
                        class="p-8 text-center text-sm text-destructive"
                    >
                        {{ error }}
                    </div>
                    <div
                        v-else-if="stockByLocation.length === 0"
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        No stock found
                    </div>
                    <div v-else class="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader class="bg-muted/40">
                                <TableRow>
                                    <TableHead>Warehouse</TableHead>
                                    <TableHead>Location</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Item SKU</TableHead>
                                    <TableHead>Item Name</TableHead>
                                    <TableHead class="text-right"
                                        >Qty On Hand</TableHead
                                    >
                                    <TableHead>UOM</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="row in stockByLocation"
                                    :key="`${row.location_id}-${row.item_id}`"
                                >
                                    <TableCell>
                                        {{ row.warehouse_code }}
                                    </TableCell>
                                    <TableCell>
                                        {{ row.location_code }} —
                                        {{ row.location_name }}
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                            :class="{
                                                'bg-blue-100 text-blue-800':
                                                    row.location_type ===
                                                    'RECEIVING',
                                                'bg-green-100 text-green-800':
                                                    row.location_type ===
                                                    'STORAGE',
                                            }"
                                        >
                                            {{ row.location_type }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="font-medium">
                                        {{ row.sku }}
                                    </TableCell>
                                    <TableCell>
                                        {{ row.item_name }}
                                    </TableCell>
                                    <TableCell class="text-right font-mono">
                                        {{ formatQty(row.qty_on_hand) }}
                                    </TableCell>
                                    <TableCell>
                                        {{ row.uom_code ?? '-' }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <!-- By Item Content -->
                <div v-show="activeTab === 'by-item'" class="p-4">
                    <div
                        v-if="loading"
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        Loading stock data...
                    </div>
                    <div
                        v-else-if="error"
                        class="p-8 text-center text-sm text-destructive"
                    >
                        {{ error }}
                    </div>
                    <div
                        v-else-if="stockByItem.length === 0"
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        No stock found
                    </div>
                    <div v-else class="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader class="bg-muted/40">
                                <TableRow>
                                    <TableHead>Item SKU</TableHead>
                                    <TableHead>Item Name</TableHead>
                                    <TableHead class="text-right"
                                        >Total Qty</TableHead
                                    >
                                    <TableHead>UOM</TableHead>
                                    <TableHead class="text-right"
                                        >Locations</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="row in stockByItem"
                                    :key="row.item_id"
                                >
                                    <TableCell class="font-medium">
                                        {{ row.sku }}
                                    </TableCell>
                                    <TableCell>
                                        {{ row.name }}
                                    </TableCell>
                                    <TableCell class="text-right font-mono">
                                        {{ formatQty(row.qty_on_hand) }}
                                    </TableCell>
                                    <TableCell>
                                        {{ row.uom_code ?? '-' }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        {{ row.locations_count }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
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
                        {{ meta.total }} records
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
                Showing all {{ meta.total }} record{{
                    meta.total !== 1 ? 's' : ''
                }}
            </div>
        </div>
    </AppLayout>
</template>
