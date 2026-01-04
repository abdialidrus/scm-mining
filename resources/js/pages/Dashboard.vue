<script setup lang="ts">
import BarChart from '@/components/Charts/BarChart.vue';
import LineChart from '@/components/Charts/LineChart.vue';
import PieChart from '@/components/Charts/PieChart.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    DollarSign,
    Package,
    RefreshCw,
    ShoppingCart,
    TrendingDown,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

interface KPIData {
    total_spend: {
        value: number;
        change: number;
        trend: 'up' | 'down';
    };
    total_orders: {
        value: number;
        change: number;
        trend: 'up' | 'down';
    };
    avg_order_value: {
        value: number;
    };
    pending_approvals: {
        prs: number;
        pos: number;
        total: number;
    };
}

const loading = ref(true);
const kpis = ref<KPIData | null>(null);
const procurementTrend = ref<any>(null);
const statusDistribution = ref<any>(null);
const departmentSpending = ref<any>(null);
const inventorySnapshot = ref<any>(null);
const selectedPeriod = ref('month');

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value);
};

const formatNumber = (value: number) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

const fetchDashboardData = async () => {
    loading.value = true;
    try {
        const [kpisRes, procRes, statusRes, deptRes, invRes] =
            await Promise.all([
                axios.get('/api/dashboard/kpis'),
                axios.get('/api/dashboard/procurement-analytics?months=6'),
                axios.get('/api/dashboard/charts/status_distribution'),
                axios.get(
                    '/api/dashboard/charts/department_spending?months=12',
                ),
                axios.get('/api/dashboard/inventory-analytics?months=6'),
            ]);

        kpis.value = kpisRes.data.data;
        procurementTrend.value = procRes.data.data.monthly_trend;
        statusDistribution.value = statusRes.data.data;
        departmentSpending.value = deptRes.data.data;
        inventorySnapshot.value = invRes.data.data.snapshot;
    } catch (error) {
        console.error('Error fetching dashboard data:', error);
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
const procurementChartSeries = computed(() => {
    if (!procurementTrend.value) return [];
    return [
        {
            name: 'Total Amount',
            data: procurementTrend.value.amounts || [],
            color: '#3b82f6',
        },
    ];
});

const statusChartData = computed(() => {
    if (!statusDistribution.value) return [];
    // Group by status and sum counts
    const grouped: { [key: string]: number } = {};
    statusDistribution.value.forEach((item: any) => {
        grouped[item.name] = (grouped[item.name] || 0) + item.value;
    });
    return Object.entries(grouped).map(([name, value]) => ({ name, value }));
});

const departmentChartSeries = computed(() => {
    if (!departmentSpending.value) return [];
    return [
        {
            name: 'Spending',
            data: departmentSpending.value.amounts || [],
            color: '#10b981',
        },
    ];
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Dashboard</h1>
                    <p class="text-muted-foreground">
                        Welcome to your procurement analytics dashboard
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Select
                        v-model="selectedPeriod"
                        @update:modelValue="fetchDashboardData"
                    >
                        <SelectTrigger class="w-[140px]">
                            <SelectValue placeholder="Select period" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="week">This Week</SelectItem>
                            <SelectItem value="month">This Month</SelectItem>
                            <SelectItem value="quarter"
                                >This Quarter</SelectItem
                            >
                            <SelectItem value="year">This Year</SelectItem>
                        </SelectContent>
                    </Select>
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
                <!-- Total Spend -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Spending</CardTitle
                        >
                        <DollarSign class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div v-if="kpis" class="space-y-1">
                            <div class="text-2xl font-bold">
                                {{ formatCurrency(kpis.total_spend.value) }}
                            </div>
                            <div class="flex items-center text-xs">
                                <TrendingUp
                                    v-if="kpis.total_spend.trend === 'up'"
                                    class="mr-1 h-4 w-4 text-green-500"
                                />
                                <TrendingDown
                                    v-else
                                    class="mr-1 h-4 w-4 text-red-500"
                                />
                                <span
                                    :class="
                                        kpis.total_spend.trend === 'up'
                                            ? 'text-green-500'
                                            : 'text-red-500'
                                    "
                                >
                                    {{ Math.abs(kpis.total_spend.change) }}%
                                </span>
                                <span class="ml-1 text-muted-foreground"
                                    >from last period</span
                                >
                            </div>
                        </div>
                        <div
                            v-else
                            class="h-12 animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>

                <!-- Total Orders -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Purchase Orders</CardTitle
                        >
                        <ShoppingCart class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div v-if="kpis" class="space-y-1">
                            <div class="text-2xl font-bold">
                                {{ formatNumber(kpis.total_orders.value) }}
                            </div>
                            <div class="flex items-center text-xs">
                                <TrendingUp
                                    v-if="kpis.total_orders.trend === 'up'"
                                    class="mr-1 h-4 w-4 text-green-500"
                                />
                                <TrendingDown
                                    v-else
                                    class="mr-1 h-4 w-4 text-red-500"
                                />
                                <span
                                    :class="
                                        kpis.total_orders.trend === 'up'
                                            ? 'text-green-500'
                                            : 'text-red-500'
                                    "
                                >
                                    {{ Math.abs(kpis.total_orders.change) }}%
                                </span>
                                <span class="ml-1 text-muted-foreground"
                                    >from last period</span
                                >
                            </div>
                        </div>
                        <div
                            v-else
                            class="h-12 animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>

                <!-- Avg Order Value -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Avg. Order Value</CardTitle
                        >
                        <Package class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div v-if="kpis" class="space-y-1">
                            <div class="text-2xl font-bold">
                                {{ formatCurrency(kpis.avg_order_value.value) }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Per purchase order
                            </p>
                        </div>
                        <div
                            v-else
                            class="h-12 animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>

                <!-- Pending Approvals -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Pending Approvals</CardTitle
                        >
                        <AlertTriangle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div v-if="kpis" class="space-y-1">
                            <div class="text-2xl font-bold">
                                {{ kpis.pending_approvals.total }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ kpis.pending_approvals.prs }} PRs,
                                {{ kpis.pending_approvals.pos }} POs
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
                <!-- Procurement Trend -->
                <Card>
                    <CardHeader>
                        <CardTitle>Procurement Trend (6 Months)</CardTitle>
                        <CardDescription
                            >Monthly spending overview</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <LineChart
                            v-if="procurementTrend"
                            :x-data="procurementTrend.months || []"
                            :series="procurementChartSeries"
                            :loading="loading"
                            height="300px"
                        />
                        <div
                            v-else
                            class="h-[300px] animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>

                <!-- Status Distribution -->
                <Card>
                    <CardHeader>
                        <CardTitle>Status Distribution</CardTitle>
                        <CardDescription
                            >Purchase requests by status</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <PieChart
                            v-if="statusDistribution"
                            :data="statusChartData"
                            :loading="loading"
                            height="300px"
                            donut
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
                <!-- Department Spending -->
                <Card>
                    <CardHeader>
                        <CardTitle>Department Spending</CardTitle>
                        <CardDescription
                            >Top spending departments (12
                            months)</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <BarChart
                            v-if="departmentSpending"
                            :x-data="departmentSpending.departments || []"
                            :series="departmentChartSeries"
                            :loading="loading"
                            height="300px"
                        />
                        <div
                            v-else
                            class="h-[300px] animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>

                <!-- Inventory Snapshot -->
                <Card>
                    <CardHeader>
                        <CardTitle>Inventory Snapshot</CardTitle>
                        <CardDescription
                            >Current inventory status</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div v-if="inventorySnapshot" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <p class="text-sm text-muted-foreground">
                                        Total Items
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            formatNumber(
                                                inventorySnapshot.total_items,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-sm text-muted-foreground">
                                        Total Quantity
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            formatNumber(
                                                inventorySnapshot.total_quantity,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-sm text-muted-foreground">
                                        Total Value
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            formatCurrency(
                                                inventorySnapshot.total_value,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-sm text-muted-foreground">
                                        Low Stock Items
                                    </p>
                                    <p class="text-2xl font-bold text-red-500">
                                        {{ inventorySnapshot.low_stock_items }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="h-[200px] animate-pulse rounded bg-muted"
                        ></div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
