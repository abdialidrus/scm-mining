<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, Save } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

// Types
type WarehouseDto = {
    id: number;
    code: string;
    name: string;
};

type LocationDto = {
    id: number;
    warehouse_id: number;
    parent_id: number | null;
    type: string;
    code: string;
    name: string;
    capacity: number | null;
    max_weight: number | null;
    is_default: boolean;
    is_active: boolean;
    notes: string | null;
    warehouse?: WarehouseDto;
};

type StockSummaryDto = {
    items_count: number;
    total_quantity: number;
    total_value: number;
};

// Props
const props = defineProps<{
    locationId?: number | null;
}>();

const page = usePage();

// State
const loading = ref(false);
const saving = ref(false);
const error = ref<string | null>(null);
const validationErrors = ref<Record<string, string>>({});

const warehouses = ref<WarehouseDto[]>([]);
const parentLocations = ref<LocationDto[]>([]);
const stockSummary = ref<StockSummaryDto | null>(null);

// Form data
const form = ref({
    warehouse_id: null as number | null,
    parent_id: null as number | null,
    type: 'STORAGE' as string,
    code: '',
    name: '',
    capacity: '' as string | number,
    max_weight: '' as string | number,
    is_default: false,
    is_active: true,
    notes: '',
});

// Computed
const isEditMode = computed(() => !!props.locationId);
const pageTitle = computed(() =>
    isEditMode.value ? 'Edit Warehouse Location' : 'Create Warehouse Location',
);
const hasStock = computed(
    () => stockSummary.value && stockSummary.value.total_quantity > 0,
);
const canChangeWarehouse = computed(() => !isEditMode.value || !hasStock.value);

// Methods
async function loadWarehouses() {
    try {
        const response = await fetch('/api/warehouses?per_page=100');
        if (!response.ok) throw new Error('Failed to load warehouses');
        const data = await response.json();
        warehouses.value = data.data;
    } catch (err: any) {
        console.error('Error loading warehouses:', err);
        error.value = err.message;
    }
}

async function loadParentLocations() {
    if (!form.value.warehouse_id) {
        parentLocations.value = [];
        return;
    }

    try {
        const response = await fetch(
            `/api/warehouses/${form.value.warehouse_id}/locations`,
        );
        if (!response.ok) throw new Error('Failed to load locations');
        const data = await response.json();

        // Filter out current location (can't be parent of itself)
        parentLocations.value = data.data.filter(
            (loc: LocationDto) => loc.id !== props.locationId,
        );
    } catch (err: any) {
        console.error('Error loading parent locations:', err);
    }
}

async function loadLocation() {
    if (!props.locationId) return;

    loading.value = true;
    error.value = null;

    try {
        // Load location details
        const response = await fetch(
            `/api/warehouse-locations/${props.locationId}`,
        );
        if (!response.ok) throw new Error('Failed to load location');
        const data = await response.json();
        const location = data.data;

        // Populate form
        form.value = {
            warehouse_id: location.warehouse_id,
            parent_id: location.parent_id,
            type: location.type,
            code: location.code,
            name: location.name,
            capacity: location.capacity || '',
            max_weight: location.max_weight || '',
            is_default: location.is_default,
            is_active: location.is_active,
            notes: location.notes || '',
        };

        // Load stock summary
        const stockResponse = await fetch(
            `/api/warehouse-locations/${props.locationId}/stock-summary`,
        );
        if (stockResponse.ok) {
            const stockData = await stockResponse.json();
            stockSummary.value = stockData.data;
        }

        // Load parent locations for the selected warehouse
        await loadParentLocations();
    } catch (err: any) {
        console.error('Error loading location:', err);
        error.value = err.message;
    } finally {
        loading.value = false;
    }
}

function onWarehouseChange() {
    // Reset parent when warehouse changes
    form.value.parent_id = null;
    loadParentLocations();
}

async function save() {
    saving.value = true;
    error.value = null;
    validationErrors.value = {};

    try {
        const url = isEditMode.value
            ? `/api/warehouse-locations/${props.locationId}`
            : '/api/warehouse-locations';

        const method = isEditMode.value ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (page.props as any).csrf_token || '',
            },
            body: JSON.stringify(form.value),
        });

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 422 && data.errors) {
                // Validation errors
                validationErrors.value = data.errors;
                error.value = 'Please fix the validation errors below.';
            } else {
                throw new Error(data.message || 'Failed to save location');
            }
            return;
        }

        // Success - redirect to index
        router.visit('/master-data/warehouse-locations', {
            onSuccess: () => {
                // Success message will be shown by Inertia
            },
        });
    } catch (err: any) {
        console.error('Error saving location:', err);
        error.value = err.message;
    } finally {
        saving.value = false;
    }
}

function cancel() {
    router.visit('/master-data/warehouse-locations');
}

// Lifecycle
onMounted(async () => {
    await loadWarehouses();
    if (isEditMode.value) {
        await loadLocation();
    }
});
</script>

<template>
    <AppLayout :title="pageTitle">
        <div class="container mx-auto space-y-6 py-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="mb-2 flex items-center gap-2">
                        <Button variant="ghost" size="sm" @click="cancel">
                            <ArrowLeft class="mr-1 h-4 w-4" />
                            Back
                        </Button>
                    </div>
                    <h1 class="text-3xl font-bold">{{ pageTitle }}</h1>
                    <p class="mt-1 text-muted-foreground">
                        {{
                            isEditMode
                                ? 'Update location details'
                                : 'Create a new warehouse location'
                        }}
                    </p>
                </div>
            </div>

            <!-- Stock Warning (Edit Mode) -->
            <div
                v-if="isEditMode && hasStock"
                class="rounded-lg border border-yellow-200 bg-yellow-50 p-4"
            >
                <div class="flex items-start gap-3">
                    <AlertCircle class="mt-0.5 h-5 w-5 text-yellow-600" />
                    <div>
                        <h4 class="font-semibold text-yellow-900">
                            Location Has Stock
                        </h4>
                        <p class="mt-1 text-sm text-yellow-700">
                            This location currently has
                            <strong
                                >{{ stockSummary?.items_count }} items</strong
                            >
                            with a total quantity of
                            <strong>{{ stockSummary?.total_quantity }}</strong
                            >. You cannot change the warehouse or deactivate
                            this location while it has stock.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <div
                v-if="error"
                class="rounded-lg border border-red-200 bg-red-50 p-4"
            >
                <div class="flex items-start gap-3">
                    <AlertCircle class="mt-0.5 h-5 w-5 text-red-600" />
                    <div>
                        <h4 class="font-semibold text-red-900">Error</h4>
                        <p class="mt-1 text-sm text-red-700">{{ error }}</p>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <Card v-if="loading">
                <CardContent class="py-12 text-center text-muted-foreground">
                    Loading location details...
                </CardContent>
            </Card>

            <!-- Form -->
            <Card v-else>
                <CardHeader>
                    <CardTitle>Location Information</CardTitle>
                    <CardDescription>
                        Provide the details for the warehouse location
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Warehouse -->
                    <div class="space-y-2">
                        <Label for="warehouse_id"
                            >Warehouse
                            <span class="text-red-500">*</span></Label
                        >
                        <Select
                            v-model="form.warehouse_id"
                            @update:model-value="onWarehouseChange"
                            :disabled="!canChangeWarehouse"
                        >
                            <SelectTrigger id="warehouse_id">
                                <SelectValue placeholder="Select warehouse" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="warehouse in warehouses"
                                    :key="warehouse.id"
                                    :value="warehouse.id"
                                >
                                    {{ warehouse.code }} — {{ warehouse.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="validationErrors.warehouse_id"
                            class="text-sm text-red-600"
                        >
                            {{ validationErrors.warehouse_id[0] }}
                        </p>
                        <p
                            v-if="!canChangeWarehouse"
                            class="text-sm text-yellow-600"
                        >
                            Cannot change warehouse while location has stock
                        </p>
                    </div>

                    <!-- Parent Location -->
                    <div class="space-y-2">
                        <Label for="parent_id"
                            >Parent Location (Optional)</Label
                        >
                        <Select
                            v-model="form.parent_id"
                            :disabled="!form.warehouse_id"
                        >
                            <SelectTrigger id="parent_id">
                                <SelectValue
                                    placeholder="No parent (top level)"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null"
                                    >No parent (top level)</SelectItem
                                >
                                <SelectItem
                                    v-for="location in parentLocations"
                                    :key="location.id"
                                    :value="location.id"
                                >
                                    {{ location.code }} — {{ location.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="validationErrors.parent_id"
                            class="text-sm text-red-600"
                        >
                            {{ validationErrors.parent_id[0] }}
                        </p>
                    </div>

                    <!-- Type -->
                    <div class="space-y-2">
                        <Label
                            >Location Type
                            <span class="text-red-500">*</span></Label
                        >
                        <div class="space-y-2">
                            <div class="flex items-center space-x-2">
                                <input
                                    type="radio"
                                    id="type-receiving"
                                    value="RECEIVING"
                                    v-model="form.type"
                                    class="h-4 w-4 text-primary focus:ring-primary"
                                />
                                <Label
                                    for="type-receiving"
                                    class="cursor-pointer font-normal"
                                >
                                    Receiving Area
                                </Label>
                            </div>
                            <div class="flex items-center space-x-2">
                                <input
                                    type="radio"
                                    id="type-storage"
                                    value="STORAGE"
                                    v-model="form.type"
                                    class="h-4 w-4 text-primary focus:ring-primary"
                                />
                                <Label
                                    for="type-storage"
                                    class="cursor-pointer font-normal"
                                >
                                    Storage Area
                                </Label>
                            </div>
                        </div>
                        <p
                            v-if="validationErrors.type"
                            class="text-sm text-red-600"
                        >
                            {{ validationErrors.type[0] }}
                        </p>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Code -->
                        <div class="space-y-2">
                            <Label for="code"
                                >Location Code
                                <span class="text-red-500">*</span></Label
                            >
                            <Input
                                id="code"
                                v-model="form.code"
                                placeholder="e.g., ZONE-A"
                                class="font-mono"
                            />
                            <p
                                v-if="validationErrors.code"
                                class="text-sm text-red-600"
                            >
                                {{ validationErrors.code[0] }}
                            </p>
                        </div>

                        <!-- Name -->
                        <div class="space-y-2">
                            <Label for="name"
                                >Location Name
                                <span class="text-red-500">*</span></Label
                            >
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="e.g., Spare Parts Zone A"
                            />
                            <p
                                v-if="validationErrors.name"
                                class="text-sm text-red-600"
                            >
                                {{ validationErrors.name[0] }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Capacity -->
                        <div class="space-y-2">
                            <Label for="capacity">Capacity (Optional)</Label>
                            <Input
                                id="capacity"
                                v-model="form.capacity"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="e.g., 1000"
                            />
                            <p class="text-sm text-muted-foreground">
                                For future capacity management
                            </p>
                            <p
                                v-if="validationErrors.capacity"
                                class="text-sm text-red-600"
                            >
                                {{ validationErrors.capacity[0] }}
                            </p>
                        </div>

                        <!-- Max Weight -->
                        <div class="space-y-2">
                            <Label for="max_weight"
                                >Max Weight (kg) (Optional)</Label
                            >
                            <Input
                                id="max_weight"
                                v-model="form.max_weight"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="e.g., 5000"
                            />
                            <p class="text-sm text-muted-foreground">
                                Maximum weight capacity
                            </p>
                            <p
                                v-if="validationErrors.max_weight"
                                class="text-sm text-red-600"
                            >
                                {{ validationErrors.max_weight[0] }}
                            </p>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label for="notes">Notes (Optional)</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            placeholder="Additional information about this location"
                            rows="3"
                            class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <p
                            v-if="validationErrors.notes"
                            class="text-sm text-red-600"
                        >
                            {{ validationErrors.notes[0] }}
                        </p>
                    </div>

                    <!-- Checkboxes -->
                    <div class="space-y-4">
                        <div class="flex items-center space-x-2">
                            <Checkbox
                                id="is_default"
                                v-model:checked="form.is_default"
                            />
                            <Label
                                for="is_default"
                                class="cursor-pointer font-normal"
                            >
                                Set as default receiving location for this
                                warehouse
                            </Label>
                        </div>
                        <div class="flex items-center space-x-2">
                            <Checkbox
                                id="is_active"
                                v-model:checked="form.is_active"
                            />
                            <Label
                                for="is_active"
                                class="cursor-pointer font-normal"
                            >
                                Active (location can be used)
                            </Label>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-3 border-t pt-4">
                        <Button @click="save" :disabled="saving">
                            <Save class="mr-2 h-4 w-4" />
                            {{
                                saving
                                    ? 'Saving...'
                                    : isEditMode
                                      ? 'Update Location'
                                      : 'Create Location'
                            }}
                        </Button>
                        <Button
                            variant="outline"
                            @click="cancel"
                            :disabled="saving"
                        >
                            Cancel
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
