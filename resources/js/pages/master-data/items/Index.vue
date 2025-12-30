<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
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
    deleteItem,
    listItemCategories,
    listItems,
    type ItemCategoryDto,
    type ItemDto,
} from '@/services/masterDataApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Plus,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const loading = ref(true);
const error = ref<string | null>(null);
const items = ref<ItemDto[]>([]);
const categories = ref<ItemCategoryDto[]>([]);

const search = ref('');
const selectedCategories = ref<ItemCategoryDto[]>([]);
const page = ref(1);
const perPage = ref(10);
const hasNext = ref(false);
const totalPages = ref(1);
const hasPrev = computed(() => page.value > 1);

const deleteDialog = ref(false);
const itemToDelete = ref<ItemDto | null>(null);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const categoryIds = selectedCategories.value.map((c) => c.id);
        const res = await listItems({
            search: search.value,
            category_ids: categoryIds.length > 0 ? categoryIds : undefined,
            page: page.value,
            per_page: perPage.value,
        });
        const paginated = (res as any).data;
        items.value = (paginated?.data ?? []) as ItemDto[];

        totalPages.value = paginated.last_page;
        page.value = paginated.current_page;
        hasNext.value = page.value < totalPages.value;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load items';
    } finally {
        loading.value = false;
    }
}

async function loadCategories() {
    try {
        const res = await listItemCategories({ per_page: 100 });
        const paginated = (res as any).data;
        categories.value = (paginated?.data ?? []) as ItemCategoryDto[];
    } catch (e: any) {
        console.error('Failed to load categories:', e);
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

function confirmDelete(item: ItemDto) {
    itemToDelete.value = item;
    deleteDialog.value = true;
}

async function handleDelete() {
    if (!itemToDelete.value) return;

    try {
        await deleteItem(itemToDelete.value.id);
        alert('Item deleted successfully');
        deleteDialog.value = false;
        itemToDelete.value = null;
        load();
    } catch (error: any) {
        alert(error.response?.data?.message || 'Failed to delete item');
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Items',
        href: '#',
    },
];

onMounted(async () => {
    await loadCategories();
    await load();
});
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
                <Link href="/master-data/items/create">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Create Item
                    </Button>
                </Link>
            </div>

            <div class="mt-6 grid items-end gap-3 md:grid-cols-12">
                <div class="md:col-span-5">
                    <label class="text-sm font-medium">Search</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Input
                            v-model="search"
                            class="h-10"
                            placeholder="Enter item SKU or name"
                        />
                    </div>
                </div>
                <div class="md:col-span-5">
                    <label class="text-sm font-medium">Categories</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Multiselect
                            v-model="selectedCategories"
                            :options="categories"
                            :multiple="true"
                            :close-on-select="false"
                            :clear-on-select="false"
                            :preserve-search="true"
                            placeholder="Select categories"
                            track-by="id"
                            label="name"
                            class="w-full"
                        >
                            <template #option="{ option }">
                                <div class="flex items-center gap-2">
                                    <div
                                        v-if="option.color_code"
                                        class="h-3 w-3 rounded-full"
                                        :style="{
                                            backgroundColor: option.color_code,
                                        }"
                                    ></div>
                                    <span>{{ option.name }}</span>
                                </div>
                            </template>
                            <template #tag="{ option, remove }">
                                <span class="multiselect__tag">
                                    <span>{{ option.name }}</span>
                                    <i
                                        class="multiselect__tag-icon"
                                        @click="remove(option)"
                                    ></i>
                                </span>
                            </template>
                        </Multiselect>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Parent categories include their subcategories
                    </p>
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
                            >Apply</Button
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
                            <TableHead>Category</TableHead>
                            <TableHead>Base UOM</TableHead>
                            <TableHead>Serialized</TableHead>
                            <TableHead>Criticality</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="it in items"
                            :key="it.id"
                            class="cursor-pointer hover:bg-muted/30"
                            @click="router.visit(`/master-data/items/${it.id}`)"
                        >
                            <TableCell class="font-mono font-medium">{{
                                it.sku
                            }}</TableCell>
                            <TableCell class="font-medium">{{
                                it.name
                            }}</TableCell>
                            <TableCell>
                                <div
                                    v-if="it.category_name"
                                    class="flex items-center gap-2"
                                >
                                    <div
                                        v-if="it.category_color"
                                        class="h-3 w-3 rounded-full"
                                        :style="{
                                            backgroundColor: it.category_color,
                                        }"
                                    ></div>
                                    <span class="text-sm">{{
                                        it.category_name
                                    }}</span>
                                </div>
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                    >-</span
                                >
                            </TableCell>
                            <TableCell>{{ it.base_uom_code ?? '-' }}</TableCell>
                            <TableCell>
                                <Badge
                                    v-if="it.is_serialized"
                                    variant="default"
                                >
                                    Serialized
                                </Badge>
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                    >No</span
                                >
                            </TableCell>
                            <TableCell>
                                <Badge
                                    v-if="it.criticality_level"
                                    :variant="
                                        it.criticality_level >= 4
                                            ? 'destructive'
                                            : it.criticality_level >= 3
                                              ? 'default'
                                              : 'secondary'
                                    "
                                >
                                    Level {{ it.criticality_level }}
                                </Badge>
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                    >-</span
                                >
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="items.length === 0">
                            <TableCell
                                colspan="7"
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

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="deleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Item</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete item
                        <strong>{{ itemToDelete?.name }}</strong
                        >? <br /><br />
                        This action cannot be undone. The item cannot be deleted
                        if it is used in any transactions.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteDialog = false">
                        Cancel
                    </Button>
                    <Button variant="destructive" @click="handleDelete">
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
