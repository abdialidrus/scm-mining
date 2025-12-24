<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { apiFetch } from '@/services/http';
import { type WarehouseDto } from '@/services/masterDataApi';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps<{ warehouseId: number | null }>();

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);

const form = reactive({
    code: '',
    name: '',
    address: '' as string,
    is_active: true,
    auto_create_storage: false,
});

const isEdit = computed(() => props.warehouseId !== null);

function setFromDto(dto: WarehouseDto) {
    form.code = dto.code;
    form.name = dto.name;
    form.address = dto.address ?? '';
    form.is_active = Boolean(dto.is_active);
}

async function load() {
    loading.value = true;
    error.value = null;

    try {
        if (isEdit.value) {
            const res = await apiFetch<{ data: WarehouseDto }>(
                `/api/warehouses/${props.warehouseId}`,
            );
            setFromDto(res.data);
        }
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load warehouse';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = null;

    try {
        const payload = {
            code: form.code,
            name: form.name,
            address: form.address || null,
            is_active: form.is_active,
            auto_create_storage: form.auto_create_storage,
        };

        if (isEdit.value) {
            await apiFetch(`/api/warehouses/${props.warehouseId}`, {
                method: 'PUT',
                body: JSON.stringify(payload),
            });
        } else {
            await apiFetch('/api/warehouses', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        }

        router.visit('/master-data/warehouses');
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to save';
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <Head :title="isEdit ? 'Edit Warehouse' : 'Create Warehouse'" />

    <AppLayout>
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">
                    {{ isEdit ? 'Edit Warehouse' : 'Create Warehouse' }}
                </h1>
                <Button variant="outline" as-child>
                    <Link href="/master-data/warehouses">Back</Link>
                </Button>
            </div>

            <div
                v-if="error"
                class="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm"
            >
                {{ error }}
            </div>

            <div v-if="loading" class="mt-6 text-sm text-muted-foreground">
                Loading...
            </div>

            <div v-else class="mt-6 max-w-2xl space-y-4">
                <div>
                    <label class="text-sm font-medium">Code</label>
                    <Input v-model="form.code" placeholder="WH-MAIN" />
                </div>

                <div>
                    <label class="text-sm font-medium">Name</label>
                    <Input v-model="form.name" placeholder="Main Warehouse" />
                </div>

                <div>
                    <label class="text-sm font-medium">Address</label>
                    <textarea
                        v-model="form.address"
                        rows="3"
                        class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="form.is_active" />
                    <span class="text-sm">Active</span>
                </div>

                <div v-if="!isEdit" class="flex items-center gap-2">
                    <input type="checkbox" v-model="form.auto_create_storage" />
                    <span class="text-sm"
                        >Auto-create default STORAGE location</span
                    >
                </div>

                <div class="flex gap-2">
                    <Button :disabled="saving" type="button" @click="save">
                        {{ saving ? 'Saving...' : 'Save' }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
