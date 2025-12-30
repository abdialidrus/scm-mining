<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    createItem,
    getItem,
    getItemCategoryTree,
    listUoms,
    updateItem,
    type ItemCategoryTreeNode,
    type UomDto,
} from '@/services/masterDataApi';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    id?: number;
}>();

const isEditMode = !!props.id;
const loading = ref(false);
const submitting = ref(false);

const uoms = ref<UomDto[]>([]);
const categoryTree = ref<ItemCategoryTreeNode[]>([]);

const form = ref({
    sku: '',
    name: '',
    is_serialized: false,
    criticality_level: null as number | null,
    base_uom_id: null as number | null,
    item_category_id: null as number | null,
});

const errors = ref<Record<string, string>>({});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Items',
        href: '/master-data/items',
    },
    {
        title: isEditMode ? 'Edit Item' : 'Create Item',
        href: '#',
    },
]);

async function loadItem() {
    if (!props.id) return;

    loading.value = true;
    try {
        const response = await getItem(props.id);
        const item = response.data;

        form.value = {
            sku: item.sku,
            name: item.name,
            is_serialized: item.is_serialized || false,
            criticality_level: item.criticality_level || null,
            base_uom_id: item.base_uom_id,
            item_category_id: item.item_category_id || null,
        };
    } catch (error) {
        console.error('Failed to load item:', error);
        alert('Failed to load item');
    } finally {
        loading.value = false;
    }
}

async function loadUoms() {
    try {
        const response = await listUoms({ per_page: 100 });
        const paginated = (response as any).data;
        uoms.value = paginated?.data ?? [];
    } catch (error) {
        console.error('Failed to load UOMs:', error);
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
        result.push({
            id: node.id,
            name: '  '.repeat(depth) + (depth > 0 ? '└ ' : '') + node.name,
            code: node.code,
        });

        if (node.children && node.children.length > 0) {
            result = result.concat(flattenTree(node.children, depth + 1));
        }
    }
    return result;
}

const flatCategories = computed(() => flattenTree(categoryTree.value));

async function handleSubmit() {
    errors.value = {};
    submitting.value = true;

    try {
        const data = {
            sku: form.value.sku,
            name: form.value.name,
            is_serialized: form.value.is_serialized,
            criticality_level: form.value.criticality_level || undefined,
            base_uom_id: form.value.base_uom_id!,
            item_category_id: form.value.item_category_id || null,
        };

        if (isEditMode && props.id) {
            await updateItem(props.id, data);
            alert('Item updated successfully');
        } else {
            await createItem(data);
            alert('Item created successfully');
        }

        router.visit('/master-data/items');
    } catch (error: any) {
        console.error('Failed to save item:', error);

        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else {
            alert(error.response?.data?.message || 'Failed to save item');
        }
    } finally {
        submitting.value = false;
    }
}

onMounted(async () => {
    await Promise.all([loadUoms(), loadCategoryTree()]);

    if (isEditMode) {
        await loadItem();
    }
});
</script>

<template>
    <Head :title="isEditMode ? 'Edit Item' : 'Create Item'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="sm"
                    @click="router.visit('/master-data/items')"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h1 class="text-3xl font-bold">
                        {{ isEditMode ? 'Edit' : 'Create' }} Item
                    </h1>
                    <p class="text-muted-foreground">
                        {{
                            isEditMode
                                ? 'Update item information'
                                : 'Add a new item'
                        }}
                    </p>
                </div>
            </div>

            <!-- Form -->
            <Card>
                <CardHeader>
                    <CardTitle>Item Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <!-- SKU & Name -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="sku">
                                    SKU
                                    <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="sku"
                                    v-model="form.sku"
                                    placeholder="e.g., ITM-001"
                                    :class="{
                                        'border-destructive': errors.sku,
                                    }"
                                />
                                <p
                                    v-if="errors.sku"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.sku }}
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
                                    placeholder="e.g., Engine Oil"
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

                        <!-- Base UOM & Category -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="base_uom_id">
                                    Base UOM
                                    <span class="text-destructive">*</span>
                                </Label>
                                <select
                                    id="base_uom_id"
                                    v-model="form.base_uom_id"
                                    class="w-full rounded-md border bg-background px-3 py-2"
                                    :class="{
                                        'border-destructive':
                                            errors.base_uom_id,
                                    }"
                                >
                                    <option :value="null">
                                        -- Select UOM --
                                    </option>
                                    <option
                                        v-for="uom in uoms"
                                        :key="uom.id"
                                        :value="uom.id"
                                    >
                                        {{ uom.code }} - {{ uom.name }}
                                    </option>
                                </select>
                                <p
                                    v-if="errors.base_uom_id"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.base_uom_id }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="item_category_id">Category</Label>
                                <select
                                    id="item_category_id"
                                    v-model="form.item_category_id"
                                    class="w-full rounded-md border bg-background px-3 py-2"
                                >
                                    <option :value="null">
                                        -- No Category --
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
                                    Optional categorization
                                </p>
                            </div>
                        </div>

                        <!-- Criticality Level -->
                        <div class="space-y-2">
                            <Label for="criticality_level">
                                Criticality Level
                            </Label>
                            <select
                                id="criticality_level"
                                v-model="form.criticality_level"
                                class="w-full rounded-md border bg-background px-3 py-2"
                            >
                                <option :value="null">-- Not Set --</option>
                                <option :value="1">Level 1 - Low</option>
                                <option :value="2">Level 2 - Medium</option>
                                <option :value="3">Level 3 - High</option>
                                <option :value="4">Level 4 - Critical</option>
                                <option :value="5">Level 5 - Essential</option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                Indicates how critical this item is for
                                operations
                            </p>
                        </div>

                        <!-- Is Serialized Checkbox -->
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="is_serialized"
                                :checked="form.is_serialized"
                                @update:checked="form.is_serialized = $event"
                            />
                            <Label for="is_serialized" class="cursor-pointer">
                                Serialized Item
                            </Label>
                            <p class="ml-2 text-xs text-muted-foreground">
                                (requires serial number tracking)
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-2 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                @click="router.visit('/master-data/items')"
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
