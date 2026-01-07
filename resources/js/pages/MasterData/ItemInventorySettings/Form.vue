<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import Input from '@/components/ui/input/Input.vue';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps<{
    setting?: {
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
        notes: string | null;
        item: { id: number; sku: string; name: string };
        warehouse: { id: number; code: string; name: string } | null;
    };
    isEdit?: boolean;
}>();

interface Item {
    id: number;
    sku: string;
    name: string;
}

interface Warehouse {
    id: number;
    code: string;
    name: string;
}

const loading = ref(false);
const submitting = ref(false);
const items = ref<Item[]>([]);
const warehouses = ref<Warehouse[]>([]);
const errors = ref<Record<string, string>>({});

const form = ref({
    item_id: null as number | null,
    warehouse_id: null as number | null,
    reorder_point: '0',
    reorder_quantity: '0',
    min_stock: '0',
    max_stock: null as string | null,
    lead_time_days: 7,
    safety_stock: '0',
    is_active: true,
    notes: null as string | null,
});

const selectedItem = ref<Item | null>(null);
const selectedWarehouse = ref<Warehouse | null>(null);
const isGlobalSetting = ref(false);

const isEditMode = computed(() => !!props.isEdit);
const pageTitle = computed(() =>
    isEditMode.value ? 'Edit Inventory Setting' : 'Create Inventory Setting',
);

async function loadItems(search = '') {
    try {
        const res = await axios.get('/api/items', {
            params: { search, per_page: 50 },
        });
        const data = res.data.data || res.data;
        items.value = Array.isArray(data) ? data : data.data || [];
    } catch (e) {
        console.error('Failed to load items', e);
    }
}

async function loadWarehouses(search = '') {
    try {
        const res = await axios.get('/api/warehouses', {
            params: { search, per_page: 50 },
        });
        const data = res.data.data || res.data;
        warehouses.value = Array.isArray(data) ? data : data.data || [];
    } catch (e) {
        console.error('Failed to load warehouses', e);
    }
}

async function loadSetting() {
    if (!props.setting) return;

    form.value = {
        item_id: props.setting.item_id,
        warehouse_id: props.setting.warehouse_id,
        reorder_point: props.setting.reorder_point,
        reorder_quantity: props.setting.reorder_quantity,
        min_stock: props.setting.min_stock,
        max_stock: props.setting.max_stock,
        lead_time_days: props.setting.lead_time_days,
        safety_stock: props.setting.safety_stock,
        is_active: props.setting.is_active,
        notes: props.setting.notes,
    };

    selectedItem.value = props.setting.item;
    selectedWarehouse.value = props.setting.warehouse;
    isGlobalSetting.value = !props.setting.warehouse_id;
}

function onItemSelect(item: Item | null) {
    selectedItem.value = item;
    form.value.item_id = item?.id || null;
}

function onWarehouseSelect(warehouse: Warehouse | null) {
    selectedWarehouse.value = warehouse;
    form.value.warehouse_id = warehouse?.id || null;
}

function toggleGlobalSetting() {
    isGlobalSetting.value = !isGlobalSetting.value;
    if (isGlobalSetting.value) {
        form.value.warehouse_id = null;
        selectedWarehouse.value = null;
    }
}

async function submit() {
    errors.value = {};
    submitting.value = true;

    try {
        const payload = {
            ...form.value,
            item_id: selectedItem.value?.id,
            warehouse_id: isGlobalSetting.value
                ? null
                : selectedWarehouse.value?.id,
        };

        if (isEditMode.value && props.setting) {
            await axios.put(
                `/api/item-inventory-settings/${props.setting.id}`,
                payload,
            );
        } else {
            await axios.post('/api/item-inventory-settings', payload);
        }

        window.location.href = '/master-data/item-inventory-settings';
    } catch (e: any) {
        if (e.response?.data?.errors) {
            errors.value = e.response.data.errors;
        } else {
            alert(
                e.response?.data?.message || 'Failed to save inventory setting',
            );
        }
    } finally {
        submitting.value = false;
    }
}

function cancel() {
    window.location.href = '/master-data/item-inventory-settings';
}

onMounted(() => {
    loadItems();
    loadWarehouses();
    if (props.setting) {
        loadSetting();
    }
});
</script>

<template>
    <AppLayout :title="pageTitle">
        <div class="container mx-auto py-6">
            <div class="mb-6">
                <Button variant="ghost" @click="cancel" class="mb-4">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to List
                </Button>
                <h1 class="text-3xl font-bold">{{ pageTitle }}</h1>
                <p class="text-muted-foreground">
                    Configure inventory parameters for item stock management
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Inventory Settings</CardTitle>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Item Selection -->
                    <div class="space-y-2">
                        <Label for="item"
                            >Item <span class="text-destructive">*</span></Label
                        >
                        <Multiselect
                            id="item"
                            v-model="selectedItem"
                            :options="items"
                            :searchable="true"
                            :close-on-select="true"
                            :show-labels="false"
                            placeholder="Select item..."
                            label="name"
                            track-by="id"
                            @search-change="loadItems"
                            :disabled="isEditMode"
                        >
                            <template #option="{ option }">
                                <div>
                                    <div class="font-medium">
                                        {{ option.sku }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ option.name }}
                                    </div>
                                </div>
                            </template>
                            <template #singleLabel="{ option }">
                                <span
                                    >{{ option.sku }} - {{ option.name }}</span
                                >
                            </template>
                        </Multiselect>
                        <p
                            v-if="errors.item_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.item_id[0] }}
                        </p>
                    </div>

                    <!-- Warehouse Selection -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="warehouse">Warehouse</Label>
                            <div class="flex items-center space-x-2">
                                <Checkbox
                                    id="global"
                                    :checked="isGlobalSetting"
                                    @update:checked="toggleGlobalSetting"
                                    :disabled="isEditMode"
                                />
                                <Label
                                    for="global"
                                    class="cursor-pointer text-sm font-normal"
                                >
                                    Global Setting (All Warehouses)
                                </Label>
                            </div>
                        </div>
                        <Multiselect
                            v-if="!isGlobalSetting"
                            id="warehouse"
                            v-model="selectedWarehouse"
                            :options="warehouses"
                            :searchable="true"
                            :close-on-select="true"
                            :show-labels="false"
                            placeholder="Select warehouse..."
                            label="name"
                            track-by="id"
                            @search-change="loadWarehouses"
                            :disabled="isEditMode"
                        >
                            <template #option="{ option }">
                                <div>
                                    <div class="font-medium">
                                        {{ option.code }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ option.name }}
                                    </div>
                                </div>
                            </template>
                            <template #singleLabel="{ option }">
                                <span
                                    >{{ option.code }} - {{ option.name }}</span
                                >
                            </template>
                        </Multiselect>
                        <div
                            v-else
                            class="rounded-md bg-muted px-3 py-2 text-sm"
                        >
                            This setting will apply to all warehouses as a
                            default
                        </div>
                        <p
                            v-if="errors.warehouse_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.warehouse_id[0] }}
                        </p>
                    </div>

                    <!-- Stock Level Thresholds -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="reorder_point"
                                >Reorder Point
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="reorder_point"
                                :model-value="
                                    parseFloat(form.reorder_point) || 0
                                "
                                @update:model-value="
                                    form.reorder_point = String($event)
                                "
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="100"
                            />
                            <p class="text-xs text-muted-foreground">
                                Stock level that triggers reorder
                            </p>
                            <p
                                v-if="errors.reorder_point"
                                class="text-sm text-destructive"
                            >
                                {{ errors.reorder_point[0] }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="reorder_quantity"
                                >Reorder Quantity
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="reorder_quantity"
                                :model-value="
                                    parseFloat(form.reorder_quantity) || 0
                                "
                                @update:model-value="
                                    form.reorder_quantity = String($event)
                                "
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="500"
                            />
                            <p class="text-xs text-muted-foreground">
                                Quantity to order when reorder point is reached
                            </p>
                            <p
                                v-if="errors.reorder_quantity"
                                class="text-sm text-destructive"
                            >
                                {{ errors.reorder_quantity[0] }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="min_stock"
                                >Minimum Stock
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="min_stock"
                                :model-value="parseFloat(form.min_stock) || 0"
                                @update:model-value="
                                    form.min_stock = String($event)
                                "
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="50"
                            />
                            <p class="text-xs text-muted-foreground">
                                Minimum stock level to maintain
                            </p>
                            <p
                                v-if="errors.min_stock"
                                class="text-sm text-destructive"
                            >
                                {{ errors.min_stock[0] }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="max_stock">Maximum Stock</Label>
                            <Input
                                id="max_stock"
                                :model-value="
                                    form.max_stock
                                        ? parseFloat(form.max_stock)
                                        : ''
                                "
                                @update:model-value="
                                    form.max_stock = $event
                                        ? String($event)
                                        : null
                                "
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="1000"
                            />
                            <p class="text-xs text-muted-foreground">
                                Maximum stock level (optional)
                            </p>
                            <p
                                v-if="errors.max_stock"
                                class="text-sm text-destructive"
                            >
                                {{ errors.max_stock[0] }}
                            </p>
                        </div>
                    </div>

                    <!-- Lead Time & Safety Stock -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="lead_time_days"
                                >Lead Time (Days)
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="lead_time_days"
                                v-model.number="form.lead_time_days"
                                type="number"
                                min="0"
                                max="365"
                                placeholder="7"
                            />
                            <p class="text-xs text-muted-foreground">
                                Supplier lead time in days
                            </p>
                            <p
                                v-if="errors.lead_time_days"
                                class="text-sm text-destructive"
                            >
                                {{ errors.lead_time_days[0] }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="safety_stock"
                                >Safety Stock
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="safety_stock"
                                :model-value="
                                    parseFloat(form.safety_stock) || 0
                                "
                                @update:model-value="
                                    form.safety_stock = String($event)
                                "
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="25"
                            />
                            <p class="text-xs text-muted-foreground">
                                Extra buffer stock for emergencies
                            </p>
                            <p
                                v-if="errors.safety_stock"
                                class="text-sm text-destructive"
                            >
                                {{ errors.safety_stock[0] }}
                            </p>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="space-y-2">
                        <div class="flex items-center space-x-2">
                            <Checkbox
                                id="is_active"
                                :checked="form.is_active"
                                @update:checked="form.is_active = $event"
                            />
                            <Label for="is_active" class="cursor-pointer">
                                Active
                            </Label>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Inactive settings will not be used in calculations
                        </p>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Additional notes or comments..."
                            maxlength="1000"
                        />
                        <p class="text-xs text-muted-foreground">
                            Optional notes (max 1000 characters)
                        </p>
                        <p v-if="errors.notes" class="text-sm text-destructive">
                            {{ errors.notes[0] }}
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-4 border-t pt-4">
                        <Button
                            variant="outline"
                            @click="cancel"
                            :disabled="submitting"
                        >
                            Cancel
                        </Button>
                        <Button
                            @click="submit"
                            :disabled="submitting || !selectedItem"
                        >
                            <Save class="mr-2 h-4 w-4" />
                            {{ submitting ? 'Saving...' : 'Save Setting' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.multiselect) {
    min-height: 40px;
}

:deep(.multiselect__tags) {
    min-height: 40px;
    padding: 8px 40px 0 8px;
    border-radius: 0.375rem;
    border-color: hsl(var(--input));
}

:deep(.multiselect__input) {
    margin-bottom: 4px;
}

:deep(.multiselect__single) {
    margin-bottom: 4px;
}
</style>
