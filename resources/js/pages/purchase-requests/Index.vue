<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
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
    listPurchaseRequests,
    type PurchaseRequestListItemDto,
} from '@/services/purchaseRequestApi';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref<string | null>(null);
const items = ref<PurchaseRequestListItemDto[]>([]);

const search = ref('');
const status = ref<string>('');
const page = ref(1);
const hasNext = ref(false);
const hasPrev = computed(() => page.value > 1);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await listPurchaseRequests({
            search: search.value || undefined,
            status: status.value || undefined,
            page: page.value,
        });

        const paginator = (res as any).data;
        items.value = (paginator?.data ?? []) as PurchaseRequestListItemDto[];

        // very simple pagination inference from meta
        const currentPage =
            paginator?.current_page ?? paginator?.meta?.current_page;
        const lastPage = paginator?.last_page ?? paginator?.meta?.last_page;
        if (typeof currentPage === 'number' && typeof lastPage === 'number') {
            page.value = currentPage;
            hasNext.value = currentPage < lastPage;
        } else {
            hasNext.value = false;
        }
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load purchase requests';
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    page.value = 1;
    load();
}

function nextPage() {
    if (!hasNext.value) return;
    page.value += 1;
    load();
}

function prevPage() {
    if (!hasPrev.value) return;
    page.value -= 1;
    load();
}

function formatDateTime(value?: string | null) {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return new Intl.DateTimeFormat('id-ID', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
}

onMounted(load);
</script>

<template>
    <Head title="Purchase Requests" />

    <AppLayout>
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Purchase Requests</h1>

            <Button as-child>
                <Link href="/purchase-requests/create">Create</Link>
            </Button>
        </div>

        <div class="mt-6 grid gap-3 md:grid-cols-12">
            <div class="md:col-span-6">
                <label class="text-sm font-medium">Search</label>
                <Input v-model="search" placeholder="PR number" />
            </div>
            <div class="md:col-span-4">
                <label class="text-sm font-medium">Status</label>
                <select
                    v-model="status"
                    class="mt-1 w-full rounded-md border bg-background px-2 py-2"
                >
                    <option value="">All</option>
                    <option value="DRAFT">DRAFT</option>
                    <option value="SUBMITTED">SUBMITTED</option>
                    <option value="APPROVED">APPROVED</option>
                    <option value="CONVERTED_TO_PO">CONVERTED_TO_PO</option>
                </select>
            </div>
            <div class="flex items-end md:col-span-2">
                <Button
                    type="button"
                    variant="outline"
                    class="w-full"
                    @click="applyFilters"
                >
                    Apply
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

        <div v-else class="mt-6 overflow-hidden rounded-lg border">
            <Table>
                <TableHeader class="bg-muted/40">
                    <TableRow>
                        <TableHead>PR No</TableHead>
                        <TableHead>Department</TableHead>
                        <TableHead>Requester</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Created</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="pr in items"
                        :key="pr.id"
                        class="cursor-pointer hover:bg-muted/30"
                        @click="router.visit(`/purchase-requests/${pr.id}`)"
                    >
                        <TableCell class="font-medium">{{
                            pr.pr_number
                        }}</TableCell>
                        <TableCell>{{
                            pr.department?.name ??
                            pr.department?.code ??
                            pr.department_id
                        }}</TableCell>
                        <TableCell>{{
                            pr.requester?.name ?? pr.requester_user_id
                        }}</TableCell>
                        <TableCell>
                            <StatusBadge module="PR" :status="pr.status" />
                        </TableCell>
                        <TableCell>{{
                            formatDateTime(pr.created_at)
                        }}</TableCell>
                    </TableRow>

                    <TableRow v-if="items.length === 0">
                        <TableCell
                            colspan="5"
                            class="py-6 text-center text-muted-foreground"
                        >
                            No purchase requests.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div class="mt-4 flex items-center justify-between">
            <Button
                variant="outline"
                type="button"
                :disabled="!hasPrev"
                @click="prevPage"
                >Previous</Button
            >
            <div class="text-sm text-muted-foreground">Page {{ page }}</div>
            <Button
                variant="outline"
                type="button"
                :disabled="!hasNext"
                @click="nextPage"
                >Next</Button
            >
        </div>
    </AppLayout>
</template>
