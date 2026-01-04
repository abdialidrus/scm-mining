<script setup lang="ts">
import BarChart from '@/components/Charts/BarChart.vue';
import LineChart from '@/components/Charts/LineChart.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    Archive,
    Package,
    RefreshCw,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Inventory',
        href: '/inventory',
    },
    {
        title: 'Dashboard',
        href: '/inventory/dashboard',
    },
];

interface KPIData {
    total_items: number;
    total_quantity: number;
    total_value: number;
    low_stock_items: number;
}

interface ReorderItem {
    item_id: number;
    sku: string;
    name: string;
    warehouse: string;
    current_stock: number;
    reorder_point: number;
    reorder_quantity: number;
    shortage: number;
    stock_level_percent: number;
    lead_time_days: number;
}

const loading = ref(true);
const kpis = ref<KPIData | null>(null);
const movementTrend = ref<any>(null);
const warehouseDistribution = ref<any>(null);
const topMovingItems = ref<any>(null);
const stockAging = ref<any>(null);
const reorderAlerts = ref<{ items: ReorderItem[]; total_items: number } | null>(
    null,
);
const turnoverRate = ref<any>(null);
const selectedMonths = ref(6);
const selectedDays = ref(30);

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value);
};

const formatNumber = (value: number) => {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);
};

const fetchDashboardData = async () => {
    loading.value = true;
    try {
        const [
            kpisRes,
            movementRes,
            warehouseRes,
            topMovingRes,
            agingRes,
            reorderRes,
            turnoverRes,
        ] = await Promise.all([
            axios.get('/api/inventory/kpis'),
            axios.get(
                `/api/inventory/movement-analysis?months=${selectedMonths.value}`,
            ),
            axios.get('/api/inventory/warehouse-comparison'),
            axios.get(
                `/api/inventory/top-moving-items?days=${selectedDays.value}&limit=10`,
            ),
            axios.get('/api/inventory/stock-aging'),
            axios.get('/api/inventory/reorder-recommendations?limit=20'),
            axios.get('/api/inventory/turnover-rate?months=12'),
        ]);

        kpis.value = kpisRes.data.data;
        movementTrend.value = movementRes.data.data;
        warehouseDistribution.value = warehouseRes.data.data;
        topMovingItems.value = topMovingRes.data.data;
        stockAging.value = agingRes.data.data;
        reorderAlerts.value = reorderRes.data.data;
        turnoverRate.value = turnoverRes.data.data;
    } catch (error) {
        console.error('Error fetching inventory dashboard data:', error);
    } finally {
        loading.value = false;
    }
};

const refreshData = () => {
    fetchDashboardData();
};

onMounted(() => {
    fetchDashboardData();
});

// Computed data for charts
const movementChartSeries = computed(() => {
    if (!movementTrend.value) return [];
    return [
        {
            name: 'Inbound',
            data: movementTrend.value.inbound || [],
            color: '#10b981',
        },
        {
            name: 'Outbound',
            data: movementTrend.value.outbound || [],
            color: '#ef4444',
        },
    ];
});

const warehouseChartSeries = computed(() => {
    if (!warehouseDistribution.value) return [];
    return [
        {
            name: 'Item Count',
            data: warehouseDistribution.value.item_counts || [],
            color: '#3b82f6',
        },
    ];
});

const topMovingChartSeries = computed(() => {
    if (!topMovingItems.value) return [];
    return [
        {
            name: 'Movement Count',
            data: topMovingItems.value.movement_counts || [],
            color: '#8b5cf6',
        },
    ];
});

const agingChartSeries = computed(() => {
    if (!stockAging.value) return [];
    return [
        {
            name: 'Item Count',
            data: stockAging.value.item_counts || [],
            color: '#f59e0b',
        },
    ];
});

const getStockLevelColor = (
    percent: number,
): 'default' | 'destructive' | 'secondary' | 'outline' => {
    if (percent >= 100) return 'default';
    if (percent >= 50) return 'secondary';
    if (percent >= 25) return 'secondary';
    return 'destructive';
};

const getStockLevelLabel = (percent: number): string => {
    if (percent >= 100) return 'Normal';
    if (percent >= 50) return 'Medium';
    if (percent >= 25) return 'Low';
    return 'Critical';
};
</script>

<template>
    <Head title="Inventory Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        Inventory Dashboard
                    </h1>
                    <p class="text-muted-foreground">
                        Real-time inventory analytics and stock management
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        @click="refreshData"
                        :disabled="loading"
                    >
                        <RefreshCw
                            :class="{ 'animate-spin': loading }"
                            class="h-4 w-4"
                        />
                    </Button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Total Items -->
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
                        <div v-if="kpis" class="space-y-1">
                            <div class="text-2xl font-bold">
                                {{ formatNumber(kpis.total_items) }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Unique SKUs in inventory
                            </p>
                        </div>
                        <div
                            v-else
                            class="h-12 animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>

                <!-- Total Quantity -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Quantity</CardTitle
                        >
                        <Archive class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div v-if="kpis" class="space-y-1">
                            <div class="text-2xl font-bold">
                                {{ formatNumber(kpis.total_quantity) }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Units on hand
                            </p>
                        </div>
                        <div
                            v-else
                            class="h-12 animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>

                <!-- Total Value -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Value</CardTitle
                        >
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div v-if="kpis" class="space-y-1">
                            <div class="text-2xl font-bold">
                                {{ formatCurrency(kpis.total_value) }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                FIFO valuation
                            </p>
                        </div>
                        <div
                            v-else
                            class="h-12 animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>

                <!-- Low Stock Alerts -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Low Stock Alerts</CardTitle
                        >
                        <AlertTriangle class="h-4 w-4 text-destructive" />
                    </CardHeader>
                    <CardContent>
                        <div v-if="kpis" class="space-y-1">
                            <div class="text-2xl font-bold text-destructive">
                                {{ kpis.low_stock_items }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Items below reorder point
                            </p>
                        </div>
                        <div
                            v-else
                            class="h-12 animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>
            </div>

            <!-- Charts Row 1 -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Stock Movement Trend -->
                <Card>
                    <CardHeader>
                        <CardTitle>Stock Movement Trend</CardTitle>
                        <CardDescription
                            >Inbound vs Outbound ({{
                                selectedMonths
                            }}
                            months)</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <LineChart
                            v-if="movementTrend"
                            :x-data="movementTrend.months || []"
                            :series="movementChartSeries"
                            :loading="loading"
                            height="300px"
                        />
                        <div
                            v-else
                            class="h-[300px] animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>

                <!-- Warehouse Distribution -->
                <Card>
                    <CardHeader>
                        <CardTitle>Warehouse Distribution</CardTitle>
                        <CardDescription>Items per warehouse</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <BarChart
                            v-if="warehouseDistribution"
                            :x-data="warehouseDistribution.warehouses || []"
                            :series="warehouseChartSeries"
                            :loading="loading"
                            height="300px"
                        />
                        <div
                            v-else
                            class="h-[300px] animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>
            </div>

            <!-- Charts Row 2 -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Top Moving Items -->
                <Card>
                    <CardHeader>
                        <CardTitle>Top Moving Items</CardTitle>
                        <CardDescription
                            >Most frequently moved ({{
                                selectedDays
                            }}
                            days)</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <BarChart
                            v-if="topMovingItems"
                            :x-data="topMovingItems.items || []"
                            :series="topMovingChartSeries"
                            :loading="loading"
                            height="300px"
                            horizontal
                        />
                        <div
                            v-else
                            class="h-[300px] animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>

                <!-- Stock Aging Analysis -->
                <Card>
                    <CardHeader>
                        <CardTitle>Stock Aging Analysis</CardTitle>
                        <CardDescription
                            >Days since last movement</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <BarChart
                            v-if="stockAging"
                            :x-data="stockAging.buckets || []"
                            :series="agingChartSeries"
                            :loading="loading"
                            height="300px"
                        />
                        <div
                            v-else
                            class="h-[300px] animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>
            </div>

            <!-- Reorder Alerts Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Reorder Recommendations</CardTitle>
                    <CardDescription
                        >Items below reorder point - Action
                        required</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div
                        v-if="reorderAlerts && reorderAlerts.items.length > 0"
                        class="rounded-md border"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>SKU</TableHead>
                                    <TableHead>Item Name</TableHead>
                                    <TableHead>Warehouse</TableHead>
                                    <TableHead class="text-right"
                                        >Current Stock</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Reorder Point</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Shortage</TableHead
                                    >
                                    <TableHead class="text-center"
                                        >Status</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Suggested Qty</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="item in reorderAlerts.items.slice(
                                        0,
                                        10,
                                    )"
                                    :key="item.item_id"
                                >
                                    <TableCell class="font-medium">{{
                                        item.sku
                                    }}</TableCell>
                                    <TableCell>{{ item.name }}</TableCell>
                                    <TableCell>{{ item.warehouse }}</TableCell>
                                    <TableCell class="text-right">{{
                                        formatNumber(item.current_stock)
                                    }}</TableCell>
                                    <TableCell class="text-right">{{
                                        formatNumber(item.reorder_point)
                                    }}</TableCell>
                                    <TableCell
                                        class="text-right font-medium text-destructive"
                                    >
                                        {{ formatNumber(item.shortage) }}
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Badge
                                            :variant="
                                                getStockLevelColor(
                                                    item.stock_level_percent,
                                                )
                                            "
                                        >
                                            {{
                                                getStockLevelLabel(
                                                    item.stock_level_percent,
                                                )
                                            }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell
                                        class="text-right font-medium text-blue-600"
                                    >
                                        {{
                                            formatNumber(item.reorder_quantity)
                                        }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <div
                        v-else-if="reorderAlerts"
                        class="py-8 text-center text-muted-foreground"
                    >
                        <Package class="mx-auto mb-2 h-12 w-12 opacity-50" />
                        <p>All items are above reorder point</p>
                    </div>
                    <div
                        v-else
                        class="h-[200px] animate-pulse rounded bg-muted"
                    ></div>
                </CardContent>
            </Card>

            <!-- Additional Metrics -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Stock Turnover Rate</CardTitle>
                        <CardDescription>Last 12 months</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="turnoverRate" class="space-y-2">
                            <div class="text-3xl font-bold">
                                {{ formatNumber(turnoverRate.turnover_rate) }}x
                            </div>
                            <p class="text-xs text-muted-foreground">
                                COGS: {{ formatCurrency(turnoverRate.cogs) }}
                            </p>
                        </div>
                        <div
                            v-else
                            class="h-16 animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
