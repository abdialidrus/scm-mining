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
import {
    deleteItemCategory,
    getItemCategory,
    type ItemCategoryDto,
} from '@/services/masterDataApi';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    id: number;
}>();

const category = ref<ItemCategoryDto | null>(null);
const loading = ref(false);
const deleteDialog = ref(false);

async function loadCategory() {
    loading.value = true;
    try {
        const response = await getItemCategory(props.id);
        category.value = response.data;
    } catch (error) {
        console.error('Failed to load category:', error);
        alert('Failed to load category');
    } finally {
        loading.value = false;
    }
}

async function handleDelete() {
    try {
        await deleteItemCategory(props.id);
        alert('Item category deleted successfully');
        router.visit('/master-data/item-categories');
    } catch (error: any) {
        alert(error.response?.data?.message || 'Failed to delete category');
    }
}

onMounted(() => {
    loadCategory();
});
</script>

<template>
    <Head title="Item Category Detail" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="router.visit('/master-data/item-categories')"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-3xl font-bold">Item Category Detail</h1>
                        <p class="text-muted-foreground">
                            View category information
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        @click="
                            router.visit(
                                `/master-data/item-categories/${props.id}/edit`,
                            )
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
            <div v-else-if="category" class="grid gap-6">
                <!-- Basic Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Basic Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Code
                                </p>
                                <div class="flex items-center gap-2">
                                    <div
                                        v-if="category.color_code"
                                        class="h-4 w-4 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                category.color_code,
                                        }"
                                    ></div>
                                    <p class="font-mono font-medium">
                                        {{ category.code }}
                                    </p>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Name
                                </p>
                                <p class="font-medium">{{ category.name }}</p>
                            </div>
                        </div>

                        <div v-if="category.description">
                            <p class="text-sm text-muted-foreground">
                                Description
                            </p>
                            <p>{{ category.description }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Status
                                </p>
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
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Requires Approval
                                </p>
                                <Badge
                                    v-if="category.requires_approval"
                                    variant="destructive"
                                >
                                    Required
                                </Badge>
                                <span v-else class="text-sm">No</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Sort Order
                                </p>
                                <p>{{ category.sort_order }}</p>
                            </div>
                            <div v-if="category.color_code">
                                <p class="text-sm text-muted-foreground">
                                    Color Code
                                </p>
                                <p class="font-mono">
                                    {{ category.color_code }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Hierarchy Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Hierarchy</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Full Path
                            </p>
                            <p class="font-medium">
                                {{ category.full_path || category.name }}
                            </p>
                        </div>

                        <div v-if="category.parent">
                            <p class="text-sm text-muted-foreground">
                                Parent Category
                            </p>
                            <div class="flex items-center gap-2">
                                <Badge variant="outline">
                                    {{ category.parent.code }}
                                </Badge>
                                <p>{{ category.parent.name }}</p>
                            </div>
                        </div>
                        <div v-else>
                            <Badge variant="secondary">Root Category</Badge>
                        </div>

                        <div
                            v-if="
                                category.children &&
                                category.children.length > 0
                            "
                        >
                            <p class="mb-2 text-sm text-muted-foreground">
                                Subcategories ({{ category.children.length }})
                            </p>
                            <div class="space-y-2">
                                <div
                                    v-for="child in category.children"
                                    :key="child.id"
                                    class="flex items-center gap-2 rounded-md border p-2"
                                >
                                    <div
                                        v-if="child.color_code"
                                        class="h-3 w-3 rounded-full"
                                        :style="{
                                            backgroundColor: child.color_code,
                                        }"
                                    ></div>
                                    <Badge variant="outline">
                                        {{ child.code }}
                                    </Badge>
                                    <p class="flex-1">{{ child.name }}</p>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="
                                            router.visit(
                                                `/master-data/item-categories/${child.id}`,
                                            )
                                        "
                                    >
                                        View
                                    </Button>
                                </div>
                            </div>
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
                            <div v-if="category.created_at">
                                <p class="text-sm text-muted-foreground">
                                    Created At
                                </p>
                                <p>
                                    {{
                                        new Date(
                                            category.created_at,
                                        ).toLocaleString()
                                    }}
                                </p>
                            </div>
                            <div v-if="category.updated_at">
                                <p class="text-sm text-muted-foreground">
                                    Updated At
                                </p>
                                <p>
                                    {{
                                        new Date(
                                            category.updated_at,
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
                    <DialogTitle>Delete Item Category</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete category
                        <strong>{{ category?.name }}</strong
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
