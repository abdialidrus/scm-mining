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
import { apiFetch } from '@/services/http';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type WarehouseDto = {
    id: number;
    code: string;
    name: string;
};

type LocationDto = {
    id: number;
    warehouse_id: number;
    type: string;
    code: string;
    name: string;
    is_active: boolean;
    warehouse?: WarehouseDto;
};

const loading = ref(true);
const error = ref<string | null>(null);
const locations = ref<LocationDto[]>([]);
const warehouses = ref<WarehouseDto[]>([]);

const search = ref('');
const warehouseFilter = ref<number | null>(null);
const typeFilter = ref('');
const statusFilter = ref('active');

const page = ref(1);
const perPage = ref(15);
const totalPages = ref(1);

const filteredLocations = computed(() => {
    let filtered = locations.value;

    // Filter by warehouse
    if (warehouseFilter.value) {
        filtered = filtered.filter(
            (loc) => loc.warehouse_id === warehouseFilter.value,
        );
    }

    // Filter by type
    if (typeFilter.value) {
        filtered = filtered.filter((loc) => loc.type === typeFilter.value);
    }

    // Filter by status
    if (statusFilter.value === 'active') {
        filtered = filtered.filter((loc) => loc.is_active);
    } else if (statusFilter.value === 'inactive') {
        filtered = filtered.filter((loc) => !loc.is_active);
    }

    // Search by code or name
    if (search.value.trim()) {
        const searchLower = search.value.toLowerCase();
        filtered = filtered.filter(
            (loc) =>
                loc.code.toLowerCase().includes(searchLower) ||
                loc.name.toLowerCase().includes(searchLower),
        );
    }

    return filtered;
});

const paginatedLocations = computed(() => {
    const start = (page.value - 1) * perPage.value;
    const end = start + perPage.value;
    totalPages.value = Math.ceil(
        filteredLocations.value.length / perPage.value,
    );
    return filteredLocations.value.slice(start, end);
});

const hasNext = computed(() => page.value < totalPages.value);
const hasPrev = computed(() => page.value > 1);

async function loadWarehouses() {
    try {
        const res = await apiFetch<{ data: { data: WarehouseDto[] } }>(
            '/api/warehouses?per_page=100',
        );
        warehouses.value = res.data.data;
    } catch (e: any) {
        console.error('Failed to load warehouses:', e);
    }
}

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await apiFetch<{ data: LocationDto[] }>(
            '/api/warehouse-locations',
        );
        locations.value = res.data || [];

        // Load warehouse data for display
        await loadWarehouses();

        // Match warehouses to locations
        locations.value.forEach((loc) => {
            loc.warehouse = warehouses.value.find(
                (wh) => wh.id === loc.warehouse_id,
            );
        });
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load locations';
    } finally {
        loading.value = false;
    }
}

function onSearch() {
    page.value = 1;
}

function nextPage() {
    if (!hasNext.value) return;
    page.value += 1;
}

function prevPage() {
    if (!hasPrev.value) return;
    page.value -= 1;
}

function firstPage() {
    page.value = 1;
}

function lastPage() {
    page.value = totalPages.value;
}

onMounted(load);
</script>

<template>
    <Head title="Master Data - Warehouse Locations" />

    <AppLayout>
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Warehouse Locations</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage storage locations across warehouses.
                    </p>
                </div>

                <Button as-child>
                    <Link href="/master-data/warehouse-locations/create">
                        + Create Location
                    </Link>
                </Button>
            </div>

            <!-- Filters -->
            <div class="mt-6 grid items-end gap-3 md:grid-cols-12">
                <div class="md:col-span-3">
                    <label class="mb-2 block text-sm font-medium">
                        Search
                    </label>
                    <Input
                        v-model="search"
                        type="text"
                        placeholder="Search by code or name..."
                        @input="onSearch"
                    />
                </div>

                <div class="md:col-span-3">
                    <label class="mb-2 block text-sm font-medium">
                        Warehouse
                    </label>
                    <select
                        v-model.number="warehouseFilter"
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

                <div class="md:col-span-3">
                    <label class="mb-2 block text-sm font-medium">Type</label>
                    <select
                        v-model="typeFilter"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option value="">All types</option>
                        <option value="RECEIVING">RECEIVING</option>
                        <option value="STORAGE">STORAGE</option>
                    </select>
                </div>

                <div class="md:col-span-3">
                    <label class="mb-2 block text-sm font-medium">
                        Status
                    </label>
                    <select
                        v-model="statusFilter"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Loading/Error States -->
            <div v-if="loading" class="mt-6 text-sm text-muted-foreground">
                Loading...
            </div>

            <div v-if="error" class="mt-6 text-sm text-destructive">
                {{ error }}
            </div>

            <!-- Locations Table -->
            <div v-else class="mt-6 overflow-hidden rounded-lg border">
                <Table>
                    <TableHeader class="bg-muted/40">
                        <TableRow>
                            <TableHead>Code</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Warehouse</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="loc in paginatedLocations"
                            :key="loc.id"
                            class="cursor-pointer hover:bg-muted/30"
                            @click="
                                router.visit(
                                    `/master-data/warehouse-locations/${loc.id}`,
                                )
                            "
                        >
                            <TableCell class="font-mono font-medium">
                                {{ loc.code }}
                            </TableCell>
                            <TableCell>{{ loc.name }}</TableCell>
                            <TableCell>
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                    :class="{
                                        'bg-blue-100 text-blue-800':
                                            loc.type === 'RECEIVING',
                                        'bg-green-100 text-green-800':
                                            loc.type === 'STORAGE',
                                    }"
                                >
                                    {{ loc.type }}
                                </span>
                            </TableCell>
                            <TableCell>
                                {{ loc.warehouse?.code }} —
                                {{ loc.warehouse?.name }}
                            </TableCell>
                            <TableCell>
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                    :class="{
                                        'bg-green-100 text-green-800':
                                            loc.is_active,
                                        'bg-gray-100 text-gray-800':
                                            !loc.is_active,
                                    }"
                                >
                                    {{ loc.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="paginatedLocations.length === 0">
                            <TableCell
                                colspan="5"
                                class="py-6 text-center text-muted-foreground"
                            >
                                No locations found.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
            <div
                v-if="totalPages > 1"
                class="mt-4 flex items-center justify-between"
            >
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!hasPrev"
                        @click="firstPage"
                    >
                        <ChevronsLeft class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!hasPrev"
                        @click="prevPage"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                </div>

                <div class="text-sm text-muted-foreground">
                    Page {{ page }} of {{ totalPages }} ({{
                        filteredLocations.length
                    }}
                    total)
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!hasNext"
                        @click="nextPage"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!hasNext"
                        @click="lastPage"
                    >
                        <ChevronsRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
