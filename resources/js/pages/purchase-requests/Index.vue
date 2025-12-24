<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Pagination from '@/components/ui/pagination/Pagination.vue';
import PaginationContent from '@/components/ui/pagination/PaginationContent.vue';
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
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Purchase Requests',
        href: '/purchase-requests',
    },
];

const loading = ref(true);
const error = ref<string | null>(null);
const items = ref<PurchaseRequestListItemDto[]>([]);

const search = ref('');
const status = ref<{ value: string; label: string }>({
    value: '',
    label: 'All',
});
const page = ref(1);
const perPage = ref(10);
const hasNext = ref(false);
const totalPages = ref(1);
const hasPrev = computed(() => page.value > 1);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await listPurchaseRequests({
            search: search.value || undefined,
            status: status.value.value || undefined,
            page: page.value,
            per_page: perPage.value,
        });

        const paginator = (res as any).data;
        items.value = (paginator?.data ?? []) as PurchaseRequestListItemDto[];

        const currentPage =
            paginator?.current_page ?? paginator?.meta?.current_page;
        const lastPage = paginator?.last_page ?? paginator?.meta?.last_page;

        if (typeof currentPage === 'number' && typeof lastPage === 'number') {
            page.value = currentPage;
            totalPages.value = lastPage;
            hasNext.value = currentPage < lastPage;
        } else {
            totalPages.value = 1;
            hasNext.value = false;
        }
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load purchase requests';
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

function applyFilters() {
    page.value = 1;
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

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Purchase Requests</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage your purchase requests.
                    </p>
                </div>

                <Button as-child>
                    <Link href="/purchase-requests/create">Create</Link>
                </Button>
            </div>

            <div class="mt-6 grid items-end gap-3 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="text-sm font-medium">Search</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Input
                            v-model="search"
                            class="h-10"
                            placeholder="PR number"
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
                                { value: 'SUBMITTED', label: 'SUBMITTED' },
                                { value: 'APPROVED', label: 'APPROVED' },
                                {
                                    value: 'CONVERTED_TO_PO',
                                    label: 'CONVERTED_TO_PO',
                                },
                            ]"
                            track-by="value"
                            label="label"
                            class="w-full"
                        />
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium opacity-0">Apply</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 w-full"
                            @click="applyFilters"
                        >
                            Apply
                        </Button>
                    </div>
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

            <div class="flex items-center justify-between">
                <div
                    class="hidden flex-1 text-sm text-muted-foreground lg:flex"
                >
                    <!-- spacer / optional status text -->
                </div>

                <div class="flex w-full items-center gap-8 lg:w-fit">
                    <div class="hidden items-center gap-2 lg:flex">
                        <label for="rows-per-page" class="text-sm font-medium">
                            Rows per page
                        </label>
                        <select
                            id="rows-per-page"
                            v-model.number="perPage"
                            class="h-8 w-20 rounded-md border bg-background px-2 text-sm"
                            @change="onChangePerPage"
                        >
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>

                    <div
                        class="flex w-fit items-center justify-center text-sm font-medium"
                    >
                        Page {{ page }} of {{ totalPages }}
                    </div>

                    <div class="ml-auto flex items-center gap-2 lg:ml-0">
                        <Pagination
                            :page="page"
                            :items-per-page="perPage"
                            :total="totalPages * perPage"
                            :sibling-count="1"
                            :show-edges="true"
                            @update:page="goToPage"
                        >
                            <PaginationContent
                                v-slot="{ items }"
                                class="justify-end"
                            >
                                <!-- <PaginationFirst @click="goToPage(1)" />
                                <PaginationPrevious @click="prevPage" />

                                <template
                                    v-for="(item, idx) in items"
                                    :key="idx"
                                >
                                    <PaginationItem
                                        v-if="item.type === 'page'"
                                        :value="item.value"
                                        :is-active="item.value === page"
                                        @click="goToPage(item.value)"
                                    >
                                        {{ item.value }}
                                    </PaginationItem>

                                    <PaginationEllipsis v-else />
                                </template>

                                <PaginationNext @click="nextPage" />
                                <PaginationLast @click="goToPage(totalPages)" /> -->

                                <Button
                                    variant="outline"
                                    class="hidden h-8 w-8 p-0 lg:flex"
                                    :disabled="page === 1"
                                    @click="goToPage(1)"
                                >
                                    <span class="sr-only"
                                        >Go to first page</span
                                    >
                                    <ChevronsLeft />
                                </Button>
                                <Button
                                    variant="outline"
                                    class="size-8"
                                    size="icon"
                                    :disabled="page === 1"
                                    @click="prevPage"
                                >
                                    <span class="sr-only"
                                        >Go to previous page</span
                                    >
                                    <ChevronLeft />
                                </Button>
                                <Button
                                    variant="outline"
                                    class="size-8"
                                    size="icon"
                                    :disabled="page === totalPages"
                                    @click="nextPage"
                                >
                                    <span class="sr-only">Go to next page</span>
                                    <ChevronRight />
                                </Button>
                                <Button
                                    variant="outline"
                                    class="hidden size-8 lg:flex"
                                    size="icon"
                                    :disabled="page === totalPages"
                                    @click="goToPage(totalPages)"
                                >
                                    <span class="sr-only">Go to last page</span>
                                    <ChevronsRight />
                                </Button>
                            </PaginationContent>
                        </Pagination>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
