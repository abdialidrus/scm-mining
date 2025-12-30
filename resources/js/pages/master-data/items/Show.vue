<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { deleteItem, getItem, type ItemDto } from '@/services/masterDataApi';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    id: number;
}>();

const item = ref<ItemDto | null>(null);
const loading = ref(false);
const deleteDialog = ref(false);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Items',
        href: '/master-data/items',
    },
    {
        title: item.value?.name || 'Item Detail',
        href: '#',
    },
]);

async function loadItem() {
    loading.value = true;
    try {
        const response = await getItem(props.id);
        item.value = response.data;
    } catch (error) {
        console.error('Failed to load item:', error);
        alert('Failed to load item');
    } finally {
        loading.value = false;
    }
}

async function handleDelete() {
    try {
        await deleteItem(props.id);
        alert('Item deleted successfully');
        router.visit('/master-data/items');
    } catch (error: any) {
        alert(error.response?.data?.message || 'Failed to delete item');
    }
}

onMounted(() => {
    loadItem();
});
</script>

<template>
    <Head title="Item Detail" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="router.visit('/master-data/items')"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-3xl font-bold">Item Detail</h1>
                        <p class="text-muted-foreground">
                            View item information
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        @click="
                            router.visit(`/master-data/items/${props.id}/edit`)
                        "
                    >
                        <Pencil class="mr-2 h-4 w-4" />
                        Edit
                    </Button>
                    <Button variant="destructive" @click="deleteDialog = true">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="py-8 text-center">Loading...</div>

            <!-- Content -->
            <div v-else-if="item" class="space-y-6">
                <!-- Basic Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Basic Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-muted-foreground">SKU</p>
                                <p class="font-mono font-medium">
                                    {{ item.sku }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Name
                                </p>
                                <p class="font-medium">{{ item.name }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Base UOM
                                </p>
                                <p v-if="item.base_uom">
                                    {{ item.base_uom.code }} -
                                    {{ item.base_uom.name }}
                                </p>
                                <p v-else class="text-muted-foreground">-</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Serialized
                                </p>
                                <Badge
                                    v-if="item.is_serialized"
                                    variant="default"
                                >
                                    Serialized
                                </Badge>
                                <span v-else class="text-sm">No</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Criticality Level
                                </p>
                                <Badge
                                    v-if="item.criticality_level"
                                    :variant="
                                        item.criticality_level >= 4
                                            ? 'destructive'
                                            : item.criticality_level >= 3
                                              ? 'default'
                                              : 'secondary'
                                    "
                                >
                                    Level {{ item.criticality_level }}
                                </Badge>
                                <span v-else class="text-sm">Not set</span>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Category
                                </p>
                                <div
                                    v-if="item.category"
                                    class="flex items-center gap-2"
                                >
                                    <div
                                        v-if="item.category.color_code"
                                        class="h-3 w-3 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                item.category.color_code,
                                        }"
                                    ></div>
                                    <Badge variant="outline">
                                        {{ item.category.code }}
                                    </Badge>
                                    <p>{{ item.category.name }}</p>
                                </div>
                                <p v-else class="text-sm text-muted-foreground">
                                    No category
                                </p>
                            </div>
                        </div>

                        <div v-if="item.category?.full_path">
                            <p class="text-sm text-muted-foreground">
                                Category Path
                            </p>
                            <p class="text-sm">{{ item.category.full_path }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Metadata -->
                <Card>
                    <CardHeader>
                        <CardTitle>Metadata</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div v-if="item.created_at">
                                <p class="text-sm text-muted-foreground">
                                    Created At
                                </p>
                                <p>
                                    {{
                                        new Date(
                                            item.created_at,
                                        ).toLocaleString()
                                    }}
                                </p>
                            </div>
                            <div v-if="item.updated_at">
                                <p class="text-sm text-muted-foreground">
                                    Updated At
                                </p>
                                <p>
                                    {{
                                        new Date(
                                            item.updated_at,
                                        ).toLocaleString()
                                    }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="deleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Item</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete item
                        <strong>{{ item?.name }}</strong
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
