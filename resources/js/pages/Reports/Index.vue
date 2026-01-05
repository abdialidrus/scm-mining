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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    DollarSign,
    Download,
    FileText,
    Package,
    ShoppingCart,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reports',
        href: '/reports',
    },
];

const loading = ref(false);
const activeTab = ref('procurement');
const selectedMonths = ref(12);

// Procurement Data
const procurementData = ref<any>(null);

// Inventory Data
const inventoryData = ref<any>(null);

// Financial Data
const financialData = ref<any>(null);

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

const fetchProcurementReports = async () => {
    loading.value = true;
    try {
        const response = await axios.get(
            `/api/dashboard/procurement-analytics?months=${selectedMonths.value}`,
        );
        procurementData.value = response.data.data;
    } catch (error) {
        console.error('Error fetching procurement reports:', error);
    } finally {
        loading.value = false;
    }
};

const fetchInventoryReports = async () => {
    loading.value = true;
    try {
        const response = await axios.get(
            `/api/dashboard/inventory-analytics?months=${selectedMonths.value}`,
        );
        inventoryData.value = response.data.data;
    } catch (error) {
        console.error('Error fetching inventory reports:', error);
    } finally {
        loading.value = false;
    }
};

const fetchFinancialReports = async () => {
    loading.value = true;
    try {
        const response = await axios.get(
            `/api/dashboard/financial-analytics?months=${selectedMonths.value}`,
        );
        financialData.value = response.data.data;
    } catch (error) {
        console.error('Error fetching financial reports:', error);
    } finally {
        loading.value = false;
    }
};

const fetchReports = () => {
    if (activeTab.value === 'procurement') {
        fetchProcurementReports();
    } else if (activeTab.value === 'inventory') {
        fetchInventoryReports();
    } else if (activeTab.value === 'financial') {
        fetchFinancialReports();
    }
};

const exportReport = (format: 'pdf' | 'excel') => {
    // TODO: Implement export functionality
    console.log(`Exporting ${activeTab.value} report as ${format}`);
};

onMounted(() => {
    fetchProcurementReports();
});

// Computed chart data
const procurementTrendSeries = computed(() => {
    if (!procurementData.value?.monthly_trend) return [];
    return [
        {
            name: 'Total Amount',
            data:
                procurementData.value.monthly_trend.amounts?.map((v: any) =>
                    Number(v),
                ) || [],
            color: '#3b82f6',
        },
        {
            name: 'Count',
            data:
                procurementData.value.monthly_trend.counts?.map((v: any) =>
                    Number(v),
                ) || [],
            color: '#10b981',
        },
    ];
});

const supplierChartSeries = computed(() => {
    if (!procurementData.value?.top_suppliers) return [];
    return [
        {
            name: 'Amount',
            data:
                procurementData.value.top_suppliers.amounts?.map((v: any) =>
                    Number(v),
                ) || [],
            color: '#f59e0b',
        },
    ];
});

const inventoryMovementSeries = computed(() => {
    if (!inventoryData.value?.movement_trend) return [];
    return [
        {
            name: 'Inbound',
            data:
                inventoryData.value.movement_trend.inbound?.map((v: any) =>
                    Number(v),
                ) || [],
            color: '#10b981',
        },
        {
            name: 'Outbound',
            data:
                inventoryData.value.movement_trend.outbound?.map((v: any) =>
                    Number(v),
                ) || [],
            color: '#ef4444',
        },
    ];
});

const warehouseChartSeries = computed(() => {
    if (!inventoryData.value?.warehouse_distribution) return [];
    return [
        {
            name: 'Value',
            data:
                inventoryData.value.warehouse_distribution.values?.map(
                    (v: any) => Number(v),
                ) || [],
            color: '#8b5cf6',
        },
    ];
});

const spendingTrendSeries = computed(() => {
    if (!financialData.value?.spending_trend) return [];
    return [
        {
            name: 'Spending',
            data:
                financialData.value.spending_trend.amounts?.map((v: any) =>
                    Number(v),
                ) || [],
            color: '#ef4444',
        },
    ];
});

const budgetChartSeries = computed(() => {
    if (!financialData.value?.budget_vs_actual) return [];
    return [
        {
            name: 'Budget',
            data:
                financialData.value.budget_vs_actual.budgets?.map((v: any) =>
                    Number(v),
                ) || [],
            color: '#3b82f6',
        },
        {
            name: 'Actual',
            data:
                financialData.value.budget_vs_actual.actuals?.map((v: any) =>
                    Number(v),
                ) || [],
            color: '#10b981',
        },
    ];
});

const paymentStatusData = computed(() => {
    if (!financialData.value?.payment_status?.statuses) return [];
    return financialData.value.payment_status.statuses.map((status: any) => ({
        name: status.name,
        value: parseFloat(status.amount),
    }));
});
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        Reports & Analytics
                    </h1>
                    <p class="text-muted-foreground">
                        Comprehensive reporting and data analysis
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Select
                        v-model="selectedMonths"
                        @update:modelValue="fetchReports"
                    >
                        <SelectTrigger class="w-[140px]">
                            <SelectValue placeholder="Select period" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="3">Last 3 Months</SelectItem>
                            <SelectItem :value="6">Last 6 Months</SelectItem>
                            <SelectItem :value="12">Last 12 Months</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button variant="outline" @click="exportReport('pdf')">
                        <FileText class="mr-2 h-4 w-4" />
                        Export PDF
                    </Button>
                    <Button variant="outline" @click="exportReport('excel')">
                        <Download class="mr-2 h-4 w-4" />
                        Export Excel
                    </Button>
                </div>
            </div>

            <!-- Reports Tabs -->
            <Tabs
                v-model="activeTab"
                @update:modelValue="fetchReports"
                class="space-y-4"
            >
                <TabsList>
                    <TabsTrigger value="procurement">
                        <ShoppingCart class="mr-2 h-4 w-4" />
                        Procurement
                    </TabsTrigger>
                    <TabsTrigger value="inventory">
                        <Package class="mr-2 h-4 w-4" />
                        Inventory
                    </TabsTrigger>
                    <TabsTrigger value="financial">
                        <DollarSign class="mr-2 h-4 w-4" />
                        Financial
                    </TabsTrigger>
                </TabsList>

                <!-- Procurement Tab -->
                <TabsContent value="procurement" class="space-y-4">
                    <!-- Summary Cards -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium"
                                    >Total PRs</CardTitle
                                >
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="procurementData"
                                    class="text-2xl font-bold"
                                >
                                    {{
                                        formatNumber(
                                            procurementData.monthly_trend?.counts
                                                ?.map((v: any) => Number(v))
                                                .reduce(
                                                    (a: number, b: number) =>
                                                        a + b,
                                                    0,
                                                ) || 0,
                                        )
                                    }}
                                </div>
                                <div
                                    v-else
                                    class="h-8 animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium"
                                    >Total Value</CardTitle
                                >
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="procurementData"
                                    class="text-2xl font-bold"
                                >
                                    {{
                                        formatCurrency(
                                            procurementData.monthly_trend?.amounts
                                                ?.map((v: any) => Number(v))
                                                .reduce(
                                                    (a: number, b: number) =>
                                                        a + b,
                                                    0,
                                                ) || 0,
                                        )
                                    }}
                                </div>
                                <div
                                    v-else
                                    class="h-8 animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium"
                                    >Avg Cycle Time</CardTitle
                                >
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="procurementData"
                                    class="text-2xl font-bold"
                                >
                                    {{
                                        procurementData.cycle_time
                                            ?.average_cycle_days || 0
                                    }}
                                    days
                                </div>
                                <div
                                    v-else
                                    class="h-8 animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Charts -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Monthly Trend</CardTitle>
                                <CardDescription
                                    >Purchase requests over
                                    time</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <LineChart
                                    v-if="procurementData?.monthly_trend"
                                    :x-data="
                                        procurementData.monthly_trend.months ||
                                        []
                                    "
                                    :series="procurementTrendSeries"
                                    :loading="loading"
                                    height="350px"
                                />
                                <div
                                    v-else
                                    class="h-[350px] animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Top Suppliers</CardTitle>
                                <CardDescription
                                    >By order value</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <BarChart
                                    v-if="procurementData?.top_suppliers"
                                    :x-data="
                                        procurementData.top_suppliers
                                            .suppliers || []
                                    "
                                    :series="supplierChartSeries"
                                    :loading="loading"
                                    height="350px"
                                />
                                <div
                                    v-else
                                    class="h-[350px] animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Status Distribution</CardTitle>
                                <CardDescription
                                    >Current status breakdown</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <PieChart
                                    v-if="procurementData?.status_distribution"
                                    :data="procurementData.status_distribution"
                                    :loading="loading"
                                    height="350px"
                                    donut
                                />
                                <div
                                    v-else
                                    class="h-[350px] animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Department Spending</CardTitle>
                                <CardDescription
                                    >Top departments by spend</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <BarChart
                                    v-if="procurementData?.department_spending"
                                    :x-data="
                                        procurementData.department_spending
                                            .departments || []
                                    "
                                    :series="[
                                        {
                                            name: 'Spending',
                                            data:
                                                procurementData.department_spending.amounts?.map(
                                                    (v: any) => Number(v),
                                                ) || [],
                                            color: '#6366f1',
                                        },
                                    ]"
                                    :loading="loading"
                                    height="350px"
                                    horizontal
                                />
                                <div
                                    v-else
                                    class="h-[350px] animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Inventory Tab -->
                <TabsContent value="inventory" class="space-y-4">
                    <!-- Summary Cards -->
                    <div class="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium"
                                    >Total Items</CardTitle
                                >
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="inventoryData"
                                    class="text-2xl font-bold"
                                >
                                    {{
                                        formatNumber(
                                            inventoryData.snapshot
                                                ?.total_items || 0,
                                        )
                                    }}
                                </div>
                                <div
                                    v-else
                                    class="h-8 animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium"
                                    >Total Quantity</CardTitle
                                >
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="inventoryData"
                                    class="text-2xl font-bold"
                                >
                                    {{
                                        formatNumber(
                                            inventoryData.snapshot
                                                ?.total_quantity || 0,
                                        )
                                    }}
                                </div>
                                <div
                                    v-else
                                    class="h-8 animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium"
                                    >Total Value</CardTitle
                                >
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="inventoryData"
                                    class="text-2xl font-bold"
                                >
                                    {{
                                        formatCurrency(
                                            inventoryData.snapshot
                                                ?.total_value || 0,
                                        )
                                    }}
                                </div>
                                <div
                                    v-else
                                    class="h-8 animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle
                                    class="text-sm font-medium text-red-500"
                                    >Low Stock</CardTitle
                                >
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="inventoryData"
                                    class="text-2xl font-bold text-red-500"
                                >
                                    {{
                                        inventoryData.snapshot
                                            ?.low_stock_items || 0
                                    }}
                                </div>
                                <div
                                    v-else
                                    class="h-8 animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Charts -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Stock Movement Trend</CardTitle>
                                <CardDescription
                                    >Inbound vs Outbound</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <LineChart
                                    v-if="inventoryData?.movement_trend"
                                    :x-data="
                                        inventoryData.movement_trend.months ||
                                        []
                                    "
                                    :series="inventoryMovementSeries"
                                    :loading="loading"
                                    height="350px"
                                />
                                <div
                                    v-else
                                    class="h-[350px] animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Warehouse Distribution</CardTitle>
                                <CardDescription
                                    >Inventory value by
                                    warehouse</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <BarChart
                                    v-if="inventoryData?.warehouse_distribution"
                                    :x-data="
                                        inventoryData.warehouse_distribution
                                            .warehouses || []
                                    "
                                    :series="warehouseChartSeries"
                                    :loading="loading"
                                    height="350px"
                                />
                                <div
                                    v-else
                                    class="h-[350px] animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>ABC Analysis</CardTitle>
                                <CardDescription
                                    >Item classification</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <PieChart
                                    v-if="inventoryData?.abc_analysis"
                                    :data="[
                                        {
                                            name: 'A Items (High Value)',
                                            value: inventoryData.abc_analysis
                                                .A_items,
                                        },
                                        {
                                            name: 'B Items (Medium Value)',
                                            value: inventoryData.abc_analysis
                                                .B_items,
                                        },
                                        {
                                            name: 'C Items (Low Value)',
                                            value: inventoryData.abc_analysis
                                                .C_items,
                                        },
                                    ]"
                                    :loading="loading"
                                    height="350px"
                                />
                                <div
                                    v-else
                                    class="h-[350px] animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Top Items by Value</CardTitle>
                                <CardDescription
                                    >Highest value items</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <BarChart
                                    v-if="inventoryData?.top_items"
                                    :x-data="
                                        inventoryData.top_items.items || []
                                    "
                                    :series="[
                                        {
                                            name: 'Value',
                                            data:
                                                inventoryData.top_items.values?.map(
                                                    (v: any) => Number(v),
                                                ) || [],
                                            color: '#ec4899',
                                        },
                                    ]"
                                    :loading="loading"
                                    height="350px"
                                    horizontal
                                />
                                <div
                                    v-else
                                    class="h-[350px] animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Financial Tab -->
                <TabsContent value="financial" class="space-y-4">
                    <!-- Summary Cards -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium"
                                    >Total Spending</CardTitle
                                >
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="financialData"
                                    class="text-2xl font-bold"
                                >
                                    {{
                                        formatCurrency(
                                            financialData.spending_trend?.amounts
                                                ?.map((v: any) => Number(v))
                                                .reduce(
                                                    (a: number, b: number) =>
                                                        a + b,
                                                    0,
                                                ) || 0,
                                        )
                                    }}
                                </div>
                                <div
                                    v-else
                                    class="h-8 animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium"
                                    >PR Approval Rate</CardTitle
                                >
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="financialData"
                                    class="text-2xl font-bold"
                                >
                                    {{
                                        financialData.approval_metrics
                                            ?.purchase_requests
                                            ?.approval_rate || 0
                                    }}%
                                </div>
                                <div
                                    v-else
                                    class="h-8 animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium"
                                    >Avg Approval Time</CardTitle
                                >
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="financialData"
                                    class="text-2xl font-bold"
                                >
                                    {{
                                        financialData.approval_metrics
                                            ?.purchase_requests
                                            ?.avg_hours_to_approve || 0
                                    }}h
                                </div>
                                <div
                                    v-else
                                    class="h-8 animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Charts -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Monthly Spending Trend</CardTitle>
                                <CardDescription
                                    >Spending over time</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <LineChart
                                    v-if="financialData?.spending_trend"
                                    :x-data="
                                        financialData.spending_trend.months ||
                                        []
                                    "
                                    :series="spendingTrendSeries"
                                    :loading="loading"
                                    height="350px"
                                />
                                <div
                                    v-else
                                    class="h-[350px] animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Budget vs Actual</CardTitle>
                                <CardDescription
                                    >Department comparison</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <BarChart
                                    v-if="financialData?.budget_vs_actual"
                                    :x-data="
                                        financialData.budget_vs_actual
                                            .departments || []
                                    "
                                    :series="budgetChartSeries"
                                    :loading="loading"
                                    height="350px"
                                />
                                <div
                                    v-else
                                    class="h-[350px] animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Payment Status</CardTitle>
                                <CardDescription
                                    >Current payment status</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <PieChart
                                    v-if="paymentStatusData.length > 0"
                                    :data="paymentStatusData"
                                    :loading="loading"
                                    height="350px"
                                    donut
                                />
                                <div
                                    v-else
                                    class="h-[350px] animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Spend by Category</CardTitle>
                                <CardDescription
                                    >Top categories by spend</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <BarChart
                                    v-if="financialData?.spend_by_category"
                                    :x-data="
                                        financialData.spend_by_category
                                            .categories || []
                                    "
                                    :series="[
                                        {
                                            name: 'Spending',
                                            data:
                                                financialData.spend_by_category.amounts?.map(
                                                    (v: any) => Number(v),
                                                ) || [],
                                            color: '#f97316',
                                        },
                                    ]"
                                    :loading="loading"
                                    height="350px"
                                    horizontal
                                />
                                <div
                                    v-else
                                    class="h-[350px] animate-pulse rounded bg-muted"
                                ></div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
