<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    cancelPayment,
    confirmPayment,
    getPurchaseOrderForPayment,
    type POPaymentStatus,
    type PaymentStatus,
    type PurchaseOrderSummary,
    type SupplierPaymentDto,
} from '@/services/paymentApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle,
    DollarSign,
    FileText,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ purchaseOrderId: number }>();

const loading = ref(true);
const error = ref<string | null>(null);
const purchaseOrder = ref<PurchaseOrderSummary | null>(null);

// Cancel Payment Dialog
const showCancelDialog = ref(false);
const paymentToCancel = ref<SupplierPaymentDto | null>(null);
const cancelReason = ref('');
const cancelling = ref(false);

// Confirm Payment Dialog
const showConfirmDialog = ref(false);
const paymentToConfirm = ref<SupplierPaymentDto | null>(null);
const confirming = ref(false);

const title = computed(() =>
    purchaseOrder.value
        ? `Payment - ${purchaseOrder.value.po_number}`
        : 'Payment Details',
);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await getPurchaseOrderForPayment(props.purchaseOrderId);
        purchaseOrder.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load purchase order details';
    } finally {
        loading.value = false;
    }
}

function openConfirmDialog(payment: SupplierPaymentDto) {
    paymentToConfirm.value = payment;
    showConfirmDialog.value = true;
}

async function handleConfirmPayment() {
    if (!paymentToConfirm.value) return;

    confirming.value = true;
    error.value = null;

    try {
        await confirmPayment(paymentToConfirm.value.id);
        showConfirmDialog.value = false;
        paymentToConfirm.value = null;
        await load(); // Reload data
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to confirm payment';
    } finally {
        confirming.value = false;
    }
}

function openCancelDialog(payment: SupplierPaymentDto) {
    paymentToCancel.value = payment;
    cancelReason.value = '';
    showCancelDialog.value = true;
}

async function handleCancelPayment() {
    if (!paymentToCancel.value || !cancelReason.value.trim()) return;

    cancelling.value = true;
    error.value = null;

    try {
        await cancelPayment(paymentToCancel.value.id, cancelReason.value);
        showCancelDialog.value = false;
        paymentToCancel.value = null;
        cancelReason.value = '';
        await load(); // Reload data
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to cancel payment';
    } finally {
        cancelling.value = false;
    }
}

function getPaymentStatusVariant(
    status: PaymentStatus,
): 'default' | 'secondary' | 'destructive' {
    switch (status) {
        case 'CONFIRMED':
            return 'default';
        case 'DRAFT':
            return 'secondary';
        case 'CANCELLED':
            return 'destructive';
    }
}

function getPOStatusVariant(
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

function formatDateTime(date: string | null): string {
    if (!date) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));
}

function isOverdue(): boolean {
    if (!purchaseOrder.value?.payment_due_date) return false;
    return (
        new Date(purchaseOrder.value.payment_due_date) < new Date() &&
        purchaseOrder.value.payment_status !== 'PAID'
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payments',
        href: '/payments',
    },
    {
        title: 'Purchase Order Details',
        href: '#',
    },
];

onMounted(load);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        >
            <!-- Loading State -->
            <div v-if="loading" class="text-sm text-muted-foreground">
                Loading purchase order details…
            </div>

            <!-- Error Message -->
            <div
                v-if="error"
                class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm"
            >
                {{ error }}
            </div>

            <template v-if="!loading && purchaseOrder">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold">
                            {{ purchaseOrder.po_number }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Payment tracking for this purchase order
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <Button variant="outline" as-child>
                            <Link href="/payments">Back</Link>
                        </Button>

                        <Button as-child>
                            <Link
                                :href="`/payments/purchase-orders/${purchaseOrder.id}/create`"
                            >
                                Record Payment
                            </Link>
                        </Button>
                    </div>
                </div>

                <!-- PO Overview Cards -->
                <div class="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">
                                Total Amount
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">
                                {{ formatCurrency(purchaseOrder.total_amount) }}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle
                                class="text-sm font-medium text-green-600"
                            >
                                Total Paid
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-green-600">
                                {{ formatCurrency(purchaseOrder.total_paid) }}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle
                                class="text-sm font-medium text-orange-600"
                            >
                                Outstanding
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-orange-600">
                                {{
                                    formatCurrency(
                                        purchaseOrder.outstanding_amount,
                                    )
                                }}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">
                                Payment Status
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2">
                                <Badge
                                    :variant="
                                        getPOStatusVariant(
                                            purchaseOrder.payment_status,
                                        )
                                    "
                                    class="text-sm"
                                >
                                    {{ purchaseOrder.payment_status }}
                                </Badge>
                                <AlertCircle
                                    v-if="isOverdue()"
                                    class="h-4 w-4 text-destructive"
                                />
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Due:
                                {{ formatDate(purchaseOrder.payment_due_date) }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- PO Details & Supplier Info -->
                <div class="grid gap-4 md:grid-cols-2">
                    <!-- PO Details -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Purchase Order Details</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >PO Number:</span
                                >
                                <span class="font-medium">{{
                                    purchaseOrder.po_number
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Payment Terms:</span
                                >
                                <span
                                    >{{
                                        purchaseOrder.payment_term_days
                                    }}
                                    days</span
                                >
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Submitted By:</span
                                >
                                <span>{{
                                    purchaseOrder.submitted_by?.name || '-'
                                }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Supplier Info -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Supplier Information</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Name:</span>
                                <span class="font-medium">
                                    {{ purchaseOrder.supplier?.name || '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Code:</span>
                                <span>{{
                                    purchaseOrder.supplier?.code || '-'
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Email:</span
                                >
                                <span>{{
                                    purchaseOrder.supplier?.email || '-'
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Phone:</span
                                >
                                <span>{{
                                    purchaseOrder.supplier?.phone || '-'
                                }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Goods Receipts -->
                <Card>
                    <CardHeader>
                        <CardTitle>Goods Receipts</CardTitle>
                        <CardDescription>
                            Items received for this purchase order
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="
                                purchaseOrder.goods_receipts &&
                                purchaseOrder.goods_receipts.length > 0
                            "
                            class="overflow-hidden rounded-lg border"
                        >
                            <Table>
                                <TableHeader class="bg-muted/40">
                                    <TableRow>
                                        <TableHead>GR Number</TableHead>
                                        <TableHead>Receipt Date</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Received By</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="gr in purchaseOrder.goods_receipts"
                                        :key="gr.id"
                                    >
                                        <TableCell class="font-medium">
                                            {{ gr.gr_number }}
                                        </TableCell>
                                        <TableCell>
                                            {{ formatDate(gr.received_at) }}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="default">{{
                                                gr.status
                                            }}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            {{ gr.posted_by?.name || '-' }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <p
                            v-else
                            class="py-4 text-center text-sm text-muted-foreground"
                        >
                            No goods receipts recorded yet.
                        </p>
                    </CardContent>
                </Card>

                <!-- Payment History -->
                <Card>
                    <CardHeader>
                        <CardTitle>Payment History</CardTitle>
                        <CardDescription>
                            All payments recorded for this purchase order
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="
                                purchaseOrder.payments &&
                                purchaseOrder.payments.length > 0
                            "
                            class="overflow-hidden rounded-lg border"
                        >
                            <Table>
                                <TableHeader class="bg-muted/40">
                                    <TableRow>
                                        <TableHead>Payment No.</TableHead>
                                        <TableHead>Invoice No.</TableHead>
                                        <TableHead>Payment Date</TableHead>
                                        <TableHead class="text-right"
                                            >Amount</TableHead
                                        >
                                        <TableHead>Method</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead class="text-center"
                                            >Actions</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="payment in purchaseOrder.payments"
                                        :key="payment.id"
                                    >
                                        <TableCell class="font-medium">
                                            {{ payment.payment_number }}
                                        </TableCell>
                                        <TableCell>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                {{
                                                    payment.supplier_invoice_number
                                                }}
                                                <a
                                                    v-if="
                                                        payment.supplier_invoice_file_path
                                                    "
                                                    :href="`/storage/${payment.supplier_invoice_file_path}`"
                                                    target="_blank"
                                                    class="text-primary hover:underline"
                                                >
                                                    <FileText class="h-4 w-4" />
                                                </a>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {{
                                                formatDate(payment.payment_date)
                                            }}
                                        </TableCell>
                                        <TableCell
                                            class="text-right font-medium"
                                        >
                                            {{
                                                formatCurrency(
                                                    payment.payment_amount,
                                                )
                                            }}
                                        </TableCell>
                                        <TableCell>
                                            {{ payment.payment_method }}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                :variant="
                                                    getPaymentStatusVariant(
                                                        payment.status,
                                                    )
                                                "
                                            >
                                                {{ payment.status }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div
                                                class="flex items-center justify-center gap-2"
                                            >
                                                <Button
                                                    v-if="
                                                        payment.status ===
                                                        'DRAFT'
                                                    "
                                                    size="sm"
                                                    variant="outline"
                                                    @click="
                                                        openConfirmDialog(
                                                            payment,
                                                        )
                                                    "
                                                >
                                                    <CheckCircle
                                                        class="h-4 w-4"
                                                    />
                                                    Confirm
                                                </Button>
                                                <Button
                                                    v-if="
                                                        payment.status !==
                                                        'CANCELLED'
                                                    "
                                                    size="sm"
                                                    variant="destructive"
                                                    @click="
                                                        openCancelDialog(
                                                            payment,
                                                        )
                                                    "
                                                >
                                                    <XCircle class="h-4 w-4" />
                                                    Cancel
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center gap-2 py-8"
                        >
                            <DollarSign
                                class="h-12 w-12 text-muted-foreground/40"
                            />
                            <p class="text-sm text-muted-foreground">
                                No payments recorded yet.
                            </p>
                            <Button size="sm" as-child>
                                <Link
                                    :href="`/payments/purchase-orders/${purchaseOrder.id}/create`"
                                >
                                    Record First Payment
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Payment Status History -->
                <Card
                    v-if="
                        purchaseOrder.payment_status_histories &&
                        purchaseOrder.payment_status_histories.length > 0
                    "
                >
                    <CardHeader>
                        <CardTitle>Status Change History</CardTitle>
                        <CardDescription>
                            Audit trail of payment status changes
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div
                                v-for="history in purchaseOrder.payment_status_histories"
                                :key="history.id"
                                class="flex items-center gap-4 rounded-lg border p-3 text-sm"
                            >
                                <div class="flex-1">
                                    <span class="text-muted-foreground">{{
                                        history.old_status
                                    }}</span>
                                    <span class="mx-2">→</span>
                                    <Badge
                                        :variant="
                                            getPOStatusVariant(
                                                history.new_status,
                                            )
                                        "
                                    >
                                        {{ history.new_status }}
                                    </Badge>
                                </div>
                                <div class="text-muted-foreground">
                                    {{ history.changed_by?.name || '-' }}
                                </div>
                                <div class="text-muted-foreground">
                                    {{ formatDateTime(history.changed_at) }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </div>

        <!-- Confirm Payment Dialog -->
        <Dialog v-model:open="showConfirmDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirm Payment</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to confirm this payment? This
                        action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <div v-if="paymentToConfirm" class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground"
                            >Payment Number:</span
                        >
                        <span class="font-medium">{{
                            paymentToConfirm.payment_number
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Amount:</span>
                        <span class="font-medium">{{
                            formatCurrency(paymentToConfirm.payment_amount)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Invoice:</span>
                        <span>{{
                            paymentToConfirm.supplier_invoice_number
                        }}</span>
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showConfirmDialog = false"
                        :disabled="confirming"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleConfirmPayment"
                        :disabled="confirming"
                    >
                        {{ confirming ? 'Confirming...' : 'Confirm Payment' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Cancel Payment Dialog -->
        <Dialog v-model:open="showCancelDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel Payment</DialogTitle>
                    <DialogDescription>
                        Please provide a reason for cancelling this payment.
                    </DialogDescription>
                </DialogHeader>
                <div v-if="paymentToCancel" class="space-y-4">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Payment Number:</span
                            >
                            <span class="font-medium">{{
                                paymentToCancel.payment_number
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Amount:</span>
                            <span class="font-medium">{{
                                formatCurrency(paymentToCancel.payment_amount)
                            }}</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium"
                            >Reason for Cancellation</label
                        >
                        <Textarea
                            v-model="cancelReason"
                            placeholder="Enter reason..."
                            rows="3"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showCancelDialog = false"
                        :disabled="cancelling"
                    >
                        Close
                    </Button>
                    <Button
                        variant="destructive"
                        @click="handleCancelPayment"
                        :disabled="cancelling || !cancelReason.trim()"
                    >
                        {{ cancelling ? 'Cancelling...' : 'Cancel Payment' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
