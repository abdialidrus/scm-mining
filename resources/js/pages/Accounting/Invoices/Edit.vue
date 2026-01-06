<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useRoute } from '@/composables/useRoute';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import {
    ArrowLeft,
    Calculator,
    FileText,
    Plus,
    Save,
    Trash2,
} from 'lucide-vue-next';
import { onMounted, watch } from 'vue';

interface InvoiceLine {
    id?: number;
    item_id: number;
    uom_id: number;
    purchase_order_line_id: number;
    goods_receipt_line_id?: number;
    description: string;
    invoiced_qty: number;
    unit_price: number;
    line_total: number;
    tax_amount: number;
    discount_amount: number;
    notes: string;
    item?: {
        code: string;
        name: string;
    };
    uom?: {
        code: string;
        name: string;
    };
}

interface Props {
    invoice: {
        id: number;
        internal_number: string;
        purchase_order_id: number;
        supplier_id: number;
        invoice_number: string;
        invoice_date: string;
        due_date: string;
        tax_invoice_number?: string;
        tax_invoice_date?: string;
        subtotal: number;
        tax_amount: number;
        discount_amount: number;
        other_charges: number;
        total_amount: number;
        notes?: string;
        delivery_note_number?: string;
        currency: string;
        exchange_rate: number;
        status: {
            value: string;
        };
        lines: InvoiceLine[];
        supplier: {
            name: string;
        };
        purchase_order: {
            po_number: string;
            lines: Array<{
                id: number;
                item_id: number;
                item: {
                    code: string;
                    name: string;
                };
                uom_id: number;
                uom: {
                    code: string;
                    name: string;
                };
                quantity: number;
                unit_price: number;
                goods_receipt_lines?: Array<{
                    id: number;
                    received_qty: number;
                    goods_receipt: {
                        status: string;
                    };
                }>;
            }>;
        };
        has_invoice_file: boolean;
        has_tax_invoice_file: boolean;
    };
    suppliers: Array<{
        id: number;
        code: string;
        name: string;
    }>;
    purchaseOrders: Array<{
        id: number;
        po_number: string;
        supplier_id: number;
    }>;
}

const props = defineProps<Props>();

const form = useForm({
    purchase_order_id: props.invoice.purchase_order_id.toString(),
    supplier_id: props.invoice.supplier_id.toString(),
    invoice_number: props.invoice.invoice_number,
    invoice_date: props.invoice.invoice_date,
    due_date: props.invoice.due_date,
    tax_invoice_number: props.invoice.tax_invoice_number || '',
    tax_invoice_date: props.invoice.tax_invoice_date || '',
    subtotal: props.invoice.subtotal,
    tax_amount: props.invoice.tax_amount,
    discount_amount: props.invoice.discount_amount,
    other_charges: props.invoice.other_charges,
    total_amount: props.invoice.total_amount,
    notes: props.invoice.notes || '',
    delivery_note_number: props.invoice.delivery_note_number || '',
    currency: props.invoice.currency,
    exchange_rate: props.invoice.exchange_rate,
    status: props.invoice.status.value,
    lines: props.invoice.lines.map((line) => ({
        id: line.id,
        item_id: line.item_id,
        uom_id: line.uom_id,
        purchase_order_line_id: line.purchase_order_line_id,
        goods_receipt_line_id: line.goods_receipt_line_id || null,
        description: line.description,
        invoiced_qty: line.invoiced_qty,
        unit_price: line.unit_price,
        line_total: line.line_total,
        tax_amount: line.tax_amount,
        discount_amount: line.discount_amount,
        notes: line.notes,
    })),
    invoice_file: null as File | null,
    tax_invoice_file: null as File | null,
    _method: 'PUT' as const,
});

// Calculate line total
const calculateLineTotal = (index: number) => {
    const line = form.lines[index];
    const subtotal = line.invoiced_qty * line.unit_price;
    line.line_total = subtotal + line.tax_amount - line.discount_amount;
    calculateTotals();
};

// Calculate all line totals
const calculateLineTotals = () => {
    form.lines.forEach((_, index) => {
        calculateLineTotal(index);
    });
};

// Calculate invoice totals
const calculateTotals = () => {
    form.subtotal = form.lines.reduce((sum, line) => {
        return sum + line.invoiced_qty * line.unit_price;
    }, 0);

    form.tax_amount = form.lines.reduce(
        (sum, line) => sum + line.tax_amount,
        0,
    );
    form.discount_amount = form.lines.reduce(
        (sum, line) => sum + line.discount_amount,
        0,
    );

    form.total_amount =
        form.subtotal +
        form.tax_amount -
        form.discount_amount +
        form.other_charges;
};

// Add new line
const addLine = () => {
    const availablePoLines = props.invoice.purchase_order.lines.filter(
        (poLine) => {
            return !form.lines.some(
                (line) => line.purchase_order_line_id === poLine.id,
            );
        },
    );

    if (availablePoLines.length === 0) {
        alert('All PO lines have been added to the invoice');
        return;
    }

    const poLine = availablePoLines[0];
    const grLine = poLine.goods_receipt_lines?.find(
        (gr) => gr.goods_receipt.status === 'completed',
    );

    form.lines.push({
        id: undefined, // New line, no ID yet
        item_id: poLine.item_id,
        uom_id: poLine.uom_id,
        purchase_order_line_id: poLine.id,
        goods_receipt_line_id: grLine?.id || null,
        description: poLine.item.name,
        invoiced_qty: grLine?.received_qty || poLine.quantity,
        unit_price: poLine.unit_price,
        line_total: 0,
        tax_amount: 0,
        discount_amount: 0,
        notes: '',
    });

    calculateLineTotal(form.lines.length - 1);
};

// Remove line
const removeLine = (index: number) => {
    form.lines.splice(index, 1);
    calculateTotals();
};

// Handle file upload
const handleInvoiceFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.invoice_file = target.files[0];
    }
};

const handleTaxInvoiceFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.tax_invoice_file = target.files[0];
    }
};

// Submit form
const submitForm = () => {
    form.post(useRoute('accounting.invoices.update', props.invoice.id), {
        preserveScroll: true,
    });
};

// Format currency
const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
};

// Get item name
const getItemName = (line: any) => {
    if (line.item) {
        return `${line.item.code} - ${line.item.name}`;
    }
    const poLine = props.invoice.purchase_order.lines.find(
        (l) => l.id === line.purchase_order_line_id,
    );
    return poLine ? `${poLine.item.code} - ${poLine.item.name}` : '';
};

// Get UOM code
const getUomCode = (line: any) => {
    if (line.uom) {
        return line.uom.code;
    }
    const poLine = props.invoice.purchase_order.lines.find(
        (l) => l.id === line.purchase_order_line_id,
    );
    return poLine ? poLine.uom.code : '';
};

// Watch other charges for total calculation
watch(
    () => form.other_charges,
    () => {
        calculateTotals();
    },
);

// Initialize totals on mount
onMounted(() => {
    calculateLineTotals();
});
</script>

<template>
    <Head :title="`Edit Invoice ${invoice.internal_number}`" />

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
                            Edit Invoice {{ invoice.internal_number }}
                        </h1>
                        <p class="text-muted-foreground">
                            Modify invoice details before submission
                        </p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submitForm" class="space-y-6">
                <!-- Invoice Header -->
                <Card>
                    <CardHeader>
                        <CardTitle>Invoice Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <!-- Purchase Order (Read-only) -->
                            <div>
                                <Label>Purchase Order</Label>
                                <Input
                                    :value="invoice.purchase_order.po_number"
                                    disabled
                                    class="bg-gray-100"
                                />
                            </div>

                            <!-- Supplier (Read-only) -->
                            <div>
                                <Label>Supplier</Label>
                                <Input
                                    :value="invoice.supplier.name"
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
                                />
                                <span
                                    v-if="form.errors.invoice_number"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.invoice_number }}
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
                                    v-if="form.errors.invoice_date"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.invoice_date }}
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
                                    v-if="form.errors.due_date"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.due_date }}
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
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            Add Line
                        </Button>
                    </CardHeader>
                    <CardContent class="p-0">
                        <Table v-if="form.lines.length > 0">
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
                                    :key="line.id || index"
                                >
                                    <TableCell>
                                        <div class="text-sm">
                                            <div class="font-medium">
                                                {{ getItemName(line) }}
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
                                        getUomCode(line)
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
                        <div v-else class="py-8 text-center">
                            <FileText
                                class="mx-auto mb-2 h-12 w-12 opacity-50"
                            />
                            <p class="text-muted-foreground">No line items</p>
                        </div>
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
                            <div>
                                <Label for="invoice_file">Invoice File</Label>
                                <div
                                    v-if="invoice.has_invoice_file"
                                    class="mb-2 text-sm text-green-600"
                                >
                                    ✓ Current file exists (upload new to
                                    replace)
                                </div>
                                <Input
                                    id="invoice_file"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    @change="handleInvoiceFileUpload"
                                />
                                <span class="text-xs text-muted-foreground"
                                    >PDF, JPG, PNG (Max: 10MB)</span
                                >
                                <span
                                    v-if="form.errors.invoice_file"
                                    class="block text-sm text-red-600"
                                >
                                    {{ form.errors.invoice_file }}
                                </span>
                            </div>
                            <div>
                                <Label for="tax_invoice_file"
                                    >Tax Invoice File</Label
                                >
                                <div
                                    v-if="invoice.has_tax_invoice_file"
                                    class="mb-2 text-sm text-green-600"
                                >
                                    ✓ Current file exists (upload new to
                                    replace)
                                </div>
                                <Input
                                    id="tax_invoice_file"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    @change="handleTaxInvoiceFileUpload"
                                />
                                <span class="text-xs text-muted-foreground"
                                    >PDF, JPG, PNG (Max: 10MB)</span
                                >
                                <span
                                    v-if="form.errors.tax_invoice_file"
                                    class="block text-sm text-red-600"
                                >
                                    {{ form.errors.tax_invoice_file }}
                                </span>
                            </div>
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
                        @click="
                            router.visit(
                                useRoute('accounting.invoices.show', invoice.id),
                            )
                        "
                        :disabled="form.processing"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || form.lines.length === 0"
                    >
                        <Save class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Saving...' : 'Update Invoice' }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
