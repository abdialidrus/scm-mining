<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
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
import axios from 'axios';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Edit,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface ItemInventorySetting {
    id: number;
    item_id: number;
    warehouse_id: number | null;
    reorder_point: string;
    reorder_quantity: string;
    min_stock: string;
    max_stock: string | null;
    lead_time_days: number;
    safety_stock: string;
    is_active: boolean;
    item: { id: number; sku: string; name: string };
    warehouse: { id: number; code: string; name: string } | null;
}

const settings = ref<ItemInventorySetting[]>([]);
const loading = ref(true);
const search = ref('');
const page = ref(1);
const perPage = ref(10);
const totalPages = ref(1);
const hasNext = ref(false);
const hasPrev = computed(() => page.value > 1);
const deleteDialog = ref(false);
const settingToDelete = ref<ItemInventorySetting | null>(null);

async function loadSettings() {
    loading.value = true;
    try {
        const res = await axios.get('/api/item-inventory-settings', {
            params: {
                search: search.value || undefined,
                page: page.value,
                per_page: perPage.value,
            },
        });

        const paginated = res.data.data || res.data;
        settings.value = Array.isArray(paginated)
            ? paginated
            : paginated?.data || [];

        // Handle pagination metadata
        const meta = paginated?.meta || res.data?.meta;
        if (meta) {
            const currentPage = Number(meta.current_page ?? page.value);
            const lastPage = Number(meta.last_page ?? currentPage);
            page.value = currentPage;
            totalPages.value = lastPage;
            hasNext.value = currentPage < lastPage;
        } else if (paginated?.last_page) {
            // Alternative pagination format
            page.value = Number(paginated.current_page ?? page.value);
            totalPages.value = Number(paginated.last_page ?? 1);
            hasNext.value = page.value < totalPages.value;
        }
    } catch (e) {
        console.error('Failed to load settings', e);
    } finally {
        loading.value = false;
    }
}

function goToPage(p: number) {
    const next = Math.max(1, Math.min(p, totalPages.value || 1));
    if (next === page.value) return;
    page.value = next;
    loadSettings();
}

function onChangePerPage() {
    page.value = 1;
    loadSettings();
}

function nextPage() {
    if (!hasNext.value) return;
    page.value += 1;
    loadSettings();
}

function prevPage() {
    if (!hasPrev.value) return;
    page.value -= 1;
    loadSettings();
}

function confirmDelete(setting: ItemInventorySetting) {
    settingToDelete.value = setting;
    deleteDialog.value = true;
}

async function deleteSetting() {
    if (!settingToDelete.value) return;
    try {
        await axios.delete(
            `/api/item-inventory-settings/${settingToDelete.value.id}`,
        );
        await loadSettings();
        deleteDialog.value = false;
    } catch (e) {
        console.error('Failed to delete', e);
    }
}

function formatNumber(value: string | null) {
    if (!value) return '-';
    return parseFloat(value).toLocaleString('id-ID', {
        minimumFractionDigits: 2,
    });
}

function navigateTo(path: string) {
    window.location.href = path;
}

onMounted(() => loadSettings());
</script>

<template>
    <AppLayout title="Item Inventory Settings">
        <div class="container mx-auto space-y-6 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Item Inventory Settings</h1>
                    <p class="text-muted-foreground">
                        Manage reorder points and stock levels
                    </p>
                </div>
                <Button
                    @click="
                        navigateTo(
                            '/master-data/item-inventory-settings/create',
                        )
                    "
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Add New
                </Button>
            </div>

            <Card>
                <CardContent class="pt-6">
                    <div class="flex gap-4">
                        <Input
                            v-model="search"
                            placeholder="Search..."
                            @keyup.enter="loadSettings"
                            class="max-w-sm"
                        >
                            <template #prefix>
                                <Search class="h-4 w-4" />
                            </template>
                        </Input>
                        <Button @click="loadSettings">Search</Button>
                    </div>
                </CardContent>
            </Card>

            <div class="rounded-md border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Item</TableHead>
                            <TableHead>Warehouse</TableHead>
                            <TableHead class="text-right"
                                >Reorder Point</TableHead
                            >
                            <TableHead class="text-right"
                                >Reorder Qty</TableHead
                            >
                            <TableHead class="text-right"
                                >Min/Max Stock</TableHead
                            >
                            <TableHead class="text-center">Lead Time</TableHead>
                            <TableHead class="text-center">Status</TableHead>
                            <TableHead class="text-center">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="loading">
                            <TableCell colspan="8" class="py-8 text-center"
                                >Loading...</TableCell
                            >
                        </TableRow>
                        <TableRow v-else-if="settings.length === 0">
                            <TableCell
                                colspan="8"
                                class="py-6 text-center text-muted-foreground"
                                >No inventory settings found.</TableCell
                            >
                        </TableRow>
                        <TableRow
                            v-else
                            v-for="setting in settings"
                            :key="setting.id"
                        >
                            <TableCell>
                                <div class="font-medium">
                                    {{ setting.item.sku }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ setting.item.name }}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    v-if="!setting.warehouse"
                                    variant="secondary"
                                    >Global</Badge
                                >
                                <div v-else>
                                    <div class="font-medium">
                                        {{ setting.warehouse.code }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ setting.warehouse.name }}
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell class="text-right">{{
                                formatNumber(setting.reorder_point)
                            }}</TableCell>
                            <TableCell class="text-right">{{
                                formatNumber(setting.reorder_quantity)
                            }}</TableCell>
                            <TableCell class="text-right">
                                {{ formatNumber(setting.min_stock) }} /
                                {{ formatNumber(setting.max_stock) }}
                            </TableCell>
                            <TableCell class="text-center"
                                >{{ setting.lead_time_days }} days</TableCell
                            >
                            <TableCell class="text-center">
                                <Badge
                                    :variant="
                                        setting.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        setting.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <div class="flex justify-center gap-2">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="
                                            navigateTo(
                                                `/master-data/item-inventory-settings/${setting.id}/edit`,
                                            )
                                        "
                                    >
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="confirmDelete(setting)"
                                    >
                                        <Trash2
                                            class="h-4 w-4 text-destructive"
                                        />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination Controls -->
            <div class="flex items-center justify-between">
                <div
                    class="hidden flex-1 text-sm text-muted-foreground lg:flex"
                >
                    <!-- spacer -->
                </div>

                <div class="flex w-full items-center gap-8 lg:w-fit">
                    <!-- Rows per page selector -->
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

                    <!-- Page counter -->
                    <div
                        class="flex w-fit items-center justify-center text-sm font-medium"
                    >
                        Page {{ page }} of {{ totalPages }}
                    </div>

                    <!-- Navigation buttons -->
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
        <Dialog :open="deleteDialog" @update:open="deleteDialog = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Setting?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete inventory settings for
                        <strong>{{ settingToDelete?.item.name }}</strong
                        >?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteDialog = false"
                        >Cancel</Button
                    >
                    <Button variant="destructive" @click="deleteSetting"
                        >Delete</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
