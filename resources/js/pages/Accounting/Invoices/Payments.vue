<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { useRoute } from '@/composables/useRoute';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import { Textarea } from '@/components/ui/textarea';
import AuthenticatedLayout from '@/layouts/AuthLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, DollarSign, Download, Plus } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    invoice: {
        id: number;
        internal_number: string;
        invoice_number: string;
        supplier: {
            name: string;
        };
        purchase_order: {
            po_number: string;
        };
        status: {
            value: string;
            label: string;
            color: string;
        };
    };
    payments: Array<{
        id: number;
        payment_number: string;
        payment_date: string;
        payment_amount: number;
        payment_method: string;
        bank_name?: string;
        reference_number: string;
        notes?: string;
        has_payment_proof: boolean;
        created_by?: {
            name: string;
        };
        created_at: string;
    }>;
    summary: {
        total_amount: number;
        paid_amount: number;
        remaining_amount: number;
    };
}

const props = defineProps<Props>();

const showPaymentForm = ref(false);

const paymentForm = useForm({
    payment_date: new Date().toISOString().split('T')[0],
    payment_amount: 0,
    payment_method: 'transfer',
    bank_name: '',
    bank_account: '',
    reference_number: '',
    notes: '',
    payment_proof: null as File | null,
});

// Format currency
const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
};

// Handle file upload
const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        paymentForm.payment_proof = target.files[0];
    }
};

// Submit payment
const submitPayment = () => {
    paymentForm.post(
        useRoute('accounting.invoices.payments.store', props.invoice.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                showPaymentForm.value = false;
                paymentForm.reset();
            },
        },
    );
};

// Download payment proof
const downloadProof = (paymentId: number) => {
    window.open(
        useRoute('accounting.invoices.payments.download', [
            props.invoice.id,
            paymentId,
        ]),
        '_blank',
    );
};

// Get payment method label
const getPaymentMethodLabel = (method: string) => {
    const methods: Record<string, string> = {
        transfer: 'Bank Transfer',
        cash: 'Cash',
        check: 'Check',
        giro: 'Giro',
    };
    return methods[method] || method;
};
</script>

<template>
    <Head title="Payment History" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="
                            router.visit(
                                useRoute('accounting.invoices.show', invoice.id),
                            )
                        "
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight">
                            Payment History
                        </h1>
                        <p class="text-muted-foreground">
                            Invoice {{ invoice.internal_number }} -
                            {{ invoice.supplier.name }}
                        </p>
                    </div>
                </div>
                <Button
                    v-if="summary.remaining_amount > 0"
                    @click="showPaymentForm = true"
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Record Payment
                </Button>
            </div>

            <!-- Payment Summary -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium"
                            >Total Invoice Amount</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">
                            {{ formatCurrency(summary.total_amount) }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium"
                            >Paid Amount</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold text-green-600">
                            {{ formatCurrency(summary.paid_amount) }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ payments.length }} payment(s)
                        </p>
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
                            :class="
                                summary.remaining_amount > 0
                                    ? 'text-red-600'
                                    : 'text-green-600'
                            "
                        >
                            {{ formatCurrency(summary.remaining_amount) }}
                        </p>
                        <Badge
                            v-if="summary.remaining_amount === 0"
                            :variant="'success' as any"
                            class="mt-2"
                        >
                            Fully Paid
                        </Badge>
                    </CardContent>
                </Card>
            </div>

            <!-- Payment History Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Payment Records</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Payment Number</TableHead>
                                <TableHead>Payment Date</TableHead>
                                <TableHead>Payment Method</TableHead>
                                <TableHead>Bank</TableHead>
                                <TableHead>Reference</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                                <TableHead>Recorded By</TableHead>
                                <TableHead class="text-center">Proof</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="payment in payments"
                                :key="payment.id"
                            >
                                <TableCell class="font-medium">{{
                                    payment.payment_number
                                }}</TableCell>
                                <TableCell>{{
                                    payment.payment_date
                                }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline">
                                        {{
                                            getPaymentMethodLabel(
                                                payment.payment_method,
                                            )
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{
                                    payment.bank_name || '-'
                                }}</TableCell>
                                <TableCell>{{
                                    payment.reference_number
                                }}</TableCell>
                                <TableCell
                                    class="text-right font-bold text-green-600"
                                >
                                    {{ formatCurrency(payment.payment_amount) }}
                                </TableCell>
                                <TableCell>
                                    <div class="text-sm">
                                        <div>
                                            {{
                                                payment.created_by?.name || '-'
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ payment.created_at }}
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="text-center">
                                    <Button
                                        v-if="payment.has_payment_proof"
                                        size="sm"
                                        variant="ghost"
                                        @click="downloadProof(payment.id)"
                                    >
                                        <Download class="h-4 w-4" />
                                    </Button>
                                    <span
                                        v-else
                                        class="text-sm text-muted-foreground"
                                        >-</span
                                    >
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="payments.length === 0">
                                <TableCell
                                    colspan="8"
                                    class="py-8 text-center text-muted-foreground"
                                >
                                    <DollarSign
                                        class="mx-auto mb-2 h-12 w-12 opacity-50"
                                    />
                                    <p>No payments recorded yet</p>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Payment Form Dialog -->
            <div
                v-if="showPaymentForm"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50"
            >
                <Card class="m-4 w-full max-w-2xl">
                    <CardHeader>
                        <CardTitle>Record New Payment</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitPayment" class="space-y-4">
                            <!-- Payment Date & Amount -->
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium"
                                        >Payment Date *</label
                                    >
                                    <Input
                                        v-model="paymentForm.payment_date"
                                        type="date"
                                        :max="
                                            new Date()
                                                .toISOString()
                                                .split('T')[0]
                                        "
                                        required
                                        class="mt-1"
                                    />
                                    <span
                                        v-if="paymentForm.errors.payment_date"
                                        class="text-sm text-red-600"
                                    >
                                        {{ paymentForm.errors.payment_date }}
                                    </span>
                                </div>
                                <div>
                                    <label class="text-sm font-medium"
                                        >Payment Amount *</label
                                    >
                                    <Input
                                        v-model="paymentForm.payment_amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        :max="summary.remaining_amount"
                                        required
                                        class="mt-1"
                                    />
                                    <span class="text-xs text-muted-foreground">
                                        Max:
                                        {{
                                            formatCurrency(
                                                summary.remaining_amount,
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-if="paymentForm.errors.payment_amount"
                                        class="block text-sm text-red-600"
                                    >
                                        {{ paymentForm.errors.payment_amount }}
                                    </span>
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div>
                                <label class="text-sm font-medium"
                                    >Payment Method *</label
                                >
                                <Select
                                    v-model="paymentForm.payment_method"
                                    required
                                >
                                    <SelectTrigger class="mt-1">
                                        <SelectValue
                                            placeholder="Select payment method"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="transfer"
                                            >Bank Transfer</SelectItem
                                        >
                                        <SelectItem value="cash"
                                            >Cash</SelectItem
                                        >
                                        <SelectItem value="check"
                                            >Check</SelectItem
                                        >
                                        <SelectItem value="giro"
                                            >Giro</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <span
                                    v-if="paymentForm.errors.payment_method"
                                    class="text-sm text-red-600"
                                >
                                    {{ paymentForm.errors.payment_method }}
                                </span>
                            </div>

                            <!-- Bank Details -->
                            <div
                                v-if="
                                    ['transfer', 'check', 'giro'].includes(
                                        paymentForm.payment_method,
                                    )
                                "
                                class="grid gap-4 md:grid-cols-2"
                            >
                                <div>
                                    <label class="text-sm font-medium"
                                        >Bank Name *</label
                                    >
                                    <Input
                                        v-model="paymentForm.bank_name"
                                        type="text"
                                        required
                                        class="mt-1"
                                        placeholder="e.g., BCA, Mandiri"
                                    />
                                    <span
                                        v-if="paymentForm.errors.bank_name"
                                        class="text-sm text-red-600"
                                    >
                                        {{ paymentForm.errors.bank_name }}
                                    </span>
                                </div>
                                <div>
                                    <label class="text-sm font-medium"
                                        >Bank Account</label
                                    >
                                    <Input
                                        v-model="paymentForm.bank_account"
                                        type="text"
                                        class="mt-1"
                                        placeholder="Account number"
                                    />
                                </div>
                            </div>

                            <!-- Reference Number -->
                            <div>
                                <label class="text-sm font-medium"
                                    >Reference Number *</label
                                >
                                <Input
                                    v-model="paymentForm.reference_number"
                                    type="text"
                                    required
                                    class="mt-1"
                                    placeholder="Transaction reference, check number, etc."
                                />
                                <span
                                    v-if="paymentForm.errors.reference_number"
                                    class="text-sm text-red-600"
                                >
                                    {{ paymentForm.errors.reference_number }}
                                </span>
                            </div>

                            <!-- Payment Proof -->
                            <div>
                                <label class="text-sm font-medium"
                                    >Payment Proof</label
                                >
                                <Input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="mt-1"
                                    @change="handleFileUpload"
                                />
                                <span class="text-xs text-muted-foreground">
                                    Accepted: PDF, JPG, PNG (Max: 5MB)
                                </span>
                                <span
                                    v-if="paymentForm.errors.payment_proof"
                                    class="block text-sm text-red-600"
                                >
                                    {{ paymentForm.errors.payment_proof }}
                                </span>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="text-sm font-medium">Notes</label>
                                <Textarea
                                    v-model="paymentForm.notes"
                                    class="mt-1"
                                    rows="3"
                                    placeholder="Additional notes..."
                                />
                            </div>

                            <!-- Actions -->
                            <div class="flex justify-end gap-2 pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="showPaymentForm = false"
                                    :disabled="paymentForm.processing"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    :disabled="paymentForm.processing"
                                >
                                    <DollarSign class="mr-2 h-4 w-4" />
                                    {{
                                        paymentForm.processing
                                            ? 'Recording...'
                                            : 'Record Payment'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
