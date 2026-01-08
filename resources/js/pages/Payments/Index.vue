<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Input from '@/components/ui/input/Input.vue';
import Pagination from '@/components/ui/pagination/Pagination.vue';
import PaginationContent from '@/components/ui/pagination/PaginationContent.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    getOutstandingPOs,
    type PaymentStatsDto,
    type POPaymentStatus,
    type PurchaseOrderSummary,
} from '@/services/paymentApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Clock,
    DollarSign,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref<string | null>(null);
const purchaseOrders = ref<PurchaseOrderSummary[]>([]);
const stats = ref<PaymentStatsDto | null>(null);

const search = ref('');
const paymentStatus = ref<POPaymentStatus | undefined>(undefined);
const overdueOnly = ref<'all' | 'overdue'>('all');
const page = ref(1);
const perPage = ref(20);
const totalPages = ref(1);
const total = ref(0);

const hasNext = computed(() => page.value < totalPages.value);
const hasPrev = computed(() => page.value > 1);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await getOutstandingPOs({
            search: search.value || undefined,
            payment_status: paymentStatus.value,
            overdue_only: overdueOnly.value === 'overdue' || undefined,
            page: page.value,
            per_page: perPage.value,
        });

        const paginated = res.data.purchase_orders;
        purchaseOrders.value = paginated.data;
        stats.value = res.data.stats;

        page.value = paginated.current_page;
        totalPages.value = paginated.last_page;
        total.value = paginated.total;
    } catch (e: any) {
        error.value =
            e?.message ?? 'Failed to load outstanding purchase orders';
    } finally {
        loading.value = false;
    }
}

function goToPage(p: number) {
    const next = Math.max(1, Math.min(p, totalPages.value || 1));
    if (next === page.value) return;
    page.value = next;
    load();
}

function onChangePerPage() {
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

function onSearch() {
    page.value = 1;
    load();
}

function getStatusVariant(
    status: POPaymentStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'PAID':
            return 'default';
        case 'PARTIAL':
            return 'secondary';
        case 'OVERDUE':
            return 'destructive';
        case 'UNPAID':
        default:
            return 'outline';
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

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(date));
}

function isOverdue(po: PurchaseOrderSummary): boolean {
    if (!po.payment_due_date) return false;
    return (
        new Date(po.payment_due_date) < new Date() &&
        po.payment_status !== 'PAID'
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payments',
        href: '#',
    },
];

onMounted(load);
</script>

<template>
    <Head title="Payment Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        >
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Payment Management</h1>
                    <p class="text-sm text-muted-foreground">
                        Track and manage supplier payments for purchase orders.
                    </p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div v-if="stats" class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Total Outstanding
                        </CardTitle>
                        <DollarSign class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatCurrency(stats.total_outstanding) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Amount pending payment
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Overdue
                        </CardTitle>
                        <AlertCircle class="h-4 w-4 text-destructive" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.overdue_count }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ formatCurrency(stats.overdue_amount) }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            This Month Paid
                        </CardTitle>
                        <CheckCircle class="h-4 w-4 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatCurrency(stats.this_month_paid) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Confirmed payments
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Pending Confirmation
                        </CardTitle>
                        <Clock class="h-4 w-4 text-orange-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.pending_confirmation }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Draft payments
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <div class="grid items-end gap-3 md:grid-cols-12">
                <div class="md:col-span-5">
                    <label class="text-sm font-medium">Search</label>
                    <div class="mt-1">
                        <Input
                            v-model="search"
                            class="h-10"
                            placeholder="PO number or supplier name"
                        />
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-medium">Payment Status</label>
                    <div class="mt-1">
                        <Select v-model="paymentStatus">
                            <SelectTrigger class="h-10">
                                <SelectValue placeholder="All Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="UNPAID">Unpaid</SelectItem>
                                <SelectItem value="PARTIAL">Partial</SelectItem>
                                <SelectItem value="OVERDUE">Overdue</SelectItem>
                                <SelectItem value="PAID">Paid</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-medium">Filter</label>
                    <div class="mt-1">
                        <Select v-model="overdueOnly">
                            <SelectTrigger class="h-10">
                                <SelectValue placeholder="All POs" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All POs</SelectItem>
                                <SelectItem value="overdue"
                                    >Overdue Only</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="md:col-span-3">
                    <div class="mt-1 flex h-10 gap-2">
                        <Button
                            variant="outline"
                            class="h-10 flex-1"
                            @click="onSearch"
                        >
                            Search
                        </Button>
                        <Button
                            variant="ghost"
                            class="h-10"
                            @click="
                                search = '';
                                paymentStatus = undefined;
                                overdueOnly = 'all';
                                onSearch();
                            "
                        >
                            Clear
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <div
                v-if="error"
                class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm"
            >
                {{ error }}
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="text-sm text-muted-foreground">
                Loading outstanding purchase orders…
            </div>

            <!-- Table -->
            <div v-else class="overflow-hidden rounded-lg border">
                <Table>
                    <TableHeader class="bg-muted/40">
                        <TableRow>
                            <TableHead>PO Number</TableHead>
                            <TableHead>Supplier</TableHead>
                            <TableHead class="text-right"
                                >Total Amount</TableHead
                            >
                            <TableHead class="text-right">Total Paid</TableHead>
                            <TableHead class="text-right"
                                >Outstanding</TableHead
                            >
                            <TableHead>Due Date</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-center">Action</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="po in purchaseOrders"
                            :key="po.id"
                            :class="{
                                'bg-destructive/5': isOverdue(po),
                            }"
                        >
                            <TableCell class="font-medium">
                                <Link
                                    :href="`/payments/purchase-orders/${po.id}`"
                                    class="hover:underline"
                                >
                                    {{ po.po_number }}
                                </Link>
                            </TableCell>
                            <TableCell>
                                {{ po.supplier?.name || '-' }}
                            </TableCell>
                            <TableCell class="text-right font-medium">
                                {{ formatCurrency(po.total_amount) }}
                            </TableCell>
                            <TableCell class="text-right text-green-600">
                                {{ formatCurrency(po.total_paid) }}
                            </TableCell>
                            <TableCell class="text-right font-semibold">
                                {{ formatCurrency(po.outstanding_amount) }}
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <span>{{
                                        formatDate(po.payment_due_date)
                                    }}</span>
                                    <AlertCircle
                                        v-if="isOverdue(po)"
                                        class="h-4 w-4 text-destructive"
                                    />
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        getStatusVariant(po.payment_status)
                                    "
                                >
                                    {{ po.payment_status }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <Button size="sm" variant="outline" as-child>
                                    <Link
                                        :href="`/payments/purchase-orders/${po.id}/create`"
                                    >
                                        Record Payment
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="purchaseOrders.length === 0">
                            <TableCell
                                colspan="8"
                                class="py-8 text-center text-muted-foreground"
                            >
                                <div class="flex flex-col items-center gap-2">
                                    <CheckCircle
                                        class="h-12 w-12 text-muted-foreground/40"
                                    />
                                    <p>No outstanding purchase orders found.</p>
                                    <p class="text-xs">
                                        All payments are up to date!
                                    </p>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between">
                <div
                    class="hidden flex-1 text-sm text-muted-foreground lg:flex"
                >
                    Showing {{ purchaseOrders.length }} of {{ total }} purchase
                    orders
                </div>

                <div class="flex w-full items-center gap-8 lg:w-fit">
                    <div class="hidden items-center gap-2 lg:flex">
                        <label for="rows-per-page" class="text-sm font-medium">
                            Rows per page
                        </label>
                        <select
                            id="rows-per-page"
                            v-model.number="perPage"
                            class="h-8 w-20 rounded-md border bg-background px-2 text-sm"
                            @change="onChangePerPage"
                        >
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                            <option :value="50">50</option>
                        </select>
                    </div>

                    <div
                        class="flex w-fit items-center justify-center text-sm font-medium"
                    >
                        Page {{ page }} of {{ totalPages }}
                    </div>

                    <div class="ml-auto flex items-center gap-2 lg:ml-0">
                        <Pagination
                            :page="page"
                            :items-per-page="perPage"
                            :total="total"
                            :sibling-count="1"
                            :show-edges="true"
                            @update:page="goToPage"
                        >
                            <PaginationContent class="justify-end">
                                <Button
                                    variant="outline"
                                    class="hidden h-8 w-8 p-0 lg:flex"
                                    :disabled="page === 1"
                                    @click="goToPage(1)"
                                >
                                    <span class="sr-only"
                                        >Go to first page</span
                                    >
                                    <ChevronsLeft />
                                </Button>
                                <Button
                                    variant="outline"
                                    class="size-8"
                                    size="icon"
                                    :disabled="page === 1"
                                    @click="prevPage"
                                >
                                    <span class="sr-only"
                                        >Go to previous page</span
                                    >
                                    <ChevronLeft />
                                </Button>
                                <Button
                                    variant="outline"
                                    class="size-8"
                                    size="icon"
                                    :disabled="page === totalPages"
                                    @click="nextPage"
                                >
                                    <span class="sr-only">Go to next page</span>
                                    <ChevronRight />
                                </Button>
                                <Button
                                    variant="outline"
                                    class="hidden size-8 lg:flex"
                                    size="icon"
                                    :disabled="page === totalPages"
                                    @click="goToPage(totalPages)"
                                >
                                    <span class="sr-only">Go to last page</span>
                                    <ChevronsRight />
                                </Button>
                            </PaginationContent>
                        </Pagination>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
