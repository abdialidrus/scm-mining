<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import AppLayout from '@/layouts/AppLayout.vue';
import {
    createInvoice,
    getCreateData,
    getPurchaseOrderDetails,
    type CreateInvoiceData,
    type PurchaseOrder,
} from '@/services/invoiceApi';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calculator,
    FileText,
    Plus,
    Save,
    Trash2,
} from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Invoices',
        href: '/accounting/invoices',
    },
    {
        title: 'Create',
        href: '/accounting/invoices/create',
    },
];

const purchaseOrders = ref<PurchaseOrder[]>([]);
const selectedPoDetails = ref<PurchaseOrder | null>(null);
const loadingPoDetails = ref(false);
const loadingData = ref(true);
const submitting = ref(false);
const error = ref<string | null>(null);

const form = ref<CreateInvoiceData>({
    purchase_order_id: 0,
    supplier_id: 0,
    invoice_number: '',
    invoice_date: new Date().toISOString().split('T')[0],
    due_date: '',
    tax_invoice_number: '',
    tax_invoice_date: '',
    subtotal: 0,
    tax_amount: 0,
    discount_amount: 0,
    other_charges: 0,
    total_amount: 0,
    notes: '',
    delivery_note_number: '',
    currency: 'IDR',
    exchange_rate: 1,
    status: 'DRAFT',
    lines: [],
});

const formErrors = ref<Record<string, string>>({});

// Load initial data
async function loadData() {
    loadingData.value = true;
    error.value = null;

    try {
        const response = (await getCreateData()) as any;
        const data = response.data;
        purchaseOrders.value = data.purchase_orders;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load data';
    } finally {
        loadingData.value = false;
    }
}

// Watch PO selection to fetch details
watch(
    () => form.value.purchase_order_id,
    async (newPoId) => {
        if (newPoId && newPoId > 0) {
            loadingPoDetails.value = true;
            try {
                const response = (await getPurchaseOrderDetails(
                    newPoId,
                )) as any;
                selectedPoDetails.value = response.data;

                // Auto-fill supplier
                form.value.supplier_id =
                    selectedPoDetails.value?.supplier_id ?? 0;

                // Pre-populate lines from PO
                if (selectedPoDetails.value?.lines) {
                    form.value.lines = selectedPoDetails.value.lines.map(
                        (line) => {
                            // Find the latest GR line for this PO line
                            const grLine = line.goods_receipt_lines?.find(
                                (gr) => gr.goods_receipt.status === 'completed',
                            );

                            return {
                                item_id: line.item_id,
                                uom_id: line.uom_id,
                                purchase_order_line_id: line.id,
                                goods_receipt_line_id: grLine?.id,
                                description: line.item.name,
                                invoiced_qty:
                                    grLine?.received_qty || line.quantity,
                                unit_price: line.unit_price,
                                line_total: 0,
                                tax_amount: 0,
                                discount_amount: 0,
                                notes: '',
                            };
                        },
                    );

                    calculateLineTotals();
                }
            } catch (err: any) {
                console.error('Failed to fetch PO details:', err);
                error.value = err?.message ?? 'Failed to fetch PO details';
            } finally {
                loadingPoDetails.value = false;
            }
        }
    },
);

// Calculate line total
const calculateLineTotal = (index: number) => {
    const line = form.value.lines[index];
    const subtotal = line.invoiced_qty * line.unit_price;
    line.line_total = subtotal + line.tax_amount - line.discount_amount;
    calculateTotals();
};

// Calculate all line totals
const calculateLineTotals = () => {
    form.value.lines.forEach((_, index) => {
        calculateLineTotal(index);
    });
};

// Calculate invoice totals
const calculateTotals = () => {
    form.value.subtotal = form.value.lines.reduce((sum, line) => {
        return sum + line.invoiced_qty * line.unit_price;
    }, 0);

    form.value.tax_amount = form.value.lines.reduce(
        (sum, line) => sum + line.tax_amount,
        0,
    );
    form.value.discount_amount = form.value.lines.reduce(
        (sum, line) => sum + line.discount_amount,
        0,
    );

    form.value.total_amount =
        form.value.subtotal +
        form.value.tax_amount -
        form.value.discount_amount +
        form.value.other_charges;
};

// Add new line
const addLine = () => {
    if (!selectedPoDetails.value) {
        alert('Please select a Purchase Order first');
        return;
    }

    form.value.lines.push({
        item_id: 0,
        uom_id: 0,
        purchase_order_line_id: 0,
        description: '',
        invoiced_qty: 0,
        unit_price: 0,
        line_total: 0,
        tax_amount: 0,
        discount_amount: 0,
        notes: '',
    });
};

// Remove line
const removeLine = (index: number) => {
    form.value.lines.splice(index, 1);
    calculateTotals();
};

// Submit form
const submitForm = async (submitForMatching: boolean = false) => {
    if (submitting.value) return;

    submitting.value = true;
    formErrors.value = {};
    error.value = null;

    try {
        const submitData = { ...form.value };
        if (submitForMatching) {
            submitData.status = 'submitted';
        }

        const response = (await createInvoice(submitData)) as any;

        // Redirect to show page
        const invoiceId = response.data.id;
        router.visit(`/accounting/invoices/${invoiceId}`);
    } catch (err: any) {
        console.error('Failed to create invoice:', err);
        error.value = err?.message ?? 'Failed to create invoice';

        if (err?.errors) {
            formErrors.value = err.errors;
        }
    } finally {
        submitting.value = false;
    }
};

// Format currency
const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
};

// Get item name by id
const getItemName = (itemId: number) => {
    if (!selectedPoDetails.value?.lines) return '';
    const line = selectedPoDetails.value.lines.find(
        (l) => l.item_id === itemId,
    );
    return line ? `${line.item.code} - ${line.item.name}` : '';
};

// Get UOM code by id
const getUomCode = (uomId: number) => {
    if (!selectedPoDetails.value?.lines) return '';
    const line = selectedPoDetails.value.lines.find((l) => l.uom_id === uomId);
    return line ? line.uom.code : '';
};

// Calculate due date (30 days from invoice date by default)
watch(
    () => form.value.invoice_date,
    (newDate) => {
        if (newDate && !form.value.due_date) {
            const invoiceDate = new Date(newDate);
            invoiceDate.setDate(invoiceDate.getDate() + 30);
            form.value.due_date = invoiceDate.toISOString().split('T')[0];
        }
    },
);

// Watch other charges for total calculation
watch(
    () => form.value.other_charges,
    () => {
        calculateTotals();
    },
);

onMounted(() => {
    loadData();
});
</script>

<template>
    <Head title="Create Invoice" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="router.visit('/accounting/invoices')"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight">
                            Create Supplier Invoice
                        </h1>
                        <p class="text-muted-foreground">
                            Create a new invoice from Purchase Order
                        </p>
                    </div>
                </div>
            </div>

            <!-- Error Alert -->
            <div
                v-if="error"
                class="rounded-lg border border-red-200 bg-red-50 p-4"
            >
                <p class="text-sm text-red-800">{{ error }}</p>
            </div>

            <!-- Loading State -->
            <div v-if="loadingData" class="py-12 text-center">
                <p class="text-muted-foreground">Loading form data...</p>
            </div>

            <form @submit.prevent="submitForm(false)" class="space-y-6">
                <!-- Invoice Header -->
                <Card>
                    <CardHeader>
                        <CardTitle>Invoice Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <!-- Purchase Order -->
                            <div>
                                <Label for="po">Purchase Order *</Label>
                                <Select
                                    v-model="form.purchase_order_id"
                                    required
                                >
                                    <SelectTrigger id="po">
                                        <SelectValue
                                            placeholder="Select Purchase Order"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="po in purchaseOrders"
                                            :key="po.id"
                                            :value="po.id.toString()"
                                        >
                                            {{ po.po_number }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <span
                                    v-if="formErrors.purchase_order_id"
                                    class="text-sm text-red-600"
                                >
                                    {{ formErrors.purchase_order_id }}
                                </span>
                            </div>

                            <!-- Supplier (Auto-filled) -->
                            <div>
                                <Label for="supplier">Supplier *</Label>
                                <Input
                                    id="supplier"
                                    :value="
                                        selectedPoDetails?.supplier?.name ||
                                        'Select PO first'
                                    "
                                    disabled
                                    class="bg-gray-100"
                                />
                            </div>

                            <!-- Invoice Number -->
                            <div>
                                <Label for="invoice_number"
                                    >Invoice Number *</Label
                                >
                                <Input
                                    id="invoice_number"
                                    v-model="form.invoice_number"
                                    type="text"
                                    required
                                    placeholder="e.g., INV-2026-001"
                                />
                                <span
                                    v-if="formErrors.invoice_number"
                                    class="text-sm text-red-600"
                                >
                                    {{ formErrors.invoice_number }}
                                </span>
                            </div>

                            <!-- Invoice Date -->
                            <div>
                                <Label for="invoice_date">Invoice Date *</Label>
                                <Input
                                    id="invoice_date"
                                    v-model="form.invoice_date"
                                    type="date"
                                    required
                                    :max="
                                        new Date().toISOString().split('T')[0]
                                    "
                                />
                                <span
                                    v-if="formErrors.invoice_date"
                                    class="text-sm text-red-600"
                                >
                                    {{ formErrors.invoice_date }}
                                </span>
                            </div>

                            <!-- Due Date -->
                            <div>
                                <Label for="due_date">Due Date *</Label>
                                <Input
                                    id="due_date"
                                    v-model="form.due_date"
                                    type="date"
                                    required
                                    :min="form.invoice_date"
                                />
                                <span
                                    v-if="formErrors.due_date"
                                    class="text-sm text-red-600"
                                >
                                    {{ formErrors.due_date }}
                                </span>
                            </div>

                            <!-- Delivery Note -->
                            <div>
                                <Label for="delivery_note"
                                    >Delivery Note Number</Label
                                >
                                <Input
                                    id="delivery_note"
                                    v-model="form.delivery_note_number"
                                    type="text"
                                    placeholder="Optional"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Tax Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Tax Invoice Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <!-- Tax Invoice Number -->
                            <div>
                                <Label for="tax_invoice_number"
                                    >Tax Invoice Number</Label
                                >
                                <Input
                                    id="tax_invoice_number"
                                    v-model="form.tax_invoice_number"
                                    type="text"
                                    placeholder="Optional"
                                />
                            </div>

                            <!-- Tax Invoice Date -->
                            <div>
                                <Label for="tax_invoice_date"
                                    >Tax Invoice Date</Label
                                >
                                <Input
                                    id="tax_invoice_date"
                                    v-model="form.tax_invoice_date"
                                    type="date"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Invoice Lines -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle>Invoice Lines</CardTitle>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addLine"
                            :disabled="!selectedPoDetails"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            Add Line
                        </Button>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="loadingPoDetails" class="py-8 text-center">
                            <p class="text-muted-foreground">
                                Loading PO details...
                            </p>
                        </div>
                        <div
                            v-else-if="form.lines.length === 0"
                            class="py-8 text-center"
                        >
                            <FileText
                                class="mx-auto mb-2 h-12 w-12 opacity-50"
                            />
                            <p class="text-muted-foreground">
                                No line items yet. Select a PO to auto-populate.
                            </p>
                        </div>
                        <Table v-else>
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
                                        >Action</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="(line, index) in form.lines"
                                    :key="index"
                                >
                                    <TableCell>
                                        <div class="text-sm">
                                            <div class="font-medium">
                                                {{ getItemName(line.item_id) }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Input
                                            v-model="line.description"
                                            type="text"
                                            placeholder="Description"
                                            class="w-full"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <Input
                                            v-model.number="line.invoiced_qty"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-24 text-right"
                                            @input="calculateLineTotal(index)"
                                        />
                                    </TableCell>
                                    <TableCell>{{
                                        getUomCode(line.uom_id)
                                    }}</TableCell>
                                    <TableCell>
                                        <Input
                                            v-model.number="line.unit_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-32 text-right"
                                            @input="calculateLineTotal(index)"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <Input
                                            v-model.number="line.tax_amount"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-32 text-right"
                                            @input="calculateLineTotal(index)"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <Input
                                            v-model.number="
                                                line.discount_amount
                                            "
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-32 text-right"
                                            @input="calculateLineTotal(index)"
                                        />
                                    </TableCell>
                                    <TableCell class="text-right font-medium">
                                        {{ formatCurrency(line.line_total) }}
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="removeLine(index)"
                                        >
                                            <Trash2
                                                class="h-4 w-4 text-red-600"
                                            />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <!-- Financial Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Calculator class="h-5 w-5" />
                            Financial Summary
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground"
                                    >Subtotal:</span
                                >
                                <span class="font-medium">{{
                                    formatCurrency(form.subtotal)
                                }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground"
                                    >Tax Amount:</span
                                >
                                <span class="font-medium">{{
                                    formatCurrency(form.tax_amount)
                                }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground"
                                    >Discount:</span
                                >
                                <span class="font-medium text-green-600"
                                    >-{{
                                        formatCurrency(form.discount_amount)
                                    }}</span
                                >
                            </div>
                            <div class="flex items-center justify-between">
                                <Label for="other_charges"
                                    >Other Charges:</Label
                                >
                                <Input
                                    id="other_charges"
                                    v-model.number="form.other_charges"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-40 text-right"
                                />
                            </div>
                            <div
                                class="flex items-center justify-between border-t pt-3 text-lg"
                            >
                                <span class="font-bold">Total Amount:</span>
                                <span class="font-bold text-blue-600">{{
                                    formatCurrency(form.total_amount)
                                }}</span>
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
                        <CardContent class="space-y-4">
                            <p class="text-sm text-muted-foreground">
                                File upload will be available in Edit page after
                                creating the invoice.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Textarea
                                v-model="form.notes"
                                rows="6"
                                placeholder="Additional notes or remarks..."
                            />
                        </CardContent>
                    </Card>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="router.visit('/accounting/invoices')"
                        :disabled="submitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="submitting || form.lines.length === 0"
                    >
                        <Save class="mr-2 h-4 w-4" />
                        Save as Draft
                    </Button>
                    <Button
                        type="button"
                        @click="submitForm(true)"
                        :disabled="submitting || form.lines.length === 0"
                    >
                        <FileText class="mr-2 h-4 w-4" />
                        {{
                            submitting ? 'Submitting...' : 'Submit for Matching'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
