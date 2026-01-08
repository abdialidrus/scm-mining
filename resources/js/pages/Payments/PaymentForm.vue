<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import Input from '@/components/ui/input/Input.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    createPayment,
    getPurchaseOrderForPaymentCreate,
    type PaymentMethod,
    type PurchaseOrderSummary,
} from '@/services/paymentApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertCircle, FileText, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ purchaseOrderId: number }>();

const loading = ref(true);
const submitting = ref(false);
const error = ref<string | null>(null);
const purchaseOrder = ref<PurchaseOrderSummary | null>(null);

// Form fields
const form = ref({
    supplier_invoice_number: '',
    supplier_invoice_date: '',
    supplier_invoice_amount: '',
    supplier_invoice_file: null as File | null,
    payment_date: '',
    payment_amount: '',
    payment_method: undefined as PaymentMethod | undefined,
    payment_reference: '',
    payment_proof_file: null as File | null,
    bank_account_from: '',
    bank_account_to: '',
    status: 'DRAFT' as 'DRAFT' | 'CONFIRMED',
    notes: '',
});

const validationErrors = ref<Record<string, string[]>>({});

const title = computed(() =>
    purchaseOrder.value
        ? `Record Payment - ${purchaseOrder.value.po_number}`
        : 'Record Payment',
);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await getPurchaseOrderForPaymentCreate(
            props.purchaseOrderId,
        );
        purchaseOrder.value = res.data;

        // Pre-fill some values
        form.value.payment_amount = String(
            purchaseOrder.value.outstanding_amount,
        );

        // Set default payment date to today
        const today = new Date().toISOString().split('T')[0];
        form.value.payment_date = today;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load purchase order';
    } finally {
        loading.value = false;
    }
}

function onInvoiceFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form.value.supplier_invoice_file = target.files[0];
    }
}

function onProofFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form.value.payment_proof_file = target.files[0];
    }
}

function removeInvoiceFile() {
    form.value.supplier_invoice_file = null;
    const input = document.getElementById('invoice-file') as HTMLInputElement;
    if (input) input.value = '';
}

function removeProofFile() {
    form.value.payment_proof_file = null;
    const input = document.getElementById('proof-file') as HTMLInputElement;
    if (input) input.value = '';
}

async function onSubmit() {
    if (!purchaseOrder.value) return;

    submitting.value = true;
    error.value = null;
    validationErrors.value = {};

    try {
        const formData = new FormData();
        formData.append('purchase_order_id', String(purchaseOrder.value.id));
        formData.append(
            'supplier_invoice_number',
            form.value.supplier_invoice_number,
        );
        formData.append(
            'supplier_invoice_date',
            form.value.supplier_invoice_date,
        );
        formData.append(
            'supplier_invoice_amount',
            form.value.supplier_invoice_amount,
        );

        if (form.value.supplier_invoice_file) {
            formData.append(
                'supplier_invoice_file',
                form.value.supplier_invoice_file,
            );
        }

        formData.append('payment_date', form.value.payment_date);
        formData.append('payment_amount', form.value.payment_amount);

        if (form.value.payment_method) {
            formData.append('payment_method', form.value.payment_method);
        }

        if (form.value.payment_reference) {
            formData.append('payment_reference', form.value.payment_reference);
        }

        if (form.value.payment_proof_file) {
            formData.append(
                'payment_proof_file',
                form.value.payment_proof_file,
            );
        }

        if (form.value.bank_account_from) {
            formData.append('bank_account_from', form.value.bank_account_from);
        }

        if (form.value.bank_account_to) {
            formData.append('bank_account_to', form.value.bank_account_to);
        }

        formData.append('status', form.value.status);

        if (form.value.notes) {
            formData.append('notes', form.value.notes);
        }

        const res = await createPayment(formData);

        // Redirect to PO detail page
        router.visit(`/payments/purchase-orders/${purchaseOrder.value.id}`, {
            preserveScroll: true,
        });
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to record payment';

        // Handle validation errors
        if (e?.payload?.errors) {
            validationErrors.value = e.payload.errors;
        }
    } finally {
        submitting.value = false;
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

function getFieldError(field: string): string | null {
    return validationErrors.value[field]?.[0] || null;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payments',
        href: '/payments',
    },
    {
        title: 'Record Payment',
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
                Loading purchase order…
            </div>

            <template v-if="!loading && purchaseOrder">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold">Record Payment</h1>
                        <p class="text-sm text-muted-foreground">
                            Record payment for {{ purchaseOrder.po_number }}
                        </p>
                    </div>

                    <Button variant="outline" as-child>
                        <Link
                            :href="`/payments/purchase-orders/${purchaseOrder.id}`"
                        >
                            Cancel
                        </Link>
                    </Button>
                </div>

                <!-- Error Message -->
                <div
                    v-if="error"
                    class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm"
                >
                    {{ error }}
                </div>

                <!-- PO Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle>Purchase Order Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 md:grid-cols-4">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    PO Number
                                </p>
                                <p class="font-medium">
                                    {{ purchaseOrder.po_number }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Supplier
                                </p>
                                <p class="font-medium">
                                    {{ purchaseOrder.supplier?.name }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Total Amount
                                </p>
                                <p class="font-medium">
                                    {{
                                        formatCurrency(
                                            purchaseOrder.total_amount,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Outstanding
                                </p>
                                <p
                                    class="text-lg font-semibold text-orange-600"
                                >
                                    {{
                                        formatCurrency(
                                            purchaseOrder.outstanding_amount,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Payment Form -->
                <form @submit.prevent="onSubmit" class="space-y-6">
                    <!-- Supplier Invoice Section -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Supplier Invoice Information</CardTitle>
                            <CardDescription>
                                Enter details from the supplier's invoice
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <Label for="invoice-number">
                                        Invoice Number
                                        <span class="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="invoice-number"
                                        v-model="form.supplier_invoice_number"
                                        placeholder="INV-2026-001"
                                        :class="{
                                            'border-destructive': getFieldError(
                                                'supplier_invoice_number',
                                            ),
                                        }"
                                    />
                                    <p
                                        v-if="
                                            getFieldError(
                                                'supplier_invoice_number',
                                            )
                                        "
                                        class="text-sm text-destructive"
                                    >
                                        {{
                                            getFieldError(
                                                'supplier_invoice_number',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="invoice-date">
                                        Invoice Date
                                        <span class="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="invoice-date"
                                        v-model="form.supplier_invoice_date"
                                        type="date"
                                        :class="{
                                            'border-destructive': getFieldError(
                                                'supplier_invoice_date',
                                            ),
                                        }"
                                    />
                                    <p
                                        v-if="
                                            getFieldError(
                                                'supplier_invoice_date',
                                            )
                                        "
                                        class="text-sm text-destructive"
                                    >
                                        {{
                                            getFieldError(
                                                'supplier_invoice_date',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="invoice-amount">
                                        Invoice Amount
                                        <span class="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="invoice-amount"
                                        v-model="form.supplier_invoice_amount"
                                        type="number"
                                        step="0.01"
                                        placeholder="0.00"
                                        :class="{
                                            'border-destructive': getFieldError(
                                                'supplier_invoice_amount',
                                            ),
                                        }"
                                    />
                                    <p
                                        v-if="
                                            getFieldError(
                                                'supplier_invoice_amount',
                                            )
                                        "
                                        class="text-sm text-destructive"
                                    >
                                        {{
                                            getFieldError(
                                                'supplier_invoice_amount',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="invoice-file"
                                    >Invoice File (PDF, max 10MB)</Label
                                >
                                <div class="flex items-center gap-2">
                                    <Input
                                        id="invoice-file"
                                        type="file"
                                        accept=".pdf"
                                        @change="onInvoiceFileChange"
                                        class="flex-1"
                                    />
                                    <Button
                                        v-if="form.supplier_invoice_file"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        @click="removeInvoiceFile"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                                <p
                                    v-if="form.supplier_invoice_file"
                                    class="text-sm text-muted-foreground"
                                >
                                    <FileText class="inline h-4 w-4" />
                                    {{ form.supplier_invoice_file.name }}
                                </p>
                                <p
                                    v-if="
                                        getFieldError('supplier_invoice_file')
                                    "
                                    class="text-sm text-destructive"
                                >
                                    {{ getFieldError('supplier_invoice_file') }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Payment Details Section -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Details</CardTitle>
                            <CardDescription>
                                Enter payment transaction information
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="payment-date">
                                        Payment Date
                                        <span class="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="payment-date"
                                        v-model="form.payment_date"
                                        type="date"
                                        :max="
                                            new Date()
                                                .toISOString()
                                                .split('T')[0]
                                        "
                                        :class="{
                                            'border-destructive':
                                                getFieldError('payment_date'),
                                        }"
                                    />
                                    <p
                                        v-if="getFieldError('payment_date')"
                                        class="text-sm text-destructive"
                                    >
                                        {{ getFieldError('payment_date') }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="payment-amount">
                                        Payment Amount
                                        <span class="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="payment-amount"
                                        v-model="form.payment_amount"
                                        type="number"
                                        step="0.01"
                                        placeholder="0.00"
                                        :class="{
                                            'border-destructive':
                                                getFieldError('payment_amount'),
                                        }"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Outstanding:
                                        {{
                                            formatCurrency(
                                                purchaseOrder.outstanding_amount,
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="getFieldError('payment_amount')"
                                        class="text-sm text-destructive"
                                    >
                                        {{ getFieldError('payment_amount') }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="payment-method">
                                        Payment Method
                                        <span class="text-destructive">*</span>
                                    </Label>
                                    <Select v-model="form.payment_method">
                                        <SelectTrigger
                                            id="payment-method"
                                            :class="{
                                                'border-destructive':
                                                    getFieldError(
                                                        'payment_method',
                                                    ),
                                            }"
                                        >
                                            <SelectValue
                                                placeholder="Select method"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="TRANSFER"
                                                >Bank Transfer</SelectItem
                                            >
                                            <SelectItem value="CASH"
                                                >Cash</SelectItem
                                            >
                                            <SelectItem value="CHECK"
                                                >Check</SelectItem
                                            >
                                            <SelectItem value="GIRO"
                                                >Giro</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <p
                                        v-if="getFieldError('payment_method')"
                                        class="text-sm text-destructive"
                                    >
                                        {{ getFieldError('payment_method') }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="payment-reference"
                                        >Payment Reference</Label
                                    >
                                    <Input
                                        id="payment-reference"
                                        v-model="form.payment_reference"
                                        placeholder="TRF-20260108-001"
                                    />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="proof-file"
                                    >Payment Proof (PDF/Image, max 5MB)</Label
                                >
                                <div class="flex items-center gap-2">
                                    <Input
                                        id="proof-file"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        @change="onProofFileChange"
                                        class="flex-1"
                                    />
                                    <Button
                                        v-if="form.payment_proof_file"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        @click="removeProofFile"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                                <p
                                    v-if="form.payment_proof_file"
                                    class="text-sm text-muted-foreground"
                                >
                                    <FileText class="inline h-4 w-4" />
                                    {{ form.payment_proof_file.name }}
                                </p>
                                <p
                                    v-if="getFieldError('payment_proof_file')"
                                    class="text-sm text-destructive"
                                >
                                    {{ getFieldError('payment_proof_file') }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Bank Account Section -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Bank Account Information</CardTitle>
                            <CardDescription>
                                Optional: Enter bank account details for
                                reference
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="account-from"
                                        >From Bank Account</Label
                                    >
                                    <Input
                                        id="account-from"
                                        v-model="form.bank_account_from"
                                        placeholder="BCA 1234567890"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="account-to"
                                        >To Bank Account (Supplier)</Label
                                    >
                                    <Input
                                        id="account-to"
                                        v-model="form.bank_account_to"
                                        placeholder="Mandiri 0987654321"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Additional Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Additional Information</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="status">
                                    Status
                                    <span class="text-destructive">*</span>
                                </Label>
                                <Select v-model="form.status">
                                    <SelectTrigger id="status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="DRAFT"
                                            >Draft (Save for later)</SelectItem
                                        >
                                        <SelectItem value="CONFIRMED"
                                            >Confirmed (Final)</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <p class="text-xs text-muted-foreground">
                                    <AlertCircle class="inline h-3 w-3" />
                                    Draft payments can be edited later.
                                    Confirmed payments will update PO payment
                                    status immediately.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="notes">Notes</Label>
                                <Textarea
                                    id="notes"
                                    v-model="form.notes"
                                    placeholder="Additional notes or comments..."
                                    rows="3"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            as-child
                            :disabled="submitting"
                        >
                            <Link
                                :href="`/payments/purchase-orders/${purchaseOrder.id}`"
                            >
                                Cancel
                            </Link>
                        </Button>
                        <Button type="submit" :disabled="submitting">
                            {{ submitting ? 'Saving...' : 'Record Payment' }}
                        </Button>
                    </div>
                </form>
            </template>
        </div>
    </AppLayout>
</template>
