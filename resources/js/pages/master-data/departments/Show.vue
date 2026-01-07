<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    deleteDepartment,
    getDepartment,
    type DepartmentDto,
} from '@/services/masterDataApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ departmentId: number }>();

const loading = ref(true);
const deleting = ref(false);
const error = ref<string | null>(null);
const department = ref<DepartmentDto | null>(null);

const title = computed(() =>
    department.value ? `Department ${department.value.name}` : 'Department',
);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await getDepartment(props.departmentId);
        department.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load department';
    } finally {
        loading.value = false;
    }
}

async function destroy() {
    if (!department.value) return;
    if (
        !confirm(
            `Delete department ${department.value.name} (${department.value.code})?`,
        )
    )
        return;

    deleting.value = true;
    error.value = null;

    try {
        await deleteDepartment(department.value.id);
        router.visit('/master-data/departments');
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to delete department';
    } finally {
        deleting.value = false;
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Departments',
        href: '/master-data/departments',
    },
    {
        title: 'Department Details',
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
                        {{ department?.name ?? 'Department' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ department?.code ?? '' }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/master-data/departments">Back</Link>
                    </Button>

                    <Button v-if="department" variant="outline" as-child>
                        <Link
                            :href="`/master-data/departments/${department.id}/edit`"
                            >Edit</Link
                        >
                    </Button>

                    <Button
                        v-if="department"
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

            <div v-else-if="department" class="mt-6 space-y-6">
                <div class="rounded-lg border p-4">
                    <h2 class="mb-4 text-lg font-semibold">
                        Department Information
                    </h2>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >Department Code</span
                            >
                            <span class="font-medium">{{
                                department.code
                            }}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >Department Name</span
                            >
                            <span class="font-medium">{{
                                department.name
                            }}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >Parent Department</span
                            >
                            <span class="font-medium">{{
                                department.parent_id ?? '-'
                            }}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >Department Head</span
                            >
                            <span v-if="department.head" class="font-medium">
                                {{ department.head.name }}
                                <span class="text-xs text-muted-foreground">
                                    ({{ department.head.email }})
                                </span>
                            </span>
                            <span v-else class="font-medium">-</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <h2 class="mb-4 text-lg font-semibold">Record Info</h2>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >ID</span
                            >
                            <span class="font-medium">{{ department.id }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
