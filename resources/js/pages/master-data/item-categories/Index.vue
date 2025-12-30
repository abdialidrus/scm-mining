<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
    deleteItemCategory,
    listItemCategories,
    type ItemCategoryDto,
    type Paginated,
} from '@/services/masterDataApi';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Plus,
    Search,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const categories = ref<ItemCategoryDto[]>([]);
const loading = ref(false);
const search = ref('');
const filterActive = ref<string>('all');
const currentPage = ref(1);
const perPage = ref(10);
const totalPages = ref(1);
const hasNext = computed(() => currentPage.value < totalPages.value);
const hasPrev = computed(() => currentPage.value > 1);

const deleteDialog = ref(false);
const categoryToDelete = ref<ItemCategoryDto | null>(null);

const filteredCategories = computed(() => {
    return categories.value;
});

async function loadCategories() {
    loading.value = true;
    try {
        const params: any = {
            search: search.value || undefined,
            page: currentPage.value,
            per_page: perPage.value,
        };

        if (filterActive.value === 'active') {
            params.is_active = true;
        } else if (filterActive.value === 'inactive') {
            params.is_active = false;
        }

        const response = await listItemCategories(params);
        const data = response.data as Paginated<ItemCategoryDto>;
        categories.value = data.data;

        totalPages.value = data.last_page;
        currentPage.value = data.current_page;
    } catch (error) {
        console.error('Failed to load categories:', error);
        alert('Failed to load item categories');
    } finally {
        loading.value = false;
    }
}

function confirmDelete(category: ItemCategoryDto) {
    categoryToDelete.value = category;
    deleteDialog.value = true;
}

async function handleDelete() {
    if (!categoryToDelete.value) return;

    try {
        await deleteItemCategory(categoryToDelete.value.id);
        alert('Item category deleted successfully');
        deleteDialog.value = false;
        categoryToDelete.value = null;
        loadCategories();
    } catch (error: any) {
        alert(
            error.response?.data?.message || 'Failed to delete item category',
        );
    }
}

function goToPage(page: number) {
    currentPage.value = page;
    loadCategories();
}

function onChangePerPage() {
    currentPage.value = 1;
    loadCategories();
}

function nextPage() {
    if (!hasNext.value) return;
    currentPage.value += 1;
    loadCategories();
}

function prevPage() {
    if (!hasPrev.value) return;
    currentPage.value -= 1;
    loadCategories();
}

watch([search, filterActive], () => {
    currentPage.value = 1;
    loadCategories();
});

loadCategories();
</script>

<template>
    <Head title="Item Categories" />

    <AppLayout>
        <div class="space-y-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Item Categories</h1>
                    <p class="text-muted-foreground">
                        Manage item categories and hierarchy
                    </p>
                </div>
                <Link href="/master-data/item-categories/create">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Create Category
                    </Button>
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex gap-4">
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search by code or name..."
                        class="pl-10"
                    />
                </div>
                <select
                    v-model="filterActive"
                    class="rounded-md border bg-background px-4 py-2"
                >
                    <option value="all">All Status</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>
            </div>

            <!-- Table -->
            <div class="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Code</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Full Path</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Requires Approval</TableHead>
                            <TableHead>Sort Order</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="loading">
                            <TableCell colspan="7" class="py-8 text-center">
                                Loading...
                            </TableCell>
                        </TableRow>
                        <TableRow v-else-if="filteredCategories.length === 0">
                            <TableCell colspan="7" class="py-8 text-center">
                                No categories found
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="category in filteredCategories"
                            :key="category.id"
                            class="cursor-pointer hover:bg-muted/30"
                            @click="
                                router.visit(
                                    `/master-data/item-categories/${category.id}`,
                                )
                            "
                        >
                            <TableCell class="font-mono">
                                <div class="flex items-center gap-2">
                                    <div
                                        v-if="category.color_code"
                                        class="h-3 w-3 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                category.color_code,
                                        }"
                                    ></div>
                                    {{ category.code }}
                                </div>
                            </TableCell>
                            <TableCell class="font-medium">
                                {{ category.name }}
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ category.full_path || category.name }}
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        category.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        category.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    v-if="category.requires_approval"
                                    variant="destructive"
                                >
                                    Required
                                </Badge>
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                >
                                    No
                                </span>
                            </TableCell>
                            <TableCell>
                                {{ category.sort_order }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
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
                        Page {{ currentPage }} of {{ totalPages }}
                    </div>

                    <div class="ml-auto flex items-center gap-2 lg:ml-0">
                        <Pagination
                            :page="currentPage"
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
                                    :disabled="currentPage === 1"
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
                                    :disabled="currentPage === 1"
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
                                    :disabled="currentPage === totalPages"
                                    @click="nextPage"
                                >
                                    <span class="sr-only">Go to next page</span>
                                    <ChevronRight />
                                </Button>
                                <Button
                                    variant="outline"
                                    class="hidden size-8 lg:flex"
                                    size="icon"
                                    :disabled="currentPage === totalPages"
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
                    <DialogTitle>Delete Item Category</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete category
                        <strong>{{ categoryToDelete?.name }}</strong
                        >? <br /><br />
                        This action cannot be undone. The category cannot be
                        deleted if it has items or subcategories.
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
