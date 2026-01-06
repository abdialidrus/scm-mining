<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { useRoute } from '@/composables/useRoute';
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
import { Textarea } from '@/components/ui/textarea';
import AuthenticatedLayout from '@/layouts/AuthLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle,
    TrendingDown,
    TrendingUp,
    XCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    invoice: any;
    matchingResult: {
        id: number;
        overall_status: {
            value: string;
            label: string;
            color: string;
        };
        total_quantity_variance: number;
        total_price_variance: number;
        total_amount_variance: number;
        quantity_variance_percent: number;
        price_variance_percent: number;
        amount_variance_percent: number;
        matched_at: string;
        matching_details: any;
    };
    matchingDetails: any;
}

const props = defineProps<Props>();

const showApproveDialog = ref(false);
const showRejectDialog = ref(false);
const approvalNotes = ref('');
const rejectionReason = ref('');

// Format currency
const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
};

// Format percentage
const formatPercent = (value: number) => {
    return `${value > 0 ? '+' : ''}${value.toFixed(2)}%`;
};

// Approve invoice
const approveInvoice = () => {
    router.post(
        useRoute('accounting.invoices.matching.approve', props.invoice.id),
        {
            notes: approvalNotes.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showApproveDialog.value = false;
                approvalNotes.value = '';
            },
        },
    );
};

// Reject invoice
const rejectInvoice = () => {
    if (!rejectionReason.value) {
        alert('Alasan penolakan harus diisi');
        return;
    }

    router.post(
        useRoute('accounting.invoices.matching.reject', props.invoice.id),
        {
            rejection_reason: rejectionReason.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showRejectDialog.value = false;
                rejectionReason.value = '';
            },
        },
    );
};

// Get variance color
const getVarianceColor = (variance: number) => {
    if (variance === 0) return 'text-green-600';
    if (Math.abs(variance) < 5) return 'text-yellow-600';
    return 'text-red-600';
};
</script>

<template>
    <Head title="Invoice Matching Result" />

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
                            3-Way Matching Result
                        </h1>
                        <p class="text-muted-foreground">
                            Invoice {{ invoice.internal_number }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <!-- Approve Button -->
                    <Button
                        v-if="
                            invoice.requires_approval &&
                            invoice.status.value === 'variance'
                        "
                        @click="showApproveDialog = true"
                        variant="default"
                    >
                        <CheckCircle class="mr-2 h-4 w-4" />
                        Approve Variance
                    </Button>

                    <!-- Reject Button -->
                    <Button
                        v-if="
                            invoice.requires_approval &&
                            invoice.status.value === 'variance'
                        "
                        @click="showRejectDialog = true"
                        variant="destructive"
                    >
                        <XCircle class="mr-2 h-4 w-4" />
                        Reject
                    </Button>
                </div>
            </div>

            <!-- Overall Status -->
            <Card
                :class="{
                    'border-green-500 bg-green-50':
                        matchingResult.overall_status.value === 'matched',
                    'border-yellow-500 bg-yellow-50':
                        matchingResult.overall_status.value.includes(
                            'variance',
                        ),
                    'border-red-500 bg-red-50':
                        matchingResult.overall_status.value === 'over_invoiced',
                }"
            >
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <component
                                :is="
                                    matchingResult.overall_status.value ===
                                    'matched'
                                        ? CheckCircle
                                        : AlertTriangle
                                "
                                class="h-12 w-12"
                                :class="{
                                    'text-green-600':
                                        matchingResult.overall_status.value ===
                                        'matched',
                                    'text-yellow-600':
                                        matchingResult.overall_status.value.includes(
                                            'variance',
                                        ),
                                    'text-red-600':
                                        matchingResult.overall_status.value ===
                                        'over_invoiced',
                                }"
                            />
                            <div>
                                <h2 class="text-2xl font-bold">
                                    {{ matchingResult.overall_status.label }}
                                </h2>
                                <p class="text-muted-foreground">
                                    Matched at {{ matchingResult.matched_at }}
                                </p>
                            </div>
                        </div>
                        <Badge
                            :variant="matchingResult.overall_status.color as any"
                            class="px-4 py-2 text-lg"
                        >
                            {{ matchingResult.overall_status.label }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>

            <!-- Variance Summary -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <component
                                :is="
                                    matchingResult.total_quantity_variance > 0
                                        ? TrendingUp
                                        : matchingResult.total_quantity_variance <
                                            0
                                          ? TrendingDown
                                          : CheckCircle
                                "
                                class="h-4 w-4"
                            />
                            Quantity Variance
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-2xl font-bold"
                            :class="
                                getVarianceColor(
                                    matchingResult.quantity_variance_percent,
                                )
                            "
                        >
                            {{
                                formatPercent(
                                    matchingResult.quantity_variance_percent,
                                )
                            }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                matchingResult.total_quantity_variance > 0
                                    ? '+'
                                    : ''
                            }}{{ matchingResult.total_quantity_variance }} units
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <component
                                :is="
                                    matchingResult.total_price_variance > 0
                                        ? TrendingUp
                                        : matchingResult.total_price_variance <
                                            0
                                          ? TrendingDown
                                          : CheckCircle
                                "
                                class="h-4 w-4"
                            />
                            Price Variance
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-2xl font-bold"
                            :class="
                                getVarianceColor(
                                    matchingResult.price_variance_percent,
                                )
                            "
                        >
                            {{
                                formatPercent(
                                    matchingResult.price_variance_percent,
                                )
                            }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                formatCurrency(
                                    matchingResult.total_price_variance,
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <component
                                :is="
                                    matchingResult.total_amount_variance > 0
                                        ? TrendingUp
                                        : matchingResult.total_amount_variance <
                                            0
                                          ? TrendingDown
                                          : CheckCircle
                                "
                                class="h-4 w-4"
                            />
                            Amount Variance
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-2xl font-bold"
                            :class="
                                getVarianceColor(
                                    matchingResult.amount_variance_percent,
                                )
                            "
                        >
                            {{
                                formatPercent(
                                    matchingResult.amount_variance_percent,
                                )
                            }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                formatCurrency(
                                    matchingResult.total_amount_variance,
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Warning Message -->
            <Card
                v-if="invoice.requires_approval"
                class="border-yellow-500 bg-yellow-50"
            >
                <CardContent class="flex items-center gap-2 pt-6">
                    <AlertTriangle class="h-5 w-5 text-yellow-600" />
                    <p class="font-semibold text-yellow-900">
                        Invoice ini memiliki variance dan membutuhkan approval
                        dari Finance + Department Head
                    </p>
                </CardContent>
            </Card>

            <!-- Line-by-Line Matching Details -->
            <Card>
                <CardHeader>
                    <CardTitle>Line-by-Line Matching Details</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Item</TableHead>
                                <TableHead class="text-right"
                                    >Invoice Qty</TableHead
                                >
                                <TableHead class="text-right"
                                    >Expected Qty</TableHead
                                >
                                <TableHead class="text-right"
                                    >Qty Variance</TableHead
                                >
                                <TableHead class="text-right"
                                    >Invoice Price</TableHead
                                >
                                <TableHead class="text-right"
                                    >Expected Price</TableHead
                                >
                                <TableHead class="text-right"
                                    >Price Variance</TableHead
                                >
                                <TableHead class="text-right"
                                    >Amount Variance</TableHead
                                >
                                <TableHead class="text-center"
                                    >Status</TableHead
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
                                <TableCell class="text-right font-medium">{{
                                    line.invoiced_qty
                                }}</TableCell>
                                <TableCell class="text-right">{{
                                    line.expected_qty || '-'
                                }}</TableCell>
                                <TableCell
                                    class="text-right font-semibold"
                                    :class="
                                        getVarianceColor(
                                            line.quantity_variance || 0,
                                        )
                                    "
                                >
                                    {{
                                        line.quantity_variance
                                            ? (line.quantity_variance > 0
                                                  ? '+'
                                                  : '') + line.quantity_variance
                                            : '-'
                                    }}
                                </TableCell>
                                <TableCell class="text-right font-medium">{{
                                    formatCurrency(line.unit_price)
                                }}</TableCell>
                                <TableCell class="text-right">{{
                                    line.expected_price
                                        ? formatCurrency(line.expected_price)
                                        : '-'
                                }}</TableCell>
                                <TableCell
                                    class="text-right font-semibold"
                                    :class="
                                        getVarianceColor(
                                            line.price_variance || 0,
                                        )
                                    "
                                >
                                    {{
                                        line.price_variance
                                            ? formatCurrency(
                                                  line.price_variance,
                                              )
                                            : '-'
                                    }}
                                </TableCell>
                                <TableCell
                                    class="text-right font-bold"
                                    :class="
                                        getVarianceColor(
                                            line.amount_variance || 0,
                                        )
                                    "
                                >
                                    {{
                                        line.amount_variance
                                            ? formatCurrency(
                                                  line.amount_variance,
                                              )
                                            : '-'
                                    }}
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge
                                        v-if="line.has_variance"
                                        :variant="'warning' as any"
                                    >
                                        Variance
                                    </Badge>
                                    <Badge v-else :variant="'success' as any">
                                        Matched
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Matching Details (Raw) -->
            <Card v-if="matchingDetails">
                <CardHeader>
                    <CardTitle>Detailed Matching Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <pre
                        class="max-h-96 overflow-auto rounded bg-gray-100 p-4 text-xs"
                        >{{ JSON.stringify(matchingDetails, null, 2) }}</pre
                    >
                </CardContent>
            </Card>

            <!-- Approve Dialog -->
            <div
                v-if="showApproveDialog"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            >
                <Card class="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>Approve Invoice Variance</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Anda akan menyetujui invoice dengan variance.
                            Invoice akan diproses untuk pembayaran setelah
                            diapprove.
                        </p>
                        <div>
                            <label class="text-sm font-medium"
                                >Approval Notes (Optional)</label
                            >
                            <Textarea
                                v-model="approvalNotes"
                                placeholder="Enter approval notes..."
                                class="mt-1"
                                rows="4"
                            />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                variant="outline"
                                @click="showApproveDialog = false"
                            >
                                Cancel
                            </Button>
                            <Button @click="approveInvoice">
                                <CheckCircle class="mr-2 h-4 w-4" />
                                Approve
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Reject Dialog -->
            <div
                v-if="showRejectDialog"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            >
                <Card class="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>Reject Invoice</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Invoice akan ditolak dan tidak dapat diproses untuk
                            pembayaran.
                        </p>
                        <div>
                            <label class="text-sm font-medium"
                                >Rejection Reason *</label
                            >
                            <Textarea
                                v-model="rejectionReason"
                                placeholder="Enter reason for rejection..."
                                class="mt-1"
                                rows="4"
                            />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                variant="outline"
                                @click="showRejectDialog = false"
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                @click="rejectInvoice"
                            >
                                <XCircle class="mr-2 h-4 w-4" />
                                Reject Invoice
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
