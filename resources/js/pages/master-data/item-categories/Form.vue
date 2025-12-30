<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    createItemCategory,
    getItemCategory,
    getItemCategoryTree,
    updateItemCategory,
    type ItemCategoryTreeNode,
} from '@/services/masterDataApi';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    id?: number;
}>();

const isEditMode = !!props.id;
const loading = ref(false);
const submitting = ref(false);
const categoryTree = ref<ItemCategoryTreeNode[]>([]);

const form = ref({
    code: '',
    name: '',
    description: '',
    parent_id: null as number | null,
    is_active: true,
    requires_approval: false,
    color_code: '',
    sort_order: 0,
});

const errors = ref<Record<string, string>>({});

async function loadCategory() {
    if (!props.id) return;

    loading.value = true;
    try {
        const response = await getItemCategory(props.id);
        const category = response.data;

        form.value = {
            code: category.code,
            name: category.name,
            description: category.description || '',
            parent_id: category.parent_id,
            is_active: category.is_active,
            requires_approval: category.requires_approval,
            color_code: category.color_code || '',
            sort_order: category.sort_order,
        };
    } catch (error) {
        console.error('Failed to load category:', error);
        alert('Failed to load category');
    } finally {
        loading.value = false;
    }
}

async function loadCategoryTree() {
    try {
        const response = await getItemCategoryTree();
        categoryTree.value = response.data;
    } catch (error) {
        console.error('Failed to load category tree:', error);
    }
}

function flattenTree(nodes: ItemCategoryTreeNode[], depth = 0): any[] {
    let result: any[] = [];
    for (const node of nodes) {
        // Skip current category in edit mode to prevent self-selection
        if (isEditMode && node.id === props.id) {
            continue;
        }

        result.push({
            id: node.id,
            name: '  '.repeat(depth) + (depth > 0 ? '└ ' : '') + node.name,
            code: node.code,
            full_path: node.full_path,
        });

        if (node.children && node.children.length > 0) {
            result = result.concat(flattenTree(node.children, depth + 1));
        }
    }
    return result;
}

const flatCategories = ref<any[]>([]);

async function handleSubmit() {
    errors.value = {};
    submitting.value = true;

    try {
        const data = {
            code: form.value.code,
            name: form.value.name,
            description: form.value.description || undefined,
            parent_id: form.value.parent_id || null,
            is_active: form.value.is_active,
            requires_approval: form.value.requires_approval,
            color_code: form.value.color_code || undefined,
            sort_order: form.value.sort_order,
        };

        if (isEditMode && props.id) {
            await updateItemCategory(props.id, data);
            alert('Item category updated successfully');
        } else {
            await createItemCategory(data);
            alert('Item category created successfully');
        }

        router.visit('/master-data/item-categories');
    } catch (error: any) {
        console.error('Failed to save category:', error);

        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else {
            alert(error.response?.data?.message || 'Failed to save category');
        }
    } finally {
        submitting.value = false;
    }
}

onMounted(async () => {
    await loadCategoryTree();
    flatCategories.value = flattenTree(categoryTree.value);

    if (isEditMode) {
        await loadCategory();
    }
});
</script>

<template>
    <Head :title="isEditMode ? 'Edit Item Category' : 'Create Item Category'" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="sm"
                    @click="router.visit('/master-data/item-categories')"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h1 class="text-3xl font-bold">
                        {{ isEditMode ? 'Edit' : 'Create' }} Item Category
                    </h1>
                    <p class="text-muted-foreground">
                        {{
                            isEditMode
                                ? 'Update category information'
                                : 'Add a new item category'
                        }}
                    </p>
                </div>
            </div>

            <!-- Form -->
            <Card>
                <CardHeader>
                    <CardTitle>Category Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <!-- Code & Name -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="code">
                                    Code
                                    <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="code"
                                    v-model="form.code"
                                    placeholder="e.g., SPR, CONS"
                                    :class="{
                                        'border-destructive': errors.code,
                                    }"
                                />
                                <p
                                    v-if="errors.code"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.code }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="name">
                                    Name
                                    <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    placeholder="e.g., Spare Parts"
                                    :class="{
                                        'border-destructive': errors.name,
                                    }"
                                />
                                <p
                                    v-if="errors.name"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.name }}
                                </p>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-md border bg-background px-3 py-2"
                                placeholder="Optional description..."
                            ></textarea>
                        </div>

                        <!-- Parent Category & Sort Order -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="parent_id">Parent Category</Label>
                                <select
                                    id="parent_id"
                                    v-model="form.parent_id"
                                    class="w-full rounded-md border bg-background px-3 py-2"
                                >
                                    <option :value="null">
                                        -- Root Category --
                                    </option>
                                    <option
                                        v-for="cat in flatCategories"
                                        :key="cat.id"
                                        :value="cat.id"
                                    >
                                        {{ cat.name }}
                                    </option>
                                </select>
                                <p class="text-xs text-muted-foreground">
                                    Leave empty for root category
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="sort_order">Sort Order</Label>
                                <Input
                                    id="sort_order"
                                    v-model.number="form.sort_order"
                                    type="number"
                                    placeholder="0"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Lower numbers appear first
                                </p>
                            </div>
                        </div>

                        <!-- Color Code -->
                        <div class="space-y-2">
                            <Label for="color_code">Color Code</Label>
                            <div class="flex gap-2">
                                <Input
                                    id="color_code"
                                    v-model="form.color_code"
                                    type="color"
                                    class="h-10 w-20"
                                />
                                <Input
                                    v-model="form.color_code"
                                    placeholder="#3B82F6"
                                    class="flex-1"
                                />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Optional color for visual identification
                            </p>
                        </div>

                        <!-- Checkboxes -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    id="is_active"
                                    :checked="form.is_active"
                                    @update:checked="form.is_active = $event"
                                />
                                <Label for="is_active" class="cursor-pointer">
                                    Active
                                </Label>
                            </div>

                            <div class="flex items-center gap-2">
                                <Checkbox
                                    id="requires_approval"
                                    :checked="form.requires_approval"
                                    @update:checked="
                                        form.requires_approval = $event
                                    "
                                />
                                <Label
                                    for="requires_approval"
                                    class="cursor-pointer"
                                >
                                    Requires Approval
                                </Label>
                                <p class="text-xs text-muted-foreground">
                                    (for sensitive items like explosives)
                                </p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-2 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                @click="
                                    router.visit('/master-data/item-categories')
                                "
                            >
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="submitting">
                                {{ submitting ? 'Saving...' : 'Save' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
