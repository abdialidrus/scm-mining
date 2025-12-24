<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import StatusHistoryTable from '@/components/StatusHistoryTable.vue';
import Button from '@/components/ui/button/Button.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAbilities } from '@/composables/useAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime, formatQty } from '@/lib/format';
import {
    approvePurchaseRequest,
    getPurchaseRequest,
    rejectPurchaseRequest,
    submitPurchaseRequest,
    type PurchaseRequestDto,
} from '@/services/purchaseRequestApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ purchaseRequestId: number }>();

const loading = ref(true);
const error = ref<string | null>(null);
const pr = ref<PurchaseRequestDto | null>(null);

const status = computed(() => pr.value?.status);

const rejectOpen = ref(false);
const rejectReason = ref('');
const rejectSubmitting = ref(false);
const fieldErrors = ref<Record<string, string[]>>({});

const { can } = useAbilities();

const canUpdate = computed(() => can('purchaseRequests.update'));
const canSubmit = computed(() => can('purchaseRequests.submit'));
const canApprove = computed(() => can('purchaseRequests.approve'));

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Purchase Requests',
        href: '/purchase-requests',
    },
    {
        title: 'Details',
        href: '#',
    },
];

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await getPurchaseRequest(props.purchaseRequestId);
        pr.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load purchase request';
    } finally {
        loading.value = false;
    }
}

function setApiError(e: any, fallback: string) {
    error.value = e?.payload?.message ?? e?.message ?? fallback;
    fieldErrors.value = (e?.payload?.errors ?? {}) as Record<string, string[]>;
}

async function submit() {
    if (!pr.value) return;
    try {
        await submitPurchaseRequest(pr.value.id);
        await load();
    } catch (e: any) {
        setApiError(e, 'Failed to submit');
    }
}

async function approve() {
    if (!pr.value) return;
    try {
        await approvePurchaseRequest(pr.value.id);
        await load();
    } catch (e: any) {
        setApiError(e, 'Failed to approve');
    }
}

async function confirmReject() {
    if (!pr.value) return;

    rejectSubmitting.value = true;
    error.value = null;
    fieldErrors.value = {};

    try {
        await rejectPurchaseRequest(pr.value.id, rejectReason.value.trim());
        rejectOpen.value = false;
        rejectReason.value = '';
        await load();
    } catch (e: any) {
        setApiError(e, 'Failed to reject');
    } finally {
        rejectSubmitting.value = false;
    }
}

function openReject() {
    rejectReason.value = '';
    fieldErrors.value = {};
    rejectOpen.value = true;
}

function doPrint() {
    // In browser context, Inertia page runs on client.
    globalThis?.print?.();
}

onMounted(load);
</script>

<template>
    <Head :title="pr ? `PR ${pr.pr_number}` : 'Purchase Request'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <!-- Print-only header -->
            <section v-if="pr" class="print-header hidden print:block">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="print-title">PURCHASE REQUEST</h1>
                        <div class="print-subtitle">
                            {{ pr.pr_number }}
                        </div>
                    </div>
                    <div class="text-right text-xs text-muted-foreground">
                        Printed at:
                        {{ formatDateTime(new Date().toISOString()) }}
                    </div>
                </div>

                <div class="print-meta">
                    <div>
                        <div>
                            <span class="label">Department:</span>
                            {{ pr.department?.code ?? pr.department_id }}
                        </div>
                        <div>
                            <span class="label">Requester:</span>
                            {{ pr.requester?.name ?? pr.requester_user_id }}
                        </div>
                    </div>
                    <div>
                        <div>
                            <span class="label">Status:</span> {{ pr.status }}
                        </div>
                        <div>
                            <span class="label">Created:</span>
                            {{ formatDateTime(pr.created_at) }}
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">
                        {{ pr?.pr_number ?? 'Purchase Request' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Status: {{ pr?.status ?? '-' }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/purchase-requests">Back</Link>
                    </Button>

                    <Button
                        v-if="pr && status === 'DRAFT' && canUpdate"
                        variant="outline"
                        as-child
                    >
                        <Link :href="`/purchase-requests/${pr.id}/edit`"
                            >Edit Draft</Link
                        >
                    </Button>
                </div>
            </div>

            <div v-if="loading" class="mt-6 text-sm text-muted-foreground">
                Loading…
            </div>

            <div v-else-if="pr" class="mt-6 space-y-6">
                <!-- Consistent header info card (like GR) -->
                <div v-if="pr" class="mt-6">
                    <div class="rounded-lg border p-4">
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <div class="text-sm font-semibold">
                                        Purchase Request
                                    </div>
                                    <StatusBadge :status="pr.status" />
                                </div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    PR No:
                                    <span class="font-medium text-foreground">{{
                                        pr.pr_number
                                    }}</span>
                                </div>
                            </div>

                            <div
                                class="text-right text-xs text-muted-foreground"
                            >
                                <div>
                                    Created at:
                                    <span class="text-foreground">{{
                                        formatDateTime(pr.created_at)
                                    }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-12">
                            <div class="md:col-span-6">
                                <div class="text-xs text-muted-foreground">
                                    Department
                                </div>
                                <div class="mt-1 text-sm">
                                    {{
                                        pr.department
                                            ? `${pr.department.code} — ${pr.department.name}`
                                            : pr.department_id
                                    }}
                                </div>
                            </div>

                            <div class="md:col-span-6">
                                <div class="text-xs text-muted-foreground">
                                    Requester
                                </div>
                                <div class="mt-1 text-sm">
                                    {{
                                        pr.requester?.name ??
                                        pr.requester_user_id ??
                                        '-'
                                    }}
                                </div>
                            </div>

                            <div v-if="pr.approvedBy" class="md:col-span-6">
                                <div class="text-xs text-muted-foreground">
                                    Approved By
                                </div>
                                <div class="mt-1 text-sm">
                                    {{ pr.approvedBy.name }}
                                </div>
                            </div>

                            <div class="md:col-span-12">
                                <div class="text-xs text-muted-foreground">
                                    Remarks
                                </div>
                                <div class="mt-1 text-sm">
                                    {{ pr.remarks || '-' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="print-block rounded-lg border">
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
                                        <TableHead>Remarks</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="(l, idx) in pr.lines"
                                        :key="idx"
                                    >
                                        <TableCell>
                                            <span v-if="l.item"
                                                >{{ l.item.sku }} —
                                                {{ l.item.name }}</span
                                            >
                                            <span
                                                v-else
                                                class="text-muted-foreground"
                                                >Item #{{ l.item_id }}</span
                                            >
                                        </TableCell>
                                        <TableCell class="text-right">{{
                                            formatQty(l.quantity)
                                        }}</TableCell>
                                        <TableCell>{{
                                            l.uom?.code ?? l.uom_id ?? '-'
                                        }}</TableCell>
                                        <TableCell>{{
                                            l.remarks ?? '-'
                                        }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>

                <div class="print-block rounded-lg border">
                    <div class="border-b p-4">
                        <h2 class="text-sm font-semibold">Audit History</h2>
                    </div>
                    <div class="p-4">
                        <StatusHistoryTable
                            :rows="pr.status_histories ?? []"
                            :format-date-time="formatDateTime"
                            :show-note="true"
                            :get-note="
                                (row) =>
                                    row.action === 'reject'
                                        ? ((row as any)?.meta?.reason ?? null)
                                        : null
                            "
                        />

                        <div class="mt-3 text-xs text-muted-foreground">
                            Note: reject reason is currently not displayed in
                            this table.
                        </div>
                    </div>
                </div>

                <div class="no-print flex gap-2">
                    <Button
                        v-if="status === 'DRAFT' && canSubmit"
                        @click="submit"
                        >Submit</Button
                    >
                    <Button
                        v-if="status === 'SUBMITTED' && canApprove"
                        @click="approve"
                        >Approve</Button
                    >
                    <Button
                        v-if="status === 'SUBMITTED' && canApprove"
                        variant="destructive"
                        @click="openReject"
                    >
                        Reject
                    </Button>

                    <Button
                        v-if="pr"
                        variant="outline"
                        type="button"
                        @click="doPrint"
                    >
                        Print
                    </Button>
                </div>

                <!-- Print-only signature blocks -->
                <section v-if="pr" class="print-signatures hidden print:grid">
                    <div class="print-sign-box">
                        <div class="print-sign-label">Requested By</div>
                        <div class="print-sign-name">
                            {{ pr.requester?.name ?? pr.requester_user_id }}
                        </div>
                    </div>
                    <div class="print-sign-box">
                        <div class="print-sign-label">Approved By</div>
                        <div class="print-sign-name">
                            {{ pr.approvedBy?.name ?? '-' }}
                        </div>
                    </div>
                </section>
            </div>

            <div
                v-if="error"
                class="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm"
            >
                {{ error }}
            </div>
        </div>
    </AppLayout>

    <Dialog v-model:open="rejectOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Reject Purchase Request</DialogTitle>
                <DialogDescription>
                    Please provide a reason. This PR will return to DRAFT.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <label class="text-sm font-medium">Reason</label>
                <Input
                    v-model="rejectReason"
                    placeholder="Type reject reason"
                    :disabled="rejectSubmitting"
                />
                <div
                    v-if="fieldErrors.reason?.length"
                    class="text-sm text-destructive"
                >
                    {{ fieldErrors.reason.join(', ') }}
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="rejectSubmitting"
                    @click="rejectOpen = false"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="rejectSubmitting"
                    @click="confirmReject"
                >
                    Reject
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
