<script setup lang="ts">
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
import {
    approvePurchaseRequest,
    getPurchaseRequest,
    rejectPurchaseRequest,
    submitPurchaseRequest,
    type PurchaseRequestDto,
} from '@/services/purchaseRequestApi';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ purchaseRequestId: number }>();

const loading = ref(true);
const error = ref<string | null>(null);
const pr = ref<PurchaseRequestDto | null>(null);

const status = computed(() => pr.value?.status);

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

async function submit() {
    if (!pr.value) return;
    try {
        await submitPurchaseRequest(pr.value.id);
        await load();
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to submit';
    }
}

async function approve() {
    if (!pr.value) return;
    try {
        await approvePurchaseRequest(pr.value.id);
        await load();
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to approve';
    }
}

async function reject() {
    if (!pr.value) return;
    try {
        await rejectPurchaseRequest(pr.value.id, 'Rejected from UI');
        await load();
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to reject';
    }
}

onMounted(load);
</script>

<template>
    <Head :title="pr ? `PR ${pr.pr_number}` : 'Purchase Request'" />

    <AppLayout>
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
                    v-if="pr && status === 'DRAFT'"
                    variant="outline"
                    as-child
                >
                    <Link :href="`/purchase-requests/${pr.id}/edit`"
                        >Edit Draft</Link
                    >
                </Button>
            </div>
        </div>

        <div
            v-if="error"
            class="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm"
        >
            {{ error }}
        </div>

        <div v-if="loading" class="mt-6 text-sm text-muted-foreground">
            Loading…
        </div>

        <div v-else-if="pr" class="mt-6 space-y-6">
            <div class="rounded-lg border p-4">
                <div class="grid gap-2 md:grid-cols-2">
                    <div>
                        <span class="text-xs text-muted-foreground"
                            >Department</span
                        >
                        — {{ pr.department?.code ?? pr.department_id }}
                    </div>
                    <div>
                        <span class="text-xs text-muted-foreground"
                            >Requester</span
                        >
                        — {{ pr.requester?.name ?? pr.requester_user_id }}
                    </div>
                    <div v-if="pr.approvedBy" class="md:col-span-2">
                        <span class="text-xs text-muted-foreground"
                            >Approved By</span
                        >
                        — {{ pr.approvedBy.name }}
                    </div>
                    <div class="md:col-span-2">
                        <span class="text-xs text-muted-foreground"
                            >Remarks</span
                        >
                        — {{ pr.remarks ?? '-' }}
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
                                        l.quantity
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

            <div class="rounded-lg border">
                <div class="border-b p-4">
                    <h2 class="text-sm font-semibold">Audit History</h2>
                </div>
                <div class="p-4">
                    <div class="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader class="bg-muted/40">
                                <TableRow>
                                    <TableHead>At</TableHead>
                                    <TableHead>Action</TableHead>
                                    <TableHead>From</TableHead>
                                    <TableHead>To</TableHead>
                                    <TableHead>Actor</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="h in pr.status_histories ?? []"
                                    :key="h.id"
                                >
                                    <TableCell>{{ h.created_at }}</TableCell>
                                    <TableCell class="font-medium">{{
                                        h.action
                                    }}</TableCell>
                                    <TableCell>{{
                                        h.from_status ?? '-'
                                    }}</TableCell>
                                    <TableCell>{{ h.to_status }}</TableCell>
                                    <TableCell>{{
                                        h.actor?.name ?? h.actor_user_id ?? '-'
                                    }}</TableCell>
                                </TableRow>

                                <TableRow
                                    v-if="
                                        (pr.status_histories ?? []).length === 0
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

            <div class="flex gap-2">
                <Button v-if="status === 'DRAFT'" @click="submit"
                    >Submit</Button
                >
                <Button v-if="status === 'SUBMITTED'" @click="approve"
                    >Approve</Button
                >
                <Button
                    v-if="status === 'SUBMITTED'"
                    variant="destructive"
                    @click="reject"
                >
                    Reject
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
