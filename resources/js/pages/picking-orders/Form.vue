<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatQty } from '@/lib/format';
import { apiFetch } from '@/services/http';
import {
    fetchDepartments,
    fetchWarehouses,
    type DepartmentDto,
    type ItemDto,
    type UomDto,
    type WarehouseDto,
} from '@/services/masterDataApi';
import { createPickingOrder } from '@/services/pickingOrderApi';
import {
    getAvailableSerialNumbers,
    type ItemSerialNumberDto,
} from '@/services/serialNumberApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps<{ pickingOrderId: number | null }>();

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});

const warehouses = ref<WarehouseDto[]>([]);
const departments = ref<DepartmentDto[]>([]);
const items = ref<ItemDto[]>([]);

type WarehouseLocationDto = {
    id: number;
    warehouse_id: number;
    type: string;
    code: string;
    name: string;
    is_active: boolean;
};

const storageLocations = ref<WarehouseLocationDto[]>([]);

const form = reactive({
    warehouse_id: null as number | null,
    department_id: null as number | null,
    purpose: '' as string,
    picked_at: '' as string,
    remarks: '' as string,
});

const lines = ref<
    Array<{
        item_id: number | null;
        uom_id: number | null;
        source_location_id: number | null;
        qty: number;
        remarks: string;
        __item?: ItemDto;
        __uom?: UomDto;
        __availableStock?: number;
        __availableSerials?: ItemSerialNumberDto[];
        __selectedSerials?: ItemSerialNumberDto[];
    }>
>([]);

const isEdit = computed(() => props.pickingOrderId !== null);

function setApiError(e: any, fallback: string) {
    error.value = e?.payload?.message ?? e?.message ?? fallback;
    fieldErrors.value = (e?.payload?.errors ?? {}) as Record<string, string[]>;
}

function lineError(
    idx: number,
    field: 'item_id' | 'source_location_id' | 'qty',
) {
    const key = `lines.${idx}.${field}`;
    return fieldErrors.value[key] ?? null;
}

async function loadWarehouses() {
    const res = await fetchWarehouses();
    warehouses.value = res.data.filter((w: WarehouseDto) => w.is_active);
}

async function loadDepartments() {
    const res = await fetchDepartments();
    departments.value = res.data;
}

async function loadItems() {
    try {
        const res = await apiFetch<{
            data: { data: ItemDto[]; total: number };
        }>(`/api/items`);

        // Extract items array from paginated response: { data: { data: [...] } }
        if (res.data && Array.isArray(res.data.data)) {
            items.value = res.data.data;
        } else {
            items.value = [];
            console.error('Items API returned unexpected structure:', res);
        }
    } catch (e) {
        console.error('Failed to load items:', e);
        items.value = [];
    }
}

async function loadStorageLocations(warehouseId: number) {
    const res = await apiFetch<{ data: WarehouseLocationDto[] }>(
        `/api/warehouses/${warehouseId}/locations`,
    );
    // Filter only STORAGE type
    storageLocations.value =
        res.data.filter((loc) => loc.type === 'STORAGE' && loc.is_active) || [];
}

async function checkStock(lineIdx: number) {
    const line = lines.value[lineIdx];
    if (!line.item_id || !line.source_location_id) {
        line.__availableStock = undefined;
        line.__availableSerials = undefined;
        return;
    }

    try {
        // Check if item is serialized
        if (line.__item?.is_serialized) {
            // Fetch available serial numbers
            const res = await getAvailableSerialNumbers({
                item_id: line.item_id,
                location_id: line.source_location_id,
                status: 'AVAILABLE',
            });
            line.__availableSerials = res.data;
            line.__availableStock = res.data.length;
        } else {
            // Original logic: fetch qty on hand for non-serialized items
            const res = await apiFetch<{ data: { qty_on_hand: number } }>(
                `/api/stock/location/${line.source_location_id}/item/${line.item_id}${line.uom_id ? `?uom_id=${line.uom_id}` : ''}`,
            );
            line.__availableStock = res.data.qty_on_hand;
            line.__availableSerials = undefined;
        }
    } catch (e) {
        line.__availableStock = 0;
        line.__availableSerials = undefined;
    }
}

function addLine() {
    lines.value.push({
        item_id: null,
        uom_id: null,
        source_location_id: null,
        qty: 0,
        remarks: '',
        __availableStock: undefined,
        __availableSerials: undefined,
        __selectedSerials: undefined,
    });
}

function removeLine(idx: number) {
    lines.value.splice(idx, 1);
}

async function onChangeWarehouse() {
    // Reset locations when warehouse changes
    storageLocations.value = [];
    lines.value.forEach((line) => {
        line.source_location_id = null;
        line.__availableStock = undefined;
        line.__availableSerials = undefined;
        line.__selectedSerials = undefined;
    });

    if (form.warehouse_id) {
        await loadStorageLocations(form.warehouse_id);
    }
}

async function onSelectItem(lineIdx: number, item: ItemDto | null) {
    const line = lines.value[lineIdx];
    line.item_id = item?.id ?? null;
    line.__item = item ?? undefined;
    line.uom_id = item?.base_uom_id ?? null;
    // Note: base_uom relation might not be loaded, skip for now
    line.__availableStock = undefined;
    line.__availableSerials = undefined;
    line.__selectedSerials = undefined;

    if (line.item_id && line.source_location_id) {
        await checkStock(lineIdx);
    }
}

async function onSelectLocation(lineIdx: number) {
    await checkStock(lineIdx);
}

function onSelectSerials(lineIdx: number, serials: ItemSerialNumberDto[]) {
    const line = lines.value[lineIdx];
    line.__selectedSerials = serials;
    // Auto-calculate qty from selected serial numbers
    line.qty = serials.length;
}

async function save() {
    saving.value = true;
    error.value = null;
    fieldErrors.value = {};

    try {
        if (isEdit.value) {
            throw new Error('Edit Picking Order is not supported in MVP');
        }

        if (!form.warehouse_id) {
            throw new Error('Please select a warehouse');
        }

        if (lines.value.length === 0) {
            throw new Error('Please add at least one line item');
        }

        const payload = {
            warehouse_id: form.warehouse_id,
            department_id: form.department_id,
            purpose: form.purpose || null,
            picked_at: form.picked_at || null,
            remarks: form.remarks || null,
            lines: lines.value.map((line) => {
                const linePayload: any = {
                    item_id: line.item_id!,
                    uom_id: line.uom_id,
                    source_location_id: line.source_location_id!,
                    qty: line.qty,
                    remarks: line.remarks || null,
                };

                // If item is serialized, include serial numbers
                if (line.__item?.is_serialized && line.__selectedSerials) {
                    linePayload.serial_numbers = line.__selectedSerials.map(
                        (s) => s.serial_number,
                    );
                }

                return linePayload;
            }),
        };

        const res = await createPickingOrder(payload);
        router.visit(`/picking-orders/${res.data.id}`);
    } catch (e: any) {
        setApiError(e, 'Failed to save picking order');
    } finally {
        saving.value = false;
    }
}

async function load() {
    loading.value = true;
    error.value = null;

    try {
        if (isEdit.value) {
            throw new Error('Edit Picking Order is not supported in MVP');
        }

        await Promise.all([loadWarehouses(), loadDepartments(), loadItems()]);

        form.picked_at = new Date().toISOString().slice(0, 16);

        // Don't add initial line - user must select warehouse first
    } catch (e: any) {
        setApiError(e, 'Failed to load form data');
    } finally {
        loading.value = false;
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Picking Orders', href: '/picking-orders' },
    { title: 'Create', href: '#' },
];

onMounted(load);
</script>

<template>
    <Head title="Create Picking Order" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Create Picking Order</h1>
                <Button variant="outline" as-child>
                    <Link href="/picking-orders">Back</Link>
                </Button>
            </div>

            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

            <div v-if="loading" class="mt-4 text-center">Loading...</div>

            <form v-else @submit.prevent="save" class="mt-4 space-y-6">
                <!-- Header Section -->
                <div class="rounded-lg border p-4">
                    <h2 class="text-sm font-semibold">Header Information</h2>

                    <div class="mt-3 grid gap-3 md:grid-cols-12">
                        <div class="md:col-span-6">
                            <label class="text-sm font-medium"
                                >Warehouse *</label
                            >
                            <div class="mt-1">
                                <select
                                    v-model.number="form.warehouse_id"
                                    @change="onChangeWarehouse"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    required
                                >
                                    <option :value="null">
                                        Select Warehouse
                                    </option>
                                    <option
                                        v-for="w in warehouses"
                                        :key="w.id"
                                        :value="w.id"
                                    >
                                        {{ w.code }} — {{ w.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="md:col-span-6">
                            <label class="text-sm font-medium"
                                >Department</label
                            >
                            <div class="mt-1">
                                <select
                                    v-model.number="form.department_id"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <option :value="null">
                                        Select Department (optional)
                                    </option>
                                    <option
                                        v-for="d in departments"
                                        :key="d.id"
                                        :value="d.id"
                                    >
                                        {{ d.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="md:col-span-6">
                            <label class="text-sm font-medium">Purpose</label>
                            <Input
                                v-model="form.purpose"
                                placeholder="e.g. Production, Sample, Transfer"
                                maxlength="100"
                            />
                        </div>

                        <div class="md:col-span-6">
                            <label class="text-sm font-medium">Picked At</label>
                            <Input
                                v-model="form.picked_at"
                                type="datetime-local"
                            />
                        </div>

                        <div class="md:col-span-12">
                            <label class="text-sm font-medium">Remarks</label>
                            <Input
                                v-model="form.remarks"
                                placeholder="Additional notes"
                            />
                        </div>
                    </div>
                </div>

                <!-- Lines Section -->
                <div class="rounded-lg border p-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold">Items to Pick</h2>
                        <button
                            type="button"
                            @click="addLine"
                            :disabled="!form.warehouse_id"
                            class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-3 text-sm font-medium whitespace-nowrap ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                        >
                            Add Item
                        </button>
                    </div>

                    <div
                        v-if="!form.warehouse_id"
                        class="mt-3 text-sm text-muted-foreground"
                    >
                        Please select a warehouse first.
                    </div>

                    <div
                        v-if="form.warehouse_id && lines.length === 0"
                        class="mt-3"
                    >
                        <p class="text-sm text-muted-foreground">
                            No items added yet. Click "Add Item" to start.
                        </p>
                    </div>

                    <div
                        v-if="form.warehouse_id && lines.length > 0"
                        class="mt-3 space-y-4"
                    >
                        <div
                            v-for="(line, idx) in lines"
                            :key="idx"
                            class="grid gap-3 rounded-md border p-3 md:grid-cols-12"
                        >
                            <!-- Item Selection -->
                            <div class="md:col-span-4">
                                <label class="text-sm font-medium">
                                    Item *
                                </label>
                                <div class="mt-1">
                                    <Multiselect
                                        :model-value="line.__item ?? null"
                                        @update:model-value="
                                            (v: any) => onSelectItem(idx, v)
                                        "
                                        :options="items"
                                        track-by="id"
                                        label="name"
                                        placeholder="Select Item"
                                        :custom-label="
                                            (opt) => `${opt.sku} — ${opt.name}`
                                        "
                                        class="w-full"
                                    />
                                </div>
                                <p
                                    v-if="lineError(idx, 'item_id')"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ lineError(idx, 'item_id')[0] }}
                                </p>
                            </div>

                            <!-- Source Location -->
                            <div class="md:col-span-3">
                                <label class="text-sm font-medium">
                                    Source Location (STORAGE) *
                                </label>
                                <div class="mt-1">
                                    <select
                                        v-model.number="line.source_location_id"
                                        @change="onSelectLocation(idx)"
                                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        required
                                    >
                                        <option :value="null">
                                            Select Location
                                        </option>
                                        <option
                                            v-for="loc in storageLocations"
                                            :key="loc.id"
                                            :value="loc.id"
                                        >
                                            {{ loc.code }} — {{ loc.name }}
                                        </option>
                                    </select>
                                </div>
                                <p
                                    v-if="lineError(idx, 'source_location_id')"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{
                                        lineError(idx, 'source_location_id')[0]
                                    }}
                                </p>
                            </div>

                            <!-- Available Stock Display -->
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium">
                                    Available
                                </label>
                                <div
                                    class="mt-1 flex h-9 items-center rounded-md border bg-muted px-3 text-sm"
                                >
                                    <span
                                        v-if="
                                            line.__availableStock !== undefined
                                        "
                                        :class="{
                                            'text-destructive':
                                                line.__availableStock === 0,
                                        }"
                                    >
                                        {{ formatQty(line.__availableStock) }}
                                        {{ line.__uom?.code ?? '' }}
                                    </span>
                                    <span v-else class="text-muted-foreground">
                                        -
                                    </span>
                                </div>
                            </div>

                            <!-- Qty to Pick OR Serial Number Selection -->
                            <div
                                v-if="!line.__item?.is_serialized"
                                class="md:col-span-2"
                            >
                                <label class="text-sm font-medium">
                                    Qty to Pick *
                                </label>
                                <div class="mt-1">
                                    <Input
                                        v-model.number="line.qty"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        :max="line.__availableStock"
                                        placeholder="0"
                                        required
                                    />
                                </div>
                                <p
                                    v-if="lineError(idx, 'qty')"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ lineError(idx, 'qty')[0] }}
                                </p>
                            </div>

                            <!-- Serial Number Selection for Serialized Items -->
                            <div v-else class="md:col-span-2">
                                <label class="text-sm font-medium">
                                    Serial Numbers *
                                </label>
                                <div class="mt-1">
                                    <Multiselect
                                        :model-value="
                                            line.__selectedSerials ?? []
                                        "
                                        @update:model-value="
                                            (v: any) => onSelectSerials(idx, v)
                                        "
                                        :options="line.__availableSerials ?? []"
                                        track-by="id"
                                        label="serial_number"
                                        placeholder="Select serial numbers"
                                        multiple
                                        :close-on-select="false"
                                        :clear-on-select="false"
                                        class="w-full"
                                    />
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ line.__selectedSerials?.length ?? 0 }}
                                    unit(s) selected
                                </p>
                                <p
                                    v-if="lineError(idx, 'qty')"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ lineError(idx, 'qty')[0] }}
                                </p>
                            </div>

                            <!-- Remove Button -->
                            <div class="flex items-end md:col-span-1">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="icon"
                                    @click="removeLine(idx)"
                                >
                                    ×
                                </Button>
                            </div>

                            <!-- Line Remarks -->
                            <div class="md:col-span-12">
                                <label class="text-sm font-medium">
                                    Line Remarks
                                </label>
                                <Input
                                    v-model="line.remarks"
                                    placeholder="Optional notes for this line"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-2">
                    <Button variant="outline" type="button" as-child>
                        <Link href="/picking-orders">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="saving">
                        {{ saving ? 'Saving...' : 'Save Draft' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
