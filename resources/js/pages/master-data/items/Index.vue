<script setup lang="ts">
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
import { listItems, type ItemDto } from '@/services/masterDataApi';
import { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref<string | null>(null);
const items = ref<ItemDto[]>([]);

const search = ref('');
const page = ref(1);
const perPage = ref(10);
const hasNext = ref(false);
const totalPages = ref(1);
const hasPrev = computed(() => page.value > 1);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await listItems({
            search: search.value,
            page: page.value,
            per_page: perPage.value,
        });
        const paginated = (res as any).data;
        items.value = (paginated?.data ?? []) as ItemDto[];

        const meta = paginated?.meta;
        const currentPage = Number(meta?.current_page ?? page.value);
        const lastPage = Number(meta?.last_page ?? currentPage);
        page.value = currentPage;
        totalPages.value = lastPage;
        hasNext.value = currentPage < lastPage;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load items';
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Items',
        href: '#',
    },
];

onMounted(load);
</script>

<template>
    <Head title="Master Data - Items" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Items</h1>
                    <p class="text-sm text-muted-foreground">Manage items</p>
                </div>
            </div>

            <div class="mt-6 grid items-end gap-3 md:grid-cols-12">
                <div class="md:col-span-10">
                    <label class="text-sm font-medium">Search</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Input
                            v-model="search"
                            class="h-10"
                            placeholder="Enter item SKU or name"
                        />
                    </div>
                </div>
                <div class="md:col-span-2">
                    <div class="mt-1 flex h-10 items-center">
                        <Button
                            variant="outline"
                            type="button"
                            class="h-10 w-full"
                            @click="
                                page = 1;
                                load();
                            "
                            >Search</Button
                        >
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
                            <TableHead>SKU</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Base UOM</TableHead>
                            <TableHead class="text-right">ID</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="it in items"
                            :key="it.id"
                            class="cursor-pointer hover:bg-muted/30"
                            title="Read-only"
                        >
                            <TableCell class="font-medium">{{
                                it.sku
                            }}</TableCell>
                            <TableCell>{{ it.name }}</TableCell>
                            <TableCell>{{ it.base_uom_code ?? '-' }}</TableCell>
                            <TableCell class="text-right">{{
                                it.id
                            }}</TableCell>
                        </TableRow>

                        <TableRow v-if="items.length === 0">
                            <TableCell
                                colspan="4"
                                class="py-6 text-center text-muted-foreground"
                            >
                                No items.
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
                            <PaginationContent class="justify-end">
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
