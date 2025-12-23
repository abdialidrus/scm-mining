<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { apiFetch } from '@/services/http';
import type { WarehouseDto } from '@/services/masterDataApi';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ warehouseId: number }>();

const loading = ref(true);
const deleting = ref(false);
const error = ref<string | null>(null);
const warehouse = ref<WarehouseDto | null>(null);

const title = computed(() => warehouse.value?.code ?? 'Warehouse');

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await apiFetch<{ data: WarehouseDto }>(
            `/api/warehouses/${props.warehouseId}`,
        );
        warehouse.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load warehouse';
    } finally {
        loading.value = false;
    }
}

async function destroy() {
    if (!warehouse.value) return;
    if (!confirm(`Delete warehouse ${warehouse.value.code}?`)) return;

    deleting.value = true;
    error.value = null;

    try {
        await apiFetch(`/api/warehouses/${warehouse.value.id}`, {
            method: 'DELETE',
        });
        router.visit('/master-data/warehouses');
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to delete';
    } finally {
        deleting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <Head :title="title" />

    <AppLayout>
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">
                        {{ warehouse?.code ?? 'Warehouse' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ warehouse?.name ?? '' }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/master-data/warehouses">Back</Link>
                    </Button>

                    <Button v-if="warehouse" variant="outline" as-child>
                        <Link
                            :href="`/master-data/warehouses/${warehouse.id}/edit`"
                            >Edit</Link
                        >
                    </Button>

                    <Button
                        v-if="warehouse"
                        variant="destructive"
                        type="button"
                        :disabled="deleting"
                        @click="destroy"
                    >
                        {{ deleting ? 'Deleting' : 'Delete' }}
                    </Button>
                </div>
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

            <div v-else-if="warehouse" class="mt-6 space-y-6">
                <div class="rounded-lg border p-4">
                    <div class="grid gap-2 md:grid-cols-2">
                        <div>
                            <span class="text-xs text-muted-foreground"
                                >ID</span
                            >
                            — {{ warehouse.id }}
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Active</span
                            >
                            — {{ warehouse.is_active ? 'Yes' : 'No' }}
                        </div>
                        <div class="md:col-span-2">
                            <span class="text-xs text-muted-foreground"
                                >Address</span
                            >
                            — {{ warehouse.address ?? '-' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
