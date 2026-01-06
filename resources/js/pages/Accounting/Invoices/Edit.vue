<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { getInvoice } from '@/services/invoiceApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Calculator, FileText, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface InvoiceLine {
    id?: number;
    item_id: number;
    uom_id: number;
    purchase_order_line_id: number;
    goods_receipt_line_id?: number | null;
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

interface InvoiceData {
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
    status: {
        value: string;
    };
    lines: InvoiceLine[];
    supplier: {
        id: number;
        name: string;
    };
    purchase_order: {
        id: number;
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
}

// Get invoice ID from URL
const invoiceId = parseInt(window.location.pathname.split('/')[3] || '0');

const loading = ref(true);
const error = ref<string | null>(null);
const invoice = ref<InvoiceData | null>(null);
const saving = ref(false);

const title = computed(() =>
    invoice.value
        ? `Edit Invoice ${invoice.value.internal_number}`
        : 'Edit Invoice',
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Supplier Invoices',
        href: '/accounting/invoices',
    },
    {
        title: 'Edit',
        href: '#',
    },
];

// Load invoice data
async function loadInvoice() {
    loading.value = true;
    error.value = null;

    try {
        const res = await getInvoice(invoiceId);
        invoice.value = res.data as unknown as InvoiceData;
        initializeForm();
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load invoice';
    } finally {
        loading.value = false;
    }
}

// Form data
const form = ref({
    invoice_number: '',
    invoice_date: '',
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
    lines: [] as InvoiceLine[],
    invoice_file: null as File | null,
    tax_invoice_file: null as File | null,
});

const formErrors = ref<Record<string, string>>({});

// Initialize form with invoice data
function initializeForm() {
    if (!invoice.value) return;

    form.value = {
        invoice_number: invoice.value.invoice_number,
        invoice_date: invoice.value.invoice_date,
        due_date: invoice.value.due_date,
        tax_invoice_number: invoice.value.tax_invoice_number || '',
        tax_invoice_date: invoice.value.tax_invoice_date || '',
        subtotal: invoice.value.subtotal,
        tax_amount: invoice.value.tax_amount,
        discount_amount: invoice.value.discount_amount,
        other_charges: invoice.value.other_charges,
        total_amount: invoice.value.total_amount,
        notes: invoice.value.notes || '',
        delivery_note_number: invoice.value.delivery_note_number || '',
        lines: invoice.value.lines.map((line) => ({
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
            item: line.item,
            uom: line.uom,
        })),
        invoice_file: null,
        tax_invoice_file: null,
    };

    calculateLineTotals();
}

// Calculate line total
function calculateLineTotal(index: number) {
    const line = form.value.lines[index];
    const subtotal = line.invoiced_qty * line.unit_price;
    line.line_total = subtotal + line.tax_amount - line.discount_amount;
    calculateTotals();
}

// Calculate all line totals
function calculateLineTotals() {
    form.value.lines.forEach((_, index) => {
        calculateLineTotal(index);
    });
}

// Calculate invoice totals
function calculateTotals() {
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
}

// Add new line
function addLine() {
    if (!invoice.value) return;

    const availablePoLines = invoice.value.purchase_order.lines.filter(
        (poLine) => {
            return !form.value.lines.some(
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

    form.value.lines.push({
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
        item: poLine.item,
        uom: poLine.uom,
    });

    calculateLineTotal(form.value.lines.length - 1);
}

// Remove line
function removeLine(index: number) {
    form.value.lines.splice(index, 1);
    calculateTotals();
}

// Handle file upload
function handleInvoiceFileUpload(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.value.invoice_file = target.files[0];
    }
}

function handleTaxInvoiceFileUpload(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.value.tax_invoice_file = target.files[0];
    }
}

// Submit form
async function submitForm() {
    if (!invoice.value) return;

    saving.value = true;
    formErrors.value = {};

    try {
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('invoice_number', form.value.invoice_number);
        formData.append('invoice_date', form.value.invoice_date);
        formData.append('due_date', form.value.due_date);
        formData.append('tax_invoice_number', form.value.tax_invoice_number);
        formData.append('tax_invoice_date', form.value.tax_invoice_date);
        formData.append(
            'delivery_note_number',
            form.value.delivery_note_number,
        );
        formData.append('notes', form.value.notes);
        formData.append('other_charges', form.value.other_charges.toString());

        // Add lines
        form.value.lines.forEach((line, index) => {
            if (line.id) {
                formData.append(`lines[${index}][id]`, line.id.toString());
            }
            formData.append(
                `lines[${index}][item_id]`,
                line.item_id.toString(),
            );
            formData.append(`lines[${index}][uom_id]`, line.uom_id.toString());
            formData.append(
                `lines[${index}][purchase_order_line_id]`,
                line.purchase_order_line_id.toString(),
            );
            if (line.goods_receipt_line_id) {
                formData.append(
                    `lines[${index}][goods_receipt_line_id]`,
                    line.goods_receipt_line_id.toString(),
                );
            }
            formData.append(`lines[${index}][description]`, line.description);
            formData.append(
                `lines[${index}][invoiced_qty]`,
                line.invoiced_qty.toString(),
            );
            formData.append(
                `lines[${index}][unit_price]`,
                line.unit_price.toString(),
            );
            formData.append(
                `lines[${index}][tax_amount]`,
                line.tax_amount.toString(),
            );
            formData.append(
                `lines[${index}][discount_amount]`,
                line.discount_amount.toString(),
            );
            formData.append(`lines[${index}][notes]`, line.notes || '');
        });

        // Add files if present
        if (form.value.invoice_file) {
            formData.append('invoice_file', form.value.invoice_file);
        }
        if (form.value.tax_invoice_file) {
            formData.append('tax_invoice_file', form.value.tax_invoice_file);
        }

        router.post(`/accounting/invoices/${invoice.value.id}`, formData, {
            preserveScroll: true,
            onSuccess: () => {
                router.visit(`/accounting/invoices/${invoice.value!.id}`);
            },
            onError: (errors) => {
                formErrors.value = errors;
            },
            onFinish: () => {
                saving.value = false;
            },
        });
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to update invoice';
        saving.value = false;
    }
}

// Format currency
function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
}

// Get item name
function getItemName(line: InvoiceLine) {
    if (line.item) {
        return `${line.item.code} - ${line.item.name}`;
    }
    if (!invoice.value) return '';
    const poLine = invoice.value.purchase_order.lines.find(
        (l) => l.id === line.purchase_order_line_id,
    );
    return poLine ? `${poLine.item.code} - ${poLine.item.name}` : '';
}

// Get UOM code
function getUomCode(line: InvoiceLine) {
    if (line.uom) {
        return line.uom.code;
    }
    if (!invoice.value) return '';
    const poLine = invoice.value.purchase_order.lines.find(
        (l) => l.id === line.purchase_order_line_id,
    );
    return poLine ? poLine.uom.code : '';
}

// Watch other charges for total calculation
watch(
    () => form.value.other_charges,
    () => {
        calculateTotals();
    },
);

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

            <!-- Edit Form -->
            <template v-else-if="invoice">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-semibold">
                            Edit Invoice {{ invoice.internal_number }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Modify invoice details before submission
                        </p>
                    </div>
                    <Button variant="outline" as-child>
                        <Link :href="`/accounting/invoices/${invoice.id}`"
                            >Back</Link
                        >
                    </Button>
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
                                        :value="
                                            invoice.purchase_order.po_number
                                        "
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
                                        v-if="formErrors.invoice_number"
                                        class="text-sm text-red-600"
                                    >
                                        {{ formErrors.invoice_number }}
                                    </span>
                                </div>

                                <!-- Invoice Date -->
                                <div>
                                    <Label for="invoice_date"
                                        >Invoice Date *</Label
                                    >
                                    <Input
                                        id="invoice_date"
                                        v-model="form.invoice_date"
                                        type="date"
                                        required
                                        :max="
                                            new Date()
                                                .toISOString()
                                                .split('T')[0]
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
                                                v-model.number="
                                                    line.invoiced_qty
                                                "
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="w-24 text-right"
                                                @input="
                                                    calculateLineTotal(index)
                                                "
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
                                                @input="
                                                    calculateLineTotal(index)
                                                "
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                v-model.number="line.tax_amount"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="w-32 text-right"
                                                @input="
                                                    calculateLineTotal(index)
                                                "
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
                                                @input="
                                                    calculateLineTotal(index)
                                                "
                                            />
                                        </TableCell>
                                        <TableCell
                                            class="text-right font-medium"
                                        >
                                            {{
                                                formatCurrency(line.line_total)
                                            }}
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
                                <p class="text-muted-foreground">
                                    No line items
                                </p>
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
                                    <Label for="invoice_file"
                                        >Invoice File</Label
                                    >
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
                                        v-if="formErrors.invoice_file"
                                        class="block text-sm text-red-600"
                                    >
                                        {{ formErrors.invoice_file }}
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
                                        v-if="formErrors.tax_invoice_file"
                                        class="block text-sm text-red-600"
                                    >
                                        {{ formErrors.tax_invoice_file }}
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
                            as-child
                            :disabled="saving"
                        >
                            <Link :href="`/accounting/invoices/${invoice.id}`">
                                Cancel
                            </Link>
                        </Button>
                        <Button
                            type="submit"
                            :disabled="saving || form.lines.length === 0"
                        >
                            <Save class="mr-2 h-4 w-4" />
                            {{ saving ? 'Saving...' : 'Update Invoice' }}
                        </Button>
                    </div>
                </form>
            </template>
        </div>
    </AppLayout>
</template>
