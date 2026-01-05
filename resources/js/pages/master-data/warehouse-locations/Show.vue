<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    DollarSign,
    Edit,
    MapPin,
    Package,
    Search,
    Trash2,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

// Types
type WarehouseDto = {
    id: number;
    code: string;
    name: string;
};

type LocationDto = {
    id: number;
    warehouse_id: number;
    parent_id: number | null;
    type: string;
    code: string;
    name: string;
    capacity: number | null;
    max_weight: number | null;
    is_default: boolean;
    is_active: boolean;
    notes: string | null;
    warehouse?: WarehouseDto;
    parent?: LocationDto;
};

type StockSummaryDto = {
    items_count: number;
    total_quantity: number;
    total_value: number;
};

type MovementDto = {
    id: number;
    item_code: string;
    item_name: string;
    qty: number;
    uom_code: string;
    reference_type: string;
    reference_id: number;
    source_location_id: number | null;
    destination_location_id: number | null;
    movement_at: string;
    direction: 'IN' | 'OUT';
};

type StockItemDto = {
    item_id: number;
    item_code: string;
    item_name: string;
    category_name: string | null;
    qty_on_hand: number;
    uom_code: string;
    total_value: number;
    unit_value: number;
};

// Props
const props = defineProps<{
    locationId: number;
}>();

// State
const loading = ref(false);
const error = ref<string | null>(null);
const location = ref<LocationDto | null>(null);
const stockSummary = ref<StockSummaryDto | null>(null);
const stockItems = ref<StockItemDto[]>([]);
const recentMovements = ref<MovementDto[]>([]);
const showDeleteDialog = ref(false);
const deleting = ref(false);
const stockSearchQuery = ref('');

// Computed
const hasStock = computed(
    () => stockSummary.value && stockSummary.value.total_quantity > 0,
);
const canDelete = computed(() => location.value?.is_active && !hasStock.value);

const filteredStockItems = computed(() => {
    if (!stockSearchQuery.value) return stockItems.value;

    const query = stockSearchQuery.value.toLowerCase();
    return stockItems.value.filter(
        (item) =>
            item.item_code.toLowerCase().includes(query) ||
            item.item_name.toLowerCase().includes(query) ||
            (item.category_name &&
                item.category_name.toLowerCase().includes(query)),
    );
});

// Methods
async function loadLocation() {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(
            `/api/warehouse-locations/${props.locationId}`,
        );
        if (!response.ok) throw new Error('Failed to load location');
        const data = await response.json();
        location.value = data.data;
    } catch (err: any) {
        console.error('Error loading location:', err);
        error.value = err.message;
    } finally {
        loading.value = false;
    }
}

async function loadStockSummary() {
    try {
        const response = await fetch(
            `/api/warehouse-locations/${props.locationId}/stock-summary`,
        );
        if (!response.ok) throw new Error('Failed to load stock summary');
        const data = await response.json();
        stockSummary.value = data.data;
    } catch (err: any) {
        console.error('Error loading stock summary:', err);
    }
}

async function loadRecentMovements() {
    try {
        const response = await fetch(
            `/api/warehouse-locations/${props.locationId}/movements?limit=20`,
        );
        if (!response.ok) throw new Error('Failed to load movements');
        const data = await response.json();
        recentMovements.value = data.data;
    } catch (err: any) {
        console.error('Error loading movements:', err);
    }
}

async function loadStockByItem() {
    try {
        const response = await fetch(
            `/api/warehouse-locations/${props.locationId}/stock-by-item`,
        );
        if (!response.ok) throw new Error('Failed to load stock items');
        const data = await response.json();
        stockItems.value = data.data;
    } catch (err: any) {
        console.error('Error loading stock items:', err);
    }
}

function editLocation() {
    router.visit(`/master-data/warehouse-locations/${props.locationId}/edit`);
}

async function confirmDelete() {
    if (!canDelete.value) return;

    deleting.value = true;
    error.value = null;

    try {
        const response = await fetch(
            `/api/warehouse-locations/${props.locationId}`,
            {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
            },
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to delete location');
        }

        // Success - redirect to index
        router.visit('/master-data/warehouse-locations');
    } catch (err: any) {
        console.error('Error deleting location:', err);
        error.value = err.message;
    } finally {
        deleting.value = false;
        showDeleteDialog.value = false;
    }
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getReferenceLabel(type: string): string {
    const labels: Record<string, string> = {
        purchase_order: 'PO',
        goods_receipt: 'GR',
        put_away: 'Put Away',
        picking_order: 'Picking',
        stock_transfer: 'Transfer',
        adjustment: 'Adjustment',
    };
    return labels[type] || type;
}

function goBack() {
    router.visit('/master-data/warehouse-locations');
}

// Lifecycle
onMounted(async () => {
    await loadLocation();
    await loadStockSummary();
    await loadStockByItem();
    await loadRecentMovements();
});
</script>

<template>
    <AppLayout :title="location?.name || 'Location Details'">
        <div class="container mx-auto space-y-6 py-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="mb-2 flex items-center gap-2">
                        <Button variant="ghost" size="sm" @click="goBack">
                            <ArrowLeft class="mr-1 h-4 w-4" />
                            Back
                        </Button>
                    </div>
                    <h1 class="text-3xl font-bold">
                        {{ location?.name || 'Loading...' }}
                    </h1>
                    <p class="mt-1 text-muted-foreground">
                        Warehouse location details and stock information
                    </p>
                </div>
                <div v-if="location" class="flex items-center gap-2">
                    <Button @click="editLocation">
                        <Edit class="mr-2 h-4 w-4" />
                        Edit
                    </Button>
                    <Button
                        variant="destructive"
                        @click="showDeleteDialog = true"
                        :disabled="!canDelete"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Deactivate
                    </Button>
                </div>
            </div>

            <!-- Error Message -->
            <div
                v-if="error"
                class="rounded-lg border border-red-200 bg-red-50 p-4"
            >
                <div class="flex items-start gap-3">
                    <AlertCircle class="mt-0.5 h-5 w-5 text-red-600" />
                    <div>
                        <h4 class="font-semibold text-red-900">Error</h4>
                        <p class="mt-1 text-sm text-red-700">{{ error }}</p>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <Card v-if="loading">
                <CardContent class="py-12 text-center text-muted-foreground">
                    Loading location details...
                </CardContent>
            </Card>

            <template v-else-if="location">
                <!-- Location Info Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Location Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            <!-- Code -->
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Location Code
                                </p>
                                <p class="font-mono text-lg font-semibold">
                                    {{ location.code }}
                                </p>
                            </div>

                            <!-- Warehouse -->
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Warehouse
                                </p>
                                <p class="font-medium">
                                    {{ location.warehouse?.code }} —
                                    {{ location.warehouse?.name }}
                                </p>
                            </div>

                            <!-- Type -->
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Type
                                </p>
                                <Badge
                                    :variant="
                                        location.type === 'RECEIVING'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{ location.type }}
                                </Badge>
                            </div>

                            <!-- Parent Location -->
                            <div v-if="location.parent">
                                <p class="text-sm text-muted-foreground">
                                    Parent Location
                                </p>
                                <p class="font-medium">
                                    {{ location.parent.code }} —
                                    {{ location.parent.name }}
                                </p>
                            </div>

                            <!-- Capacity -->
                            <div v-if="location.capacity">
                                <p class="text-sm text-muted-foreground">
                                    Capacity
                                </p>
                                <p class="font-medium">
                                    {{ formatNumber(location.capacity) }}
                                </p>
                            </div>

                            <!-- Max Weight -->
                            <div v-if="location.max_weight">
                                <p class="text-sm text-muted-foreground">
                                    Max Weight (kg)
                                </p>
                                <p class="font-medium">
                                    {{ formatNumber(location.max_weight) }}
                                </p>
                            </div>

                            <!-- Status -->
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Status
                                </p>
                                <Badge
                                    :variant="
                                        location.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        location.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </Badge>
                            </div>

                            <!-- Default -->
                            <div v-if="location.is_default">
                                <p class="text-sm text-muted-foreground">
                                    Default
                                </p>
                                <Badge variant="outline"
                                    >Default Receiving</Badge
                                >
                            </div>
                        </div>

                        <!-- Notes -->
                        <div v-if="location.notes" class="border-t pt-4">
                            <p class="mb-1 text-sm text-muted-foreground">
                                Notes
                            </p>
                            <p class="text-sm">{{ location.notes }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Stock Summary Cards -->
                <div class="grid gap-6 md:grid-cols-3">
                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between space-y-0 pb-2"
                        >
                            <CardTitle class="text-sm font-medium"
                                >Total Items</CardTitle
                            >
                            <Package class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">
                                {{ stockSummary?.items_count || 0 }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Unique items in location
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between space-y-0 pb-2"
                        >
                            <CardTitle class="text-sm font-medium"
                                >Total Quantity</CardTitle
                            >
                            <TrendingUp class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">
                                {{
                                    formatNumber(
                                        stockSummary?.total_quantity || 0,
                                    )
                                }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Total units in stock
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between space-y-0 pb-2"
                        >
                            <CardTitle class="text-sm font-medium"
                                >Stock Value</CardTitle
                            >
                            <DollarSign class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">
                                {{
                                    formatCurrency(
                                        stockSummary?.total_value || 0,
                                    )
                                }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Total inventory value
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Stock by Item -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Stock by Item</CardTitle>
                                <CardDescription>
                                    Detailed stock information per item
                                </CardDescription>
                            </div>
                            <div class="relative w-64">
                                <Search
                                    class="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground"
                                />
                                <Input
                                    v-model="stockSearchQuery"
                                    placeholder="Search items..."
                                    class="pl-8"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="stockItems.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            <Package
                                class="mx-auto mb-2 h-12 w-12 opacity-50"
                            />
                            <p>No stock in this location</p>
                        </div>
                        <div v-else class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Item Code</TableHead>
                                        <TableHead>Item Name</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead class="text-right"
                                            >Quantity</TableHead
                                        >
                                        <TableHead class="text-center"
                                            >UOM</TableHead
                                        >
                                        <TableHead class="text-right"
                                            >Unit Value</TableHead
                                        >
                                        <TableHead class="text-right"
                                            >Total Value</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="item in filteredStockItems"
                                        :key="item.item_id"
                                        class="hover:bg-muted/50"
                                    >
                                        <TableCell class="font-mono text-sm">
                                            {{ item.item_code }}
                                        </TableCell>
                                        <TableCell class="font-medium">
                                            {{ item.item_name }}
                                        </TableCell>
                                        <TableCell
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ item.category_name || '-' }}
                                        </TableCell>
                                        <TableCell class="text-right font-mono">
                                            {{ formatNumber(item.qty_on_hand) }}
                                        </TableCell>
                                        <TableCell class="text-center text-sm">
                                            {{ item.uom_code }}
                                        </TableCell>
                                        <TableCell
                                            class="text-right font-mono text-sm"
                                        >
                                            {{
                                                formatCurrency(item.unit_value)
                                            }}
                                        </TableCell>
                                        <TableCell
                                            class="text-right font-mono font-medium"
                                        >
                                            {{
                                                formatCurrency(item.total_value)
                                            }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                            <div
                                v-if="
                                    filteredStockItems.length === 0 &&
                                    stockSearchQuery
                                "
                                class="py-8 text-center text-muted-foreground"
                            >
                                <p>
                                    No items found matching "{{
                                        stockSearchQuery
                                    }}"
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Movements -->
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Stock Movements</CardTitle>
                        <CardDescription
                            >Last 20 movements in/out of this
                            location</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="recentMovements.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            <MapPin class="mx-auto mb-2 h-12 w-12 opacity-50" />
                            <p>No stock movements yet</p>
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr
                                        class="border-b text-sm text-muted-foreground"
                                    >
                                        <th
                                            class="px-2 py-3 text-left font-medium"
                                        >
                                            Date
                                        </th>
                                        <th
                                            class="px-2 py-3 text-left font-medium"
                                        >
                                            Direction
                                        </th>
                                        <th
                                            class="px-2 py-3 text-left font-medium"
                                        >
                                            Item
                                        </th>
                                        <th
                                            class="px-2 py-3 text-right font-medium"
                                        >
                                            Qty
                                        </th>
                                        <th
                                            class="px-2 py-3 text-center font-medium"
                                        >
                                            UOM
                                        </th>
                                        <th
                                            class="px-2 py-3 text-left font-medium"
                                        >
                                            Reference
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="movement in recentMovements"
                                        :key="movement.id"
                                        class="border-b hover:bg-muted/50"
                                    >
                                        <td class="px-2 py-3 text-sm">
                                            {{
                                                formatDate(movement.movement_at)
                                            }}
                                        </td>
                                        <td class="px-2 py-3">
                                            <Badge
                                                :variant="
                                                    movement.direction === 'IN'
                                                        ? 'default'
                                                        : 'secondary'
                                                "
                                                class="text-xs"
                                            >
                                                {{ movement.direction }}
                                            </Badge>
                                        </td>
                                        <td class="px-2 py-3 text-sm">
                                            <div
                                                class="font-mono text-xs text-muted-foreground"
                                            >
                                                {{ movement.item_code }}
                                            </div>
                                            <div class="font-medium">
                                                {{ movement.item_name }}
                                            </div>
                                        </td>
                                        <td
                                            class="px-2 py-3 text-right font-mono text-sm"
                                        >
                                            {{
                                                movement.direction === 'IN'
                                                    ? '+'
                                                    : '-'
                                            }}{{
                                                formatNumber(
                                                    Math.abs(movement.qty),
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-2 py-3 text-center text-sm"
                                        >
                                            {{ movement.uom_code }}
                                        </td>
                                        <td class="px-2 py-3 text-sm">
                                            <span class="text-muted-foreground">
                                                {{
                                                    getReferenceLabel(
                                                        movement.reference_type,
                                                    )
                                                }}
                                            </span>
                                            <span class="ml-1 font-mono">
                                                #{{ movement.reference_id }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <!-- Delete Confirmation Dialog -->
            <div
                v-if="showDeleteDialog"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="showDeleteDialog = false"
            >
                <Card class="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>Deactivate Location?</CardTitle>
                        <CardDescription>
                            This will mark the location as inactive. You can
                            reactivate it later.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="hasStock"
                            class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 p-4"
                        >
                            <div class="flex items-start gap-3">
                                <AlertCircle
                                    class="mt-0.5 h-5 w-5 text-yellow-600"
                                />
                                <div>
                                    <h4 class="font-semibold text-yellow-900">
                                        Cannot Deactivate
                                    </h4>
                                    <p class="mt-1 text-sm text-yellow-700">
                                        This location has stock. Please move all
                                        items before deactivating.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Button
                                variant="destructive"
                                @click="confirmDelete"
                                :disabled="!canDelete || deleting"
                            >
                                {{
                                    deleting
                                        ? 'Deactivating...'
                                        : 'Yes, Deactivate'
                                }}
                            </Button>
                            <Button
                                variant="outline"
                                @click="showDeleteDialog = false"
                                :disabled="deleting"
                            >
                                Cancel
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
