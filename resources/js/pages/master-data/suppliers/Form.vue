<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    createSupplier,
    getSupplier,
    updateSupplier,
    type SupplierDto,
} from '@/services/supplierApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps<{ supplierId: number | null }>();

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);

const form = reactive({
    name: '',
    contact_name: '',
    phone: '',
    email: '',
    address: '',
});

const isEdit = computed(() => props.supplierId !== null);

function setFromDto(dto: SupplierDto) {
    form.name = dto.name ?? '';
    form.contact_name = dto.contact_name ?? '';
    form.phone = dto.phone ?? '';
    form.email = dto.email ?? '';
    form.address = dto.address ?? '';
}

async function load() {
    loading.value = true;
    error.value = null;

    try {
        if (isEdit.value) {
            const res = await getSupplier(props.supplierId as number);
            setFromDto(res.data);
        }
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load supplier';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = null;

    try {
        if (isEdit.value) {
            await updateSupplier(props.supplierId as number, {
                name: form.name || null,
                contact_name: form.contact_name || null,
                phone: form.phone || null,
                email: form.email || null,
                address: form.address || null,
            });
        } else {
            await createSupplier({
                name: form.name,
                contact_name: form.contact_name || null,
                phone: form.phone || null,
                email: form.email || null,
                address: form.address || null,
            });
        }

        router.visit('/master-data/suppliers');
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to save';
    } finally {
        saving.value = false;
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Suppliers',
        href: '/master-data/suppliers',
    },
    {
        title: isEdit.value ? 'Edit' : 'Create',
        href: '#',
    },
];

onMounted(load);
</script>

<template>
    <Head :title="isEdit ? 'Edit Supplier' : 'Create Supplier'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">
                    {{ isEdit ? 'Edit Supplier' : 'Create Supplier' }}
                </h1>
                <Button variant="outline" as-child>
                    <Link href="/master-data/suppliers">Back</Link>
                </Button>
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

            <form v-else class="mt-6 space-y-4" @submit.prevent="save">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium">Supplier Name</label>
                        <Input
                            v-model="form.name"
                            placeholder="Supplier name"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium"
                            >Contact / PIC Name</label
                        >
                        <Input
                            v-model="form.contact_name"
                            placeholder="Contact name"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium">Phone</label>
                        <Input v-model="form.phone" placeholder="Phone" />
                    </div>

                    <div>
                        <label class="text-sm font-medium">Email</label>
                        <Input
                            v-model="form.email"
                            placeholder="email@example.com"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-medium"
                            >Address (optional)</label
                        >
                        <textarea
                            v-model="form.address"
                            rows="3"
                            class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            placeholder="Address"
                        />
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="saving">
                        {{ saving ? 'Saving…' : 'Save' }}
                    </Button>
                    <Button variant="outline" as-child>
                        <Link href="/master-data/suppliers">Cancel</Link>
                    </Button>
                </div>

                <p class="text-xs text-muted-foreground">
                    Supplier code will be auto-generated on create.
                </p>
            </form>
        </div>
    </AppLayout>
</template>
