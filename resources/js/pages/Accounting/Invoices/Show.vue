<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useRoute } from '@/composables/useRoute';
import AuthenticatedLayout from '@/layouts/AuthLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle,
    DollarSign,
    Download,
    Edit,
    FileText,
    Send,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    invoice: {
        id: number;
        internal_number: string;
        invoice_number: string;
        invoice_date: string;
        due_date: string;
        status: {
            value: string;
            label: string;
            color: string;
        };
        matching_status: {
            value: string;
            label: string;
            color: string;
        };
        payment_status: {
            value: string;
            label: string;
            color: string;
        };
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
            overall_status: {
                value: string;
                label: string;
                color: string;
            };
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
    };
}

const props = defineProps<Props>();

const showCancelDialog = ref(false);
const cancellationReason = ref('');

// Format currency
const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
};

// Submit invoice for matching
const submitInvoice = () => {
    if (confirm('Submit invoice untuk matching?')) {
        router.post(
            useRoute('accounting.invoices.submit', props.invoice.id),
            {},
            {
                preserveScroll: true,
            },
        );
    }
};

// Run matching
const runMatching = () => {
    if (confirm('Jalankan 3-way matching untuk invoice ini?')) {
        router.post(
            useRoute('accounting.invoices.matching.run', props.invoice.id),
            {},
            {
                preserveScroll: true,
            },
        );
    }
};

// Cancel invoice
const cancelInvoice = () => {
    if (!cancellationReason.value) {
        alert('Alasan pembatalan harus diisi');
        return;
    }

    router.post(
        useRoute('accounting.invoices.cancel', props.invoice.id),
        {
            cancellation_reason: cancellationReason.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showCancelDialog.value = false;
                cancellationReason.value = '';
            },
        },
    );
};

// Delete invoice
const deleteInvoice = () => {
    if (confirm(`Hapus invoice ${props.invoice.internal_number}?`)) {
        router.delete(
            useRoute('accounting.invoices.destroy', props.invoice.id),
        );
    }
};

// Download files
const downloadInvoiceFile = () => {
    window.open(
        useRoute('accounting.invoices.download.invoice', props.invoice.id),
        '_blank',
    );
};

const downloadTaxInvoiceFile = () => {
    window.open(
        useRoute('accounting.invoices.download.tax-invoice', props.invoice.id),
        '_blank',
    );
};
</script>

<template>
    <Head :title="`Invoice ${invoice.internal_number}`" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="
                            router.visit(useRoute('accounting.invoices.index'))
                        "
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight">
                            {{ invoice.internal_number }}
                        </h1>
                        <p class="text-muted-foreground">
                            Invoice from {{ invoice.supplier.name }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <!-- Submit for Matching -->
                    <Button
                        v-if="invoice.status.value === 'draft'"
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
                        @click="
                            router.visit(
                                useRoute(
                                    'accounting.invoices.matching.show',
                                    invoice.id,
                                ),
                            )
                        "
                        variant="outline"
                    >
                        <FileText class="mr-2 h-4 w-4" />
                        View Matching
                    </Button>

                    <!-- Record Payment -->
                    <Button
                        v-if="
                            ['approved', 'paid'].includes(
                                invoice.status.value,
                            ) && invoice.remaining_amount > 0
                        "
                        @click="
                            router.visit(
                                useRoute(
                                    'accounting.invoices.payments.index',
                                    invoice.id,
                                ),
                            )
                        "
                    >
                        <DollarSign class="mr-2 h-4 w-4" />
                        Record Payment
                    </Button>

                    <!-- Edit -->
                    <Button
                        v-if="invoice.is_editable"
                        variant="outline"
                        @click="
                            router.visit(
                                useRoute(
                                    'accounting.invoices.edit',
                                    invoice.id,
                                ),
                            )
                        "
                    >
                        <Edit class="mr-2 h-4 w-4" />
                        Edit
                    </Button>

                    <!-- Delete -->
                    <Button
                        v-if="invoice.status.value === 'draft'"
                        variant="destructive"
                        @click="deleteInvoice"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                    </Button>

                    <!-- Cancel -->
                    <Button
                        v-if="
                            ['draft', 'submitted', 'variance'].includes(
                                invoice.status.value,
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

            <!-- Status Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium"
                            >Status</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <Badge
                            :variant="invoice.status.color as any"
                            class="text-sm"
                        >
                            {{ invoice.status.label }}
                        </Badge>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium"
                            >Matching Status</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <Badge
                            :variant="invoice.matching_status.color as any"
                            class="text-sm"
                        >
                            {{ invoice.matching_status.label }}
                        </Badge>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium"
                            >Payment Status</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <Badge
                            :variant="invoice.payment_status.color as any"
                            class="text-sm"
                        >
                            {{ invoice.payment_status.label }}
                        </Badge>
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
                                'text-red-600': invoice.remaining_amount > 0,
                            }"
                        >
                            {{ formatCurrency(invoice.remaining_amount) }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Overdue Warning -->
            <Card v-if="invoice.is_overdue" class="border-red-500 bg-red-50">
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
                                <TableHead class="text-right">Qty</TableHead>
                                <TableHead>UOM</TableHead>
                                <TableHead class="text-right"
                                    >Unit Price</TableHead
                                >
                                <TableHead class="text-right">Tax</TableHead>
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
                                :class="{ 'bg-yellow-50': line.has_variance }"
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
                                    formatCurrency(line.unit_price)
                                }}</TableCell>
                                <TableCell class="text-right">{{
                                    formatCurrency(line.tax_amount)
                                }}</TableCell>
                                <TableCell class="text-right">{{
                                    formatCurrency(line.discount_amount)
                                }}</TableCell>
                                <TableCell class="text-right font-medium">
                                    {{ formatCurrency(line.line_total) }}
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge
                                        v-if="line.has_variance"
                                        :variant="'warning' as any"
                                    >
                                        Variance Detected
                                    </Badge>
                                    <span v-else class="text-muted-foreground"
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
                            <span class="text-muted-foreground">Subtotal:</span>
                            <span class="font-medium">{{
                                formatCurrency(invoice.subtotal)
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Tax:</span>
                            <span class="font-medium">{{
                                formatCurrency(invoice.tax_amount)
                            }}</span>
                        </div>
                        <div
                            v-if="invoice.discount_amount > 0"
                            class="flex justify-between text-green-600"
                        >
                            <span>Discount:</span>
                            <span class="font-medium"
                                >-{{
                                    formatCurrency(invoice.discount_amount)
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
                                formatCurrency(invoice.other_charges)
                            }}</span>
                        </div>
                        <div class="flex justify-between border-t pt-2 text-lg">
                            <span class="font-semibold">Total Amount:</span>
                            <span class="font-bold">{{
                                formatCurrency(invoice.total_amount)
                            }}</span>
                        </div>
                        <div class="flex justify-between text-green-600">
                            <span>Paid Amount:</span>
                            <span class="font-medium">{{
                                formatCurrency(invoice.paid_amount)
                            }}</span>
                        </div>
                        <div class="flex justify-between border-t pt-2 text-lg">
                            <span class="font-semibold">Remaining Amount:</span>
                            <span
                                class="font-bold"
                                :class="{
                                    'text-red-600':
                                        invoice.remaining_amount > 0,
                                }"
                            >
                                {{ formatCurrency(invoice.remaining_amount) }}
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
        </div>
    </AuthenticatedLayout>
</template>
