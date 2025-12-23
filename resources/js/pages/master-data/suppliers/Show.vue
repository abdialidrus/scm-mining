<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    deleteSupplier,
    getSupplier,
    type SupplierDto,
} from '@/services/supplierApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ supplierId: number }>();

const loading = ref(true);
const deleting = ref(false);
const error = ref<string | null>(null);
const supplier = ref<SupplierDto | null>(null);

const title = computed(() =>
    supplier.value ? `Supplier ${supplier.value.name}` : 'Supplier',
);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await getSupplier(props.supplierId);
        supplier.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load supplier';
    } finally {
        loading.value = false;
    }
}

async function destroy() {
    if (!supplier.value) return;
    if (
        !confirm(
            `Delete supplier ${supplier.value.name} (${supplier.value.code})?`,
        )
    )
        return;

    deleting.value = true;
    error.value = null;

    try {
        await deleteSupplier(supplier.value.id);
        router.visit('/master-data/suppliers');
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to delete supplier';
    } finally {
        deleting.value = false;
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Suppliers',
        href: '/master-data/suppliers',
    },
    {
        title: 'Supplier Details',
        href: '#',
    },
];

onMounted(load);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">
                        {{ supplier?.name ?? 'Supplier' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ supplier?.code ?? '' }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/master-data/suppliers">Back</Link>
                    </Button>

                    <Button v-if="supplier" variant="outline" as-child>
                        <Link
                            :href="`/master-data/suppliers/${supplier.id}/edit`"
                            >Edit</Link
                        >
                    </Button>

                    <Button
                        v-if="supplier"
                        variant="destructive"
                        type="button"
                        :disabled="deleting"
                        @click="destroy"
                    >
                        {{ deleting ? 'Deleting…' : 'Delete' }}
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
                Loading…
            </div>

            <div v-else-if="supplier" class="mt-6 space-y-6">
                <div class="rounded-lg border p-4">
                    <div class="grid gap-2 md:grid-cols-2">
                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Code</span
                            >
                            — {{ supplier.code }}
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Name</span
                            >
                            — {{ supplier.name }}
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Contact</span
                            >
                            — {{ supplier.contact_name ?? '-' }}
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Phone</span
                            >
                            — {{ supplier.phone ?? '-' }}
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Email</span
                            >
                            — {{ supplier.email ?? '-' }}
                        </div>
                        <div class="md:col-span-2">
                            <span class="text-xs text-muted-foreground"
                                >Address</span
                            >
                            — {{ supplier.address ?? '-' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
