<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Pagination from '@/components/ui/pagination/Pagination.vue';
import PaginationContent from '@/components/ui/pagination/PaginationContent.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency } from '@/lib/format';
import { listInvoices, type InvoiceDto } from '@/services/invoiceApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Edit,
    Eye,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const loading = ref(true);
const error = ref<string | null>(null);
const invoices = ref<InvoiceDto[]>([]);

const search = ref('');
const status = ref<{ value: string; label: string }>({
    value: '',
    label: 'All',
});
const matchingStatus = ref<{ value: string; label: string }>({
    value: '',
    label: 'All',
});
const paymentStatus = ref<{ value: string; label: string }>({
    value: '',
    label: 'All',
});
const page = ref(1);
const perPage = ref(10);
const hasNext = ref(false);
const totalPages = ref(1);
const hasPrev = computed(() => page.value > 1);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await listInvoices({
            search: search.value || undefined,
            status: status.value.value || undefined,
            matching_status: matchingStatus.value.value || undefined,
            payment_status: paymentStatus.value.value || undefined,
            page: page.value,
            per_page: perPage.value,
        });

        // API response structure: { data: InvoiceDto[], meta: {...} }
        invoices.value = (res.data ?? []) as InvoiceDto[];

        const meta = res.meta;
        const currentPage = Number(meta?.current_page ?? page.value);
        const lastPage = Number(meta?.last_page ?? currentPage);
        page.value = currentPage;
        totalPages.value = lastPage;
        hasNext.value = currentPage < lastPage;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load invoices';
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

function applyFilters() {
    page.value = 1;
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

async function deleteInvoice(invoice: InvoiceDto) {
    if (!confirm(`Delete invoice ${invoice.internal_number}?`)) return;

    try {
        await router.delete(`/accounting/invoices/${invoice.id}`, {
            preserveScroll: true,
            onSuccess: () => load(),
        });
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to delete invoice';
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Supplier Invoices',
        href: '/accounting/invoices',
    },
];

onMounted(load);
</script>

<template>
    <Head title="Supplier Invoices" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Supplier Invoices</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage supplier invoices, matching, and payments
                    </p>
                </div>

                <Button as-child>
                    <Link href="/accounting/invoices/create"
                        >Create Invoice</Link
                    >
                </Button>
            </div>

            <!-- Filters -->
            <div class="mt-6 grid items-end gap-3 md:grid-cols-12">
                <div class="md:col-span-3">
                    <label class="text-sm font-medium">Search</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Input
                            v-model="search"
                            class="h-10"
                            placeholder="Invoice number / supplier"
                        />
                    </div>
                </div>

                <div class="md:col-span-3">
                    <label class="text-sm font-medium">Status</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Multiselect
                            v-model="status"
                            :options="[
                                { value: '', label: 'All' },
                                { value: 'DRAFT', label: 'Draft' },
                                { value: 'SUBMITTED', label: 'Submitted' },
                                { value: 'MATCHED', label: 'Matched' },
                                { value: 'VARIANCE', label: 'Variance' },
                                { value: 'APPROVED', label: 'Approved' },
                                { value: 'PAID', label: 'Paid' },
                                { value: 'REJECTED', label: 'Rejected' },
                                { value: 'CANCELLED', label: 'Cancelled' },
                            ]"
                            track-by="value"
                            label="label"
                            placeholder="Select status"
                            :searchable="false"
                            :show-labels="false"
                        />
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-medium">Matching</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Multiselect
                            v-model="matchingStatus"
                            :options="[
                                { value: '', label: 'All' },
                                { value: 'PENDING', label: 'Pending' },
                                { value: 'MATCHED', label: 'Matched' },
                                { value: 'VARIANCE', label: 'Variance' },
                            ]"
                            track-by="value"
                            label="label"
                            placeholder="Matching status"
                            :searchable="false"
                            :show-labels="false"
                        />
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-medium">Payment</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Multiselect
                            v-model="paymentStatus"
                            :options="[
                                { value: '', label: 'All' },
                                { value: 'UNPAID', label: 'Unpaid' },
                                { value: 'PARTIAL_PAID', label: 'Partial' },
                                { value: 'PAID', label: 'Paid' },
                                { value: 'OVERDUE', label: 'Overdue' },
                            ]"
                            track-by="value"
                            label="label"
                            placeholder="Payment status"
                            :searchable="false"
                            :show-labels="false"
                        />
                    </div>
                </div>

                <div class="md:col-span-2">
                    <Button class="h-10 w-full" @click="applyFilters">
                        Apply Filters
                    </Button>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="py-12 text-center text-muted-foreground">
                Loading invoices...
            </div>

            <!-- Error State -->
            <div
                v-else-if="error"
                class="rounded-md bg-destructive/10 p-4 text-sm text-destructive"
            >
                {{ error }}
            </div>

            <!-- Invoices Table -->
            <div v-else class="mt-6 overflow-hidden rounded-lg border">
                <Table>
                    <TableHeader class="bg-muted/40">
                        <TableRow>
                            <TableHead>Internal No.</TableHead>
                            <TableHead>Invoice No.</TableHead>
                            <TableHead>Supplier</TableHead>
                            <TableHead>Invoice Date</TableHead>
                            <TableHead>Due Date</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Matching</TableHead>
                            <TableHead>Payment</TableHead>
                            <TableHead class="text-right"
                                >Total Amount</TableHead
                            >
                            <TableHead class="text-right">Remaining</TableHead>
                            <TableHead class="text-center">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="invoice in invoices"
                            :key="invoice.id"
                            :class="{
                                'bg-red-50 dark:bg-red-950/20':
                                    invoice.is_overdue,
                            }"
                        >
                            <TableCell class="font-medium">
                                {{ invoice.internal_number }}
                            </TableCell>
                            <TableCell>{{ invoice.invoice_number }}</TableCell>
                            <TableCell>
                                <div class="text-sm">
                                    <div class="font-medium">
                                        {{ invoice.supplier?.name }}
                                    </div>
                                    <div class="text-muted-foreground">
                                        {{ invoice.supplier?.code }}
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>{{ invoice.invoice_date }}</TableCell>
                            <TableCell
                                :class="{
                                    'font-semibold text-red-600':
                                        invoice.is_overdue,
                                }"
                            >
                                {{ invoice.due_date }}
                            </TableCell>
                            <TableCell>
                                <StatusBadge
                                    :status="invoice.status?.value ?? null"
                                />
                            </TableCell>
                            <TableCell>
                                <StatusBadge
                                    :status="
                                        invoice.matching_status?.value ?? null
                                    "
                                />
                            </TableCell>
                            <TableCell>
                                <StatusBadge
                                    :status="
                                        invoice.payment_status?.value ?? null
                                    "
                                />
                            </TableCell>
                            <TableCell class="text-right font-medium">
                                {{ formatCurrency(invoice.total_amount) }}
                            </TableCell>
                            <TableCell
                                class="text-right"
                                :class="{
                                    'font-semibold text-red-600':
                                        invoice.remaining_amount > 0,
                                }"
                            >
                                {{ formatCurrency(invoice.remaining_amount) }}
                            </TableCell>
                            <TableCell>
                                <div
                                    class="flex items-center justify-center gap-1"
                                >
                                    <Button size="sm" variant="ghost" as-child>
                                        <Link
                                            :href="`/accounting/invoices/${invoice.id}`"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="invoice.is_editable"
                                        size="sm"
                                        variant="ghost"
                                        as-child
                                    >
                                        <Link
                                            :href="`/accounting/invoices/${invoice.id}/edit`"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="invoice.status?.value === 'DRAFT'"
                                        size="sm"
                                        variant="ghost"
                                        @click="deleteInvoice(invoice)"
                                    >
                                        <Trash2 class="h-4 w-4 text-red-600" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="invoices.length === 0">
                            <TableCell
                                colspan="11"
                                class="py-6 text-center text-muted-foreground"
                            >
                                No invoices found
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
            <div
                v-if="!loading && invoices.length > 0"
                class="flex items-center justify-between"
            >
                <div
                    class="hidden flex-1 text-sm text-muted-foreground lg:flex"
                >
                    <!-- spacer / optional status text -->
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
                            <option :value="100">100</option>
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
                            :total="totalPages * perPage"
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
