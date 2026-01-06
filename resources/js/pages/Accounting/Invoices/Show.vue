<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { getInvoice } from '@/services/invoiceApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle,
    DollarSign,
    Download,
    Edit,
    FileText,
    Send,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface InvoiceData {
    id: number;
    internal_number: string;
    invoice_number: string;
    invoice_date: string;
    due_date: string | null;
    status: {
        value: string;
        label: string;
        color: string;
    } | null;
    matching_status: {
        value: string;
        label: string;
        color: string;
    } | null;
    payment_status: {
        value: string;
        label: string;
        color: string;
    } | null;
    supplier: {
        id: number;
        code: string;
        name: string;
    };
    purchase_order: {
        id: number;
        po_number: string;
    };
    subtotal: number;
    tax_amount: number;
    discount_amount: number;
    other_charges: number;
    total_amount: number;
    paid_amount: number;
    remaining_amount: number;
    currency: string;
    tax_invoice_number?: string;
    tax_invoice_date?: string;
    notes?: string;
    delivery_note_number?: string;
    has_invoice_file: boolean;
    has_tax_invoice_file: boolean;
    requires_approval: boolean;
    is_editable: boolean;
    can_be_matched: boolean;
    is_overdue: boolean;
    lines: Array<{
        id: number;
        item: {
            code: string;
            name: string;
        };
        uom: {
            code: string;
            name: string;
        };
        description?: string;
        invoiced_qty: number;
        unit_price: number;
        line_total: number;
        tax_amount: number;
        discount_amount: number;
        expected_qty?: number;
        expected_price?: number;
        expected_amount?: number;
        quantity_variance?: number;
        price_variance?: number;
        amount_variance?: number;
        has_variance: boolean;
    }>;
    matching_result?: {
        overall_status: string;
        total_quantity_variance: number;
        total_price_variance: number;
        total_amount_variance: number;
        matched_at: string;
    };
    payments?: Array<{
        id: number;
        payment_number: string;
        payment_date: string;
        payment_amount: number;
        payment_method: string;
        reference_number: string;
        has_payment_proof: boolean;
    }>;
    created_at: string;
    created_by?: {
        name: string;
    };
}

// Get invoice ID from route
const invoiceId = parseInt(window.location.pathname.split('/').pop() || '0');

const loading = ref(true);
const error = ref<string | null>(null);
const invoice = ref<InvoiceData | null>(null);
const showCancelDialog = ref(false);
const cancellationReason = ref('');

const title = computed(() =>
    invoice.value ? `Invoice ${invoice.value.internal_number}` : 'Invoice',
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Supplier Invoices',
        href: '/accounting/invoices',
    },
    {
        title: 'Details',
        href: '#',
    },
];

// Load invoice data
async function loadInvoice() {
    loading.value = true;
    error.value = null;

    try {
        const res = await getInvoice(invoiceId);
        invoice.value = res.data as InvoiceData;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load invoice';
    } finally {
        loading.value = false;
    }
}

// Format currency
function formatInvoiceCurrency(amount: number) {
    return formatCurrency(amount);
}

// Submit invoice for matching
async function submitInvoice() {
    if (!invoice.value) return;
    if (confirm('Submit invoice untuk matching?')) {
        try {
            router.post(
                `/accounting/invoices/${invoice.value.id}/submit`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => loadInvoice(),
                },
            );
        } catch (e: any) {
            error.value =
                e?.payload?.message ?? e?.message ?? 'Failed to submit';
        }
    }
}

// Run matching
async function runMatching() {
    if (!invoice.value) return;
    if (confirm('Jalankan 3-way matching untuk invoice ini?')) {
        try {
            router.post(
                `/accounting/invoices/${invoice.value.id}/matching/run`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => loadInvoice(),
                },
            );
        } catch (e: any) {
            error.value =
                e?.payload?.message ?? e?.message ?? 'Failed to run matching';
        }
    }
}

// Cancel invoice
async function cancelInvoice() {
    if (!invoice.value) return;
    if (!cancellationReason.value) {
        alert('Alasan pembatalan harus diisi');
        return;
    }

    try {
        router.post(
            `/accounting/invoices/${invoice.value.id}/cancel`,
            {
                cancellation_reason: cancellationReason.value,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showCancelDialog.value = false;
                    cancellationReason.value = '';
                    loadInvoice();
                },
            },
        );
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to cancel';
    }
}

// Delete invoice
function deleteInvoice() {
    if (!invoice.value) return;
    if (confirm(`Hapus invoice ${invoice.value.internal_number}?`)) {
        router.delete(`/accounting/invoices/${invoice.value.id}`);
    }
}

// Download files
function downloadInvoiceFile() {
    if (!invoice.value) return;
    window.open(
        `/accounting/invoices/${invoice.value.id}/download/invoice`,
        '_blank',
    );
}

function downloadTaxInvoiceFile() {
    if (!invoice.value) return;
    window.open(
        `/accounting/invoices/${invoice.value.id}/download/tax-invoice`,
        '_blank',
    );
}

onMounted(loadInvoice);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <!-- Loading State -->
            <div v-if="loading" class="py-12 text-center text-muted-foreground">
                Loading invoice...
            </div>

            <!-- Error State -->
            <div
                v-else-if="error"
                class="rounded-md bg-destructive/10 p-4 text-sm text-destructive"
            >
                {{ error }}
            </div>

            <!-- Invoice Content -->
            <template v-else-if="invoice">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-semibold">
                                {{ invoice.internal_number }}
                            </h1>
                            <StatusBadge
                                :status="invoice.status?.value ?? ''"
                            />
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ invoice.supplier.name }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" as-child>
                            <Link href="/accounting/invoices">Back</Link>
                        </Button>
                        <!-- Submit for Matching -->
                        <Button
                            v-if="invoice.status?.value === 'DRAFT'"
                            @click="submitInvoice"
                        >
                            <Send class="mr-2 h-4 w-4" />
                            Submit
                        </Button>

                        <!-- Run Matching -->
                        <Button
                            v-if="invoice.can_be_matched"
                            @click="runMatching"
                            variant="default"
                        >
                            <CheckCircle class="mr-2 h-4 w-4" />
                            Run Matching
                        </Button>

                        <!-- View Matching Result -->
                        <Button
                            v-if="invoice.matching_result"
                            variant="outline"
                            as-child
                        >
                            <Link
                                :href="`/accounting/invoices/${invoice.id}/matching`"
                            >
                                <FileText class="mr-2 h-4 w-4" />
                                View Matching
                            </Link>
                        </Button>

                        <!-- Record Payment -->
                        <Button
                            v-if="
                                ['APPROVED', 'PAID'].includes(
                                    invoice.status?.value || '',
                                ) && invoice.remaining_amount > 0
                            "
                            as-child
                        >
                            <Link
                                :href="`/accounting/invoices/${invoice.id}/payments`"
                            >
                                <DollarSign class="mr-2 h-4 w-4" />
                                Record Payment
                            </Link>
                        </Button>

                        <!-- Edit -->
                        <Button
                            v-if="invoice.is_editable"
                            variant="outline"
                            as-child
                        >
                            <Link
                                :href="`/accounting/invoices/${invoice.id}/edit`"
                            >
                                <Edit class="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </Button>

                        <!-- Delete -->
                        <Button
                            v-if="invoice.status?.value === 'DRAFT'"
                            variant="destructive"
                            @click="deleteInvoice"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Delete
                        </Button>

                        <!-- Cancel -->
                        <Button
                            v-if="
                                ['DRAFT', 'SUBMITTED', 'VARIANCE'].includes(
                                    invoice.status?.value || '',
                                )
                            "
                            variant="outline"
                            @click="showCancelDialog = true"
                        >
                            <XCircle class="mr-2 h-4 w-4" />
                            Cancel
                        </Button>
                    </div>
                </div>

                <!-- Status & Summary Cards -->
                <div class="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium"
                                >Status</CardTitle
                            >
                        </CardHeader>
                        <CardContent>
                            <StatusBadge
                                :status="invoice.status?.value ?? ''"
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium"
                                >Matching Status</CardTitle
                            >
                        </CardHeader>
                        <CardContent>
                            <StatusBadge
                                v-if="invoice.matching_status"
                                :status="invoice.matching_status.value"
                            />
                            <span v-else class="text-sm text-muted-foreground"
                                >-</span
                            >
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium"
                                >Payment Status</CardTitle
                            >
                        </CardHeader>
                        <CardContent>
                            <StatusBadge
                                v-if="invoice.payment_status"
                                :status="invoice.payment_status.value"
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium"
                                >Remaining Amount</CardTitle
                            >
                        </CardHeader>
                        <CardContent>
                            <p
                                class="text-2xl font-bold"
                                :class="{
                                    'text-red-600':
                                        invoice.remaining_amount > 0,
                                }"
                            >
                                {{
                                    formatInvoiceCurrency(
                                        invoice.remaining_amount,
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Overdue Warning -->
                <Card
                    v-if="invoice.is_overdue"
                    class="border-red-500 bg-red-50"
                >
                    <CardContent class="flex items-center gap-2 pt-6">
                        <AlertTriangle class="h-5 w-5 text-red-600" />
                        <p class="font-semibold text-red-600">
                            Invoice ini sudah melewati tanggal jatuh tempo!
                        </p>
                    </CardContent>
                </Card>

                <!-- Invoice Details -->
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Basic Info -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Invoice Information</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-sm text-muted-foreground"
                                    >Internal Number:</span
                                >
                                <span class="col-span-2 font-medium">{{
                                    invoice.internal_number
                                }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-sm text-muted-foreground"
                                    >Invoice Number:</span
                                >
                                <span class="col-span-2 font-medium">{{
                                    invoice.invoice_number
                                }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-sm text-muted-foreground"
                                    >Invoice Date:</span
                                >
                                <span class="col-span-2">{{
                                    invoice.invoice_date
                                }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-sm text-muted-foreground"
                                    >Due Date:</span
                                >
                                <span
                                    class="col-span-2"
                                    :class="{
                                        'font-semibold text-red-600':
                                            invoice.is_overdue,
                                    }"
                                >
                                    {{ invoice.due_date }}
                                </span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-sm text-muted-foreground"
                                    >PO Number:</span
                                >
                                <span class="col-span-2">{{
                                    invoice.purchase_order.po_number
                                }}</span>
                            </div>
                            <div
                                v-if="invoice.delivery_note_number"
                                class="grid grid-cols-3 gap-2"
                            >
                                <span class="text-sm text-muted-foreground"
                                    >Delivery Note:</span
                                >
                                <span class="col-span-2">{{
                                    invoice.delivery_note_number
                                }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Supplier & Tax Info -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Supplier & Tax Information</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-sm text-muted-foreground"
                                    >Supplier:</span
                                >
                                <span class="col-span-2 font-medium">{{
                                    invoice.supplier.name
                                }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-sm text-muted-foreground"
                                    >Supplier Code:</span
                                >
                                <span class="col-span-2">{{
                                    invoice.supplier.code
                                }}</span>
                            </div>
                            <div
                                v-if="invoice.tax_invoice_number"
                                class="grid grid-cols-3 gap-2"
                            >
                                <span class="text-sm text-muted-foreground"
                                    >Tax Invoice No:</span
                                >
                                <span class="col-span-2">{{
                                    invoice.tax_invoice_number
                                }}</span>
                            </div>
                            <div
                                v-if="invoice.tax_invoice_date"
                                class="grid grid-cols-3 gap-2"
                            >
                                <span class="text-sm text-muted-foreground"
                                    >Tax Invoice Date:</span
                                >
                                <span class="col-span-2">{{
                                    invoice.tax_invoice_date
                                }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Invoice Lines -->
                <Card>
                    <CardHeader>
                        <CardTitle>Invoice Lines</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead class="text-right"
                                        >Qty</TableHead
                                    >
                                    <TableHead>UOM</TableHead>
                                    <TableHead class="text-right"
                                        >Unit Price</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Tax</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Discount</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Line Total</TableHead
                                    >
                                    <TableHead class="text-center"
                                        >Variance</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="line in invoice.lines"
                                    :key="line.id"
                                    :class="{
                                        'bg-yellow-50': line.has_variance,
                                    }"
                                >
                                    <TableCell>
                                        <div class="text-sm">
                                            <div class="font-medium">
                                                {{ line.item.name }}
                                            </div>
                                            <div class="text-muted-foreground">
                                                {{ line.item.code }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>{{
                                        line.description || '-'
                                    }}</TableCell>
                                    <TableCell class="text-right">{{
                                        line.invoiced_qty
                                    }}</TableCell>
                                    <TableCell>{{ line.uom.code }}</TableCell>
                                    <TableCell class="text-right">{{
                                        formatInvoiceCurrency(line.unit_price)
                                    }}</TableCell>
                                    <TableCell class="text-right">{{
                                        formatInvoiceCurrency(line.tax_amount)
                                    }}</TableCell>
                                    <TableCell class="text-right">{{
                                        formatInvoiceCurrency(
                                            line.discount_amount,
                                        )
                                    }}</TableCell>
                                    <TableCell class="text-right font-medium">
                                        {{
                                            formatInvoiceCurrency(
                                                line.line_total,
                                            )
                                        }}
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <StatusBadge
                                            v-if="line.has_variance"
                                            status="VARIANCE"
                                        />
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >-</span
                                        >
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <!-- Financial Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle>Financial Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Subtotal:</span
                                >
                                <span class="font-medium">{{
                                    formatInvoiceCurrency(invoice.subtotal)
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Tax:</span>
                                <span class="font-medium">{{
                                    formatInvoiceCurrency(invoice.tax_amount)
                                }}</span>
                            </div>
                            <div
                                v-if="invoice.discount_amount > 0"
                                class="flex justify-between text-green-600"
                            >
                                <span>Discount:</span>
                                <span class="font-medium"
                                    >-{{
                                        formatInvoiceCurrency(
                                            invoice.discount_amount,
                                        )
                                    }}</span
                                >
                            </div>
                            <div
                                v-if="invoice.other_charges > 0"
                                class="flex justify-between"
                            >
                                <span class="text-muted-foreground"
                                    >Other Charges:</span
                                >
                                <span class="font-medium">{{
                                    formatInvoiceCurrency(invoice.other_charges)
                                }}</span>
                            </div>
                            <div
                                class="flex justify-between border-t pt-2 text-lg"
                            >
                                <span class="font-semibold">Total Amount:</span>
                                <span class="font-bold">{{
                                    formatInvoiceCurrency(invoice.total_amount)
                                }}</span>
                            </div>
                            <div class="flex justify-between text-green-600">
                                <span>Paid Amount:</span>
                                <span class="font-medium">{{
                                    formatInvoiceCurrency(invoice.paid_amount)
                                }}</span>
                            </div>
                            <div
                                class="flex justify-between border-t pt-2 text-lg"
                            >
                                <span class="font-semibold"
                                    >Remaining Amount:</span
                                >
                                <span
                                    class="font-bold"
                                    :class="{
                                        'text-red-600':
                                            invoice.remaining_amount > 0,
                                    }"
                                >
                                    {{
                                        formatInvoiceCurrency(
                                            invoice.remaining_amount,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Files & Notes -->
                <div class="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Attached Files</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <div
                                v-if="invoice.has_invoice_file"
                                class="flex items-center justify-between"
                            >
                                <span>Invoice File</span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="downloadInvoiceFile"
                                >
                                    <Download class="mr-2 h-4 w-4" />
                                    Download
                                </Button>
                            </div>
                            <div
                                v-if="invoice.has_tax_invoice_file"
                                class="flex items-center justify-between"
                            >
                                <span>Tax Invoice File</span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="downloadTaxInvoiceFile"
                                >
                                    <Download class="mr-2 h-4 w-4" />
                                    Download
                                </Button>
                            </div>
                            <p
                                v-if="
                                    !invoice.has_invoice_file &&
                                    !invoice.has_tax_invoice_file
                                "
                                class="text-muted-foreground"
                            >
                                No files attached
                            </p>
                        </CardContent>
                    </Card>

                    <Card v-if="invoice.notes">
                        <CardHeader>
                            <CardTitle>Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm">{{ invoice.notes }}</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Cancel Dialog -->
                <div
                    v-if="showCancelDialog"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                >
                    <Card class="w-full max-w-md">
                        <CardHeader>
                            <CardTitle>Cancel Invoice</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <label class="text-sm font-medium"
                                    >Cancellation Reason</label
                                >
                                <textarea
                                    v-model="cancellationReason"
                                    class="mt-1 w-full rounded-md border p-2"
                                    rows="4"
                                    placeholder="Enter reason for cancellation..."
                                />
                            </div>
                            <div class="flex justify-end gap-2">
                                <Button
                                    variant="outline"
                                    @click="showCancelDialog = false"
                                >
                                    Close
                                </Button>
                                <Button
                                    variant="destructive"
                                    @click="cancelInvoice"
                                >
                                    Cancel Invoice
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
