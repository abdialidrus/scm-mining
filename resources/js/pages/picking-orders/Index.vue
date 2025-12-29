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
    listPickingOrders,
    type PickingOrderDto,
} from '@/services/pickingOrderApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-vue-next';

const loading = ref(true);
const error = ref<string | null>(null);
const pickingOrders = ref<PickingOrderDto[]>([]);

const search = ref('');
const status = ref<{ value: string; label: string }>({
    value: '',
    label: 'All',
});

const page = ref(1);
const perPage = ref(20);
const totalPages = ref(1);
const fromRecord = ref(0);
const toRecord = ref(0);
const totalRecords = ref(0);
const hasNext = ref(false);
const hasPrev = computed(() => page.value > 1);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await listPickingOrders({
            search: search.value,
            status: status.value.value || undefined,
            page: page.value,
            per_page: perPage.value,
        });

        const paginated = (res as any).data;
        pickingOrders.value = (paginated?.data ?? []) as PickingOrderDto[];

        const meta = paginated?.meta;
        const currentPage = Number(meta?.current_page ?? page.value);
        const lastPage = Number(meta?.last_page ?? currentPage);
        page.value = currentPage;
        totalPages.value = lastPage;
        fromRecord.value = Number(meta?.from ?? 0);
        toRecord.value = Number(meta?.to ?? 0);
        totalRecords.value = Number(meta?.total ?? 0);
        hasNext.value = currentPage < lastPage;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load picking orders';
    } finally {
        loading.value = false;
    }
}

function goToPage(p: number) {
    const next = Math.max(1, Math.min(p, totalPages.value || 1));
    if (next === page.value) return;
    page.value = next;
    load();
}

function onChangePerPage() {
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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Picking Orders', href: '#' }];

// Watch for search/status changes and reset to page 1
watch([search, status], () => {
    page.value = 1;
    load();
});

onMounted(load);

// Helper for formatting date
function formatDate(dateStr?: string | null): string {
    if (!dateStr) return '-';
    try {
        return new Date(dateStr).toLocaleDateString();
    } catch {
        return '-';
    }
}

// Calculate page numbers for pagination
const pageNumbers = computed(() => {
    const total = totalPages.value;
    const current = page.value;
    const pages: (number | string)[] = [];

    if (total <= 7) {
        for (let i = 1; i <= total; i++) {
            pages.push(i);
        }
    } else {
        if (current <= 4) {
            for (let i = 1; i <= 5; i++) pages.push(i);
            pages.push('...');
            pages.push(total);
        } else if (current >= total - 3) {
            pages.push(1);
            pages.push('...');
            for (let i = total - 4; i <= total; i++) pages.push(i);
        } else {
            pages.push(1);
            pages.push('...');
            for (let i = current - 1; i <= current + 1; i++) pages.push(i);
            pages.push('...');
            pages.push(total);
        }
    }

    return pages;
});
</script>

<template>
    <Head title="Picking Orders" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Picking Orders</h1>
                    <p class="text-sm text-muted-foreground">
                        Pick goods from STORAGE for various purposes.
                    </p>
                </div>

                <Button as-child>
                    <Link href="/picking-orders/create">Create</Link>
                </Button>
            </div>

            <div class="mt-6 grid items-end gap-3 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="text-sm font-medium">Search</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Input
                            v-model="search"
                            class="h-10"
                            placeholder="PK number"
                        />
                    </div>
                </div>

                <div class="md:col-span-4">
                    <label class="text-sm font-medium">Status</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Multiselect
                            v-model="status"
                            :options="[
                                { value: '', label: 'All' },
                                { value: 'DRAFT', label: 'DRAFT' },
                                { value: 'POSTED', label: 'POSTED' },
                                { value: 'CANCELLED', label: 'CANCELLED' },
                            ]"
                            track-by="value"
                            label="label"
                            class="w-full"
                        />
                    </div>
                </div>

                <div class="md:col-span-2">
                    <Button @click="load" class="w-full">Search</Button>
                </div>
            </div>

            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

            <div v-if="loading" class="mt-4 text-center text-muted-foreground">
                Loading...
            </div>

            <div v-else-if="pickingOrders.length === 0" class="mt-4">
                <p class="text-sm text-muted-foreground">
                    No picking orders found.
                </p>
            </div>

            <div v-else class="mt-4 overflow-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>PK Number</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Warehouse</TableHead>
                            <TableHead>Department</TableHead>
                            <TableHead>Purpose</TableHead>
                            <TableHead>Picked At</TableHead>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="pk in pickingOrders"
                            :key="pk.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="$inertia.visit(`/picking-orders/${pk.id}`)"
                        >
                            <TableCell class="font-medium">
                                {{ pk.picking_order_number }}
                            </TableCell>
                            <TableCell>
                                <StatusBadge :status="pk.status" />
                            </TableCell>
                            <TableCell>
                                {{
                                    pk.warehouse
                                        ? `${pk.warehouse.code} — ${pk.warehouse.name}`
                                        : '-'
                                }}
                            </TableCell>
                            <TableCell>
                                {{ pk.department?.name ?? '-' }}
                            </TableCell>
                            <TableCell>
                                {{ pk.purpose ?? '-' }}
                            </TableCell>
                            <TableCell>
                                {{ formatDate(pk.picked_at) }}
                            </TableCell>
                            <TableCell>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    @click.stop
                                >
                                    <Link :href="`/picking-orders/${pk.id}`">
                                        View
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Enhanced Pagination -->
            <div
                v-if="!loading && pickingOrders.length > 0"
                class="mt-4 flex flex-col items-center justify-between gap-4 sm:flex-row"
            >
                <div class="text-sm text-muted-foreground">
                    <span v-if="totalPages > 1">
                        Showing {{ fromRecord }} to {{ toRecord }} of
                        {{ totalRecords }} records
                    </span>
                    <span v-else> Showing all {{ totalRecords }} records </span>
                </div>

                <div v-if="totalPages > 1" class="flex items-center gap-2">
                    <!-- First Page -->
                    <Button
                        variant="outline"
                        size="icon"
                        :disabled="!hasPrev"
                        @click="goToPage(1)"
                    >
                        <ChevronsLeft class="h-4 w-4" />
                    </Button>

                    <!-- Previous Page -->
                    <Button
                        variant="outline"
                        size="icon"
                        :disabled="!hasPrev"
                        @click="prevPage"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </Button>

                    <!-- Page Numbers -->
                    <div class="hidden items-center gap-1 sm:flex">
                        <template v-for="(p, idx) in pageNumbers" :key="idx">
                            <Button
                                v-if="typeof p === 'number'"
                                :variant="p === page ? 'default' : 'outline'"
                                size="icon"
                                @click="goToPage(p)"
                            >
                                {{ p }}
                            </Button>
                            <span v-else class="px-2 text-muted-foreground">
                                {{ p }}
                            </span>
                        </template>
                    </div>

                    <!-- Mobile: Current Page Indicator -->
                    <div class="flex items-center gap-2 sm:hidden">
                        <span class="text-sm text-muted-foreground">
                            Page {{ page }} of {{ totalPages }}
                        </span>
                    </div>

                    <!-- Next Page -->
                    <Button
                        variant="outline"
                        size="icon"
                        :disabled="!hasNext"
                        @click="nextPage"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </Button>

                    <!-- Last Page -->
                    <Button
                        variant="outline"
                        size="icon"
                        :disabled="!hasNext"
                        @click="goToPage(totalPages)"
                    >
                        <ChevronsRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
