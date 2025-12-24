<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import Button from '@/components/ui/button/Button.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency, formatDateTime, formatQty } from '@/lib/format';
import {
    approvePurchaseOrder,
    cancelPurchaseOrder,
    closePurchaseOrder,
    getPurchaseOrder,
    reopenPurchaseOrder,
    sendPurchaseOrder,
    submitPurchaseOrder,
    updatePurchaseOrderDraft,
    type PurchaseOrderDto,
} from '@/services/purchaseOrderApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ purchaseOrderId: number }>();

const loading = ref(true);
const error = ref<string | null>(null);
const po = ref<PurchaseOrderDto | null>(null);

const title = computed(() =>
    po.value ? `PO ${po.value.po_number}` : 'Purchase Order',
);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await getPurchaseOrder(props.purchaseOrderId);
        po.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load purchase order';
    } finally {
        loading.value = false;
    }
}

async function submit() {
    if (!po.value) return;
    try {
        const res = await submitPurchaseOrder(po.value.id);
        po.value = res.data;
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to submit';
    }
}

async function approve() {
    if (!po.value) return;
    try {
        const res = await approvePurchaseOrder(po.value.id);
        po.value = res.data;
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to approve';
    }
}

async function send() {
    if (!po.value) return;
    try {
        const res = await sendPurchaseOrder(po.value.id);
        po.value = res.data;
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to send';
    }
}

async function close() {
    if (!po.value) return;
    try {
        const res = await closePurchaseOrder(po.value.id);
        po.value = res.data;
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to close';
    }
}

async function cancel() {
    if (!po.value) return;

    const reason = prompt('Cancel reason (optional):') ?? undefined;

    try {
        const res = await cancelPurchaseOrder(po.value.id, reason);
        po.value = res.data;
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to cancel';
    }
}

const editingDraft = ref(false);
const draftForm = ref({
    supplier_id: 0,
    currency_code: 'IDR',
    tax_rate: 0.11,
    lines: [] as Array<{
        id: number;
        unit_price: number;
        remarks?: string | null;
    }>,
});

function startEditDraft() {
    if (!po.value) return;
    editingDraft.value = true;
    draftForm.value = {
        supplier_id: po.value.supplier_id,
        currency_code: po.value.currency_code,
        tax_rate: po.value.tax_rate,
        lines: (po.value.lines ?? []).map((l) => ({
            id: l.id,
            unit_price: l.unit_price,
            remarks: l.remarks ?? null,
        })),
    };
}

function cancelEditDraft() {
    editingDraft.value = false;
}

async function saveDraft() {
    if (!po.value) return;
    try {
        const res = await updatePurchaseOrderDraft(
            po.value.id,
            draftForm.value,
        );
        po.value = res.data;
        editingDraft.value = false;
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to save draft';
    }
}

async function reopen() {
    if (!po.value) return;
    const reason = prompt('Reopen reason (optional):') ?? undefined;

    try {
        const res = await reopenPurchaseOrder(po.value.id, reason);
        po.value = res.data;
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to reopen';
    }
}

function round2(n: number): number {
    return Math.round((n + Number.EPSILON) * 100) / 100;
}

const computedSubtotal = computed(() => {
    const lines = po.value?.lines ?? [];
    return round2(
        lines.reduce(
            (sum, l) => sum + Number(l.quantity) * Number(l.unit_price),
            0,
        ),
    );
});

const computedTax = computed(() => {
    const rate = Number(po.value?.tax_rate ?? 0);
    return round2(computedSubtotal.value * rate);
});

const computedTotal = computed(() =>
    round2(computedSubtotal.value + computedTax.value),
);

const displaySubtotal = computed(
    () => po.value?.subtotal_amount ?? computedSubtotal.value,
);
const displayTax = computed(() => po.value?.tax_amount ?? computedTax.value);
const displayTotal = computed(
    () => po.value?.total_amount ?? computedTotal.value,
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Purchase Orders',
        href: '/purchase-orders',
    },
    {
        title: 'Details',
        href: '#',
    },
];

onMounted(load);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold">
                            {{ po?.po_number ?? 'PO' }}
                        </h1>
                        <StatusBadge :status="po?.status ?? ''" />
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ po?.supplier?.name ?? po?.supplier_id }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/purchase-orders">Back</Link>
                    </Button>

                    <Button
                        v-if="po?.status === 'DRAFT' && !editingDraft"
                        variant="outline"
                        @click="startEditDraft"
                        >Edit Draft</Button
                    >
                    <Button
                        v-if="po?.status === 'DRAFT' && editingDraft"
                        variant="outline"
                        @click="cancelEditDraft"
                        >Cancel Edit</Button
                    >
                    <Button
                        v-if="po?.status === 'DRAFT' && editingDraft"
                        @click="saveDraft"
                        >Save Draft</Button
                    >

                    <Button
                        v-if="po?.status === 'DRAFT' && !editingDraft"
                        @click="submit"
                        >Submit</Button
                    >

                    <Button v-if="po?.status === 'CANCELLED'" @click="reopen"
                        >Reopen to Draft</Button
                    >

                    <Button
                        v-if="
                            po &&
                            (po.status === 'SUBMITTED' ||
                                po.status === 'IN_APPROVAL')
                        "
                        @click="approve"
                        >Approve Step</Button
                    >
                    <Button
                        v-if="po?.status === 'APPROVED'"
                        variant="outline"
                        @click="send"
                        >Mark Sent</Button
                    >
                    <Button
                        v-if="po?.status === 'SENT'"
                        variant="outline"
                        @click="close"
                        >Close</Button
                    >
                    <Button
                        v-if="
                            po &&
                            po.status !== 'CANCELLED' &&
                            po.status !== 'CLOSED' &&
                            !editingDraft
                        "
                        variant="destructive"
                        @click="cancel"
                        >Cancel</Button
                    >
                </div>
            </div>

            <div
                v-if="error"
                class="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm"
            >
                {{ error }}
            </div>

            <div v-if="loading" class="mt-6 text-sm text-muted-foreground">
                Loading...
            </div>

            <div v-else-if="po" class="mt-6 space-y-6">
                <div class="rounded-lg border p-4">
                    <div class="grid gap-2 md:grid-cols-3">
                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Supplier</span
                            >
                            <template v-if="editingDraft">
                                <input
                                    v-model.number="draftForm.supplier_id"
                                    type="number"
                                    class="mt-1 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                />
                            </template>
                            <template v-else>
                                {{ po.supplier?.name ?? po.supplier_id }}
                            </template>
                        </div>

                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Currency</span
                            >
                            <template v-if="editingDraft">
                                <input
                                    v-model="draftForm.currency_code"
                                    type="text"
                                    class="mt-1 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                />
                            </template>
                            <template v-else> {{ po.currency_code }} </template>
                        </div>

                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Status</span
                            >
                            {{ po.status }}
                        </div>

                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Tax Rate</span
                            >
                            <template v-if="editingDraft">
                                <input
                                    v-model.number="draftForm.tax_rate"
                                    type="number"
                                    step="0.000001"
                                    min="0"
                                    max="1"
                                    class="mt-1 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                />
                            </template>
                            <template v-else> {{ po.tax_rate }} </template>
                        </div>

                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Subtotal</span
                            >
                            <div class="mt-1 text-sm font-medium">
                                {{
                                    formatCurrency(displaySubtotal as any, {
                                        currency: po.currency_code,
                                    })
                                }}
                            </div>
                        </div>

                        <div>
                            <span class="text-xs text-muted-foreground"
                                >PPN</span
                            >
                            <div class="mt-1 text-sm font-medium">
                                {{
                                    formatCurrency(displayTax as any, {
                                        currency: po.currency_code,
                                    })
                                }}
                            </div>
                        </div>

                        <div class="md:col-span-3">
                            <div
                                class="mt-2 flex items-center justify-between rounded-md border bg-muted/20 px-3 py-2"
                            >
                                <div class="text-sm font-semibold">
                                    Grand Total
                                </div>
                                <div class="text-base font-semibold">
                                    {{
                                        formatCurrency(displayTotal as any, {
                                            currency: po.currency_code,
                                        })
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border">
                    <div class="border-b p-4">
                        <h2 class="text-sm font-semibold">Lines</h2>
                    </div>
                    <div class="p-4">
                        <div class="overflow-hidden rounded-lg border">
                            <Table>
                                <TableHeader class="bg-muted/40">
                                    <TableRow>
                                        <TableHead>Item</TableHead>
                                        <TableHead class="text-right"
                                            >Qty</TableHead
                                        >
                                        <TableHead>UOM</TableHead>
                                        <TableHead class="text-right"
                                            >Unit Price</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="(l, idx) in po.lines"
                                        :key="l.id"
                                    >
                                        <TableCell>
                                            {{ l.item?.sku ?? l.item_id }}
                                            {{ l.item?.name ?? '' }}
                                        </TableCell>
                                        <TableCell class="text-right">{{
                                            formatQty(l.quantity)
                                        }}</TableCell>
                                        <TableCell>{{
                                            l.uom?.code ?? l.uom_id
                                        }}</TableCell>
                                        <TableCell class="text-right">
                                            <template v-if="editingDraft">
                                                <input
                                                    v-model.number="
                                                        draftForm.lines[idx]
                                                            .unit_price
                                                    "
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="w-28 rounded-md border bg-background px-2 py-1 text-right text-sm"
                                                />
                                            </template>
                                            <template v-else>
                                                {{
                                                    formatCurrency(
                                                        l.unit_price as any,
                                                        {
                                                            currency:
                                                                po.currency_code,
                                                        },
                                                    )
                                                }}</template
                                            >
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-if="po.lines.length > 0">
                                        <TableCell
                                            colspan="3"
                                            class="text-right font-medium"
                                            >Subtotal</TableCell
                                        >
                                        <TableCell
                                            class="text-right font-medium"
                                        >
                                            {{
                                                formatCurrency(
                                                    displaySubtotal as any,
                                                    {
                                                        currency:
                                                            po.currency_code,
                                                    },
                                                )
                                            }}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="po.lines.length > 0">
                                        <TableCell
                                            colspan="3"
                                            class="text-right font-medium"
                                            >PPN</TableCell
                                        >
                                        <TableCell
                                            class="text-right font-medium"
                                        >
                                            {{
                                                formatCurrency(
                                                    displayTax as any,
                                                    {
                                                        currency:
                                                            po.currency_code,
                                                    },
                                                )
                                            }}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="po.lines.length > 0">
                                        <TableCell
                                            colspan="3"
                                            class="text-right font-semibold"
                                            >Grand Total</TableCell
                                        >
                                        <TableCell
                                            class="text-right font-semibold"
                                        >
                                            {{
                                                formatCurrency(
                                                    displayTotal as any,
                                                    {
                                                        currency:
                                                            po.currency_code,
                                                    },
                                                )
                                            }}
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-if="po.lines.length === 0">
                                        <TableCell
                                            colspan="4"
                                            class="py-6 text-center text-muted-foreground"
                                        >
                                            No lines.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border">
                    <div class="border-b p-4">
                        <h2 class="text-sm font-semibold">Linked PRs</h2>
                    </div>
                    <div class="p-4">
                        <div
                            v-if="(po.purchase_requests ?? []).length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No linked PRs.
                        </div>

                        <ul v-else class="space-y-2">
                            <li
                                v-for="pr in po.purchase_requests ?? []"
                                :key="pr.id"
                                class="flex items-center justify-between gap-3 rounded-md border bg-muted/10 px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <Link
                                        :href="`/purchase-requests/${pr.id}`"
                                        class="truncate text-sm font-medium hover:underline"
                                    >
                                        {{ pr.pr_number }}
                                    </Link>
                                    <div class="text-xs text-muted-foreground">
                                        PR ID: {{ pr.id }}
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    <StatusBadge :status="pr.status" />
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="rounded-lg border">
                    <div class="border-b p-4">
                        <h2 class="text-sm font-semibold">Status History</h2>
                    </div>
                    <div class="p-4">
                        <div class="overflow-hidden rounded-lg border">
                            <Table>
                                <TableHeader class="bg-muted/40">
                                    <TableRow>
                                        <TableHead>When</TableHead>
                                        <TableHead>Action</TableHead>
                                        <TableHead>From</TableHead>
                                        <TableHead>To</TableHead>
                                        <TableHead>Actor</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="h in po.status_histories ?? []"
                                        :key="h.id"
                                    >
                                        <TableCell>{{
                                            formatDateTime(h.created_at)
                                        }}</TableCell>
                                        <TableCell>{{ h.action }}</TableCell>
                                        <TableCell>{{
                                            h.from_status ?? '-'
                                        }}</TableCell>
                                        <TableCell>{{ h.to_status }}</TableCell>
                                        <TableCell>{{
                                            h.actor?.name ??
                                            h.actor_user_id ??
                                            '-'
                                        }}</TableCell>
                                    </TableRow>
                                    <TableRow
                                        v-if="
                                            (po.status_histories ?? [])
                                                .length === 0
                                        "
                                    >
                                        <TableCell
                                            colspan="5"
                                            class="py-6 text-center text-muted-foreground"
                                        >
                                            No history.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
