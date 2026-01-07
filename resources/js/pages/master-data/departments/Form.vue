<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    createDepartment,
    getDepartment,
    listDepartments,
    updateDepartment,
    type DepartmentDto,
} from '@/services/masterDataApi';
import { listUsers, type UserDto } from '@/services/userAdminApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps<{ departmentId: number | null }>();

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);

const form = reactive({
    code: '',
    name: '',
    parent_id: null as number | null,
    head_user_id: null as number | null,
});

const departments = ref<DepartmentDto[]>([]);
const users = ref<UserDto[]>([]);
const selectedParent = ref<DepartmentDto | null>(null);
const selectedHead = ref<UserDto | null>(null);

const isEdit = computed(() => props.departmentId !== null);

function setFromDto(dto: DepartmentDto) {
    form.code = dto.code ?? '';
    form.name = dto.name ?? '';
    form.parent_id = dto.parent_id;
    form.head_user_id = dto.head_user_id;

    // Set selected parent
    if (dto.parent_id) {
        const parent = departments.value.find((d) => d.id === dto.parent_id);
        if (parent) {
            selectedParent.value = parent;
        }
    }

    // Set selected head
    if (dto.head_user_id && dto.head) {
        selectedHead.value = {
            id: dto.head.id,
            name: dto.head.name,
            email: dto.head.email,
        } as UserDto;
    }
}

async function loadDepartments() {
    try {
        const res = await listDepartments({ per_page: 100 });
        const data = (res as any).data;
        departments.value = (data?.data ?? []) as DepartmentDto[];
    } catch (e) {
        console.error('Failed to load departments', e);
    }
}

async function loadUsers() {
    try {
        const res = await listUsers({ per_page: 100 });
        const data = (res as any).data;
        users.value = (data?.data ?? []) as UserDto[];
    } catch (e) {
        console.error('Failed to load users', e);
    }
}

async function load() {
    loading.value = true;
    error.value = null;

    try {
        await Promise.all([loadDepartments(), loadUsers()]);

        if (isEdit.value) {
            const res = await getDepartment(props.departmentId as number);
            setFromDto(res.data);
        }
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load department';
    } finally {
        loading.value = false;
    }
}

function onParentChange(value: DepartmentDto | null) {
    selectedParent.value = value;
    form.parent_id = value?.id ?? null;
}

function onHeadChange(value: UserDto | null) {
    selectedHead.value = value;
    form.head_user_id = value?.id ?? null;
}

async function save() {
    saving.value = true;
    error.value = null;

    try {
        if (isEdit.value) {
            await updateDepartment(props.departmentId as number, {
                code: form.code || undefined,
                name: form.name || undefined,
                parent_id: form.parent_id,
                head_user_id: form.head_user_id,
            });
        } else {
            await createDepartment({
                code: form.code,
                name: form.name,
                parent_id: form.parent_id,
                head_user_id: form.head_user_id,
            });
        }

        router.visit('/master-data/departments');
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to save';
    } finally {
        saving.value = false;
    }
}

const availableDepartments = computed(() => {
    if (!isEdit.value) return departments.value;
    // Exclude current department to prevent circular reference
    return departments.value.filter((d) => d.id !== props.departmentId);
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Departments',
        href: '/master-data/departments',
    },
    {
        title: isEdit.value ? 'Edit' : 'Create',
        href: '#',
    },
];

onMounted(load);
</script>

<template>
    <Head :title="isEdit ? 'Edit Department' : 'Create Department'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">
                    {{ isEdit ? 'Edit Department' : 'Create Department' }}
                </h1>
                <Button variant="outline" as-child>
                    <Link href="/master-data/departments">Back</Link>
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
                    <div>
                        <label class="text-sm font-medium"
                            >Department Code *</label
                        >
                        <Input
                            v-model="form.code"
                            placeholder="e.g. FIN, HR, IT"
                            required
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium"
                            >Department Name *</label
                        >
                        <Input
                            v-model="form.name"
                            placeholder="Department name"
                            required
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium"
                            >Parent Department</label
                        >
                        <Multiselect
                            v-model="selectedParent"
                            :options="availableDepartments"
                            track-by="id"
                            label="name"
                            placeholder="Select parent department (optional)"
                            :searchable="true"
                            :show-labels="false"
                            :allow-empty="true"
                            @update:model-value="onParentChange"
                        >
                            <template #option="{ option }">
                                <div>
                                    <div class="font-medium">
                                        {{ option.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ option.code }}
                                    </div>
                                </div>
                            </template>
                        </Multiselect>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Optional: Set a parent for hierarchical structure
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-medium"
                            >Department Head</label
                        >
                        <Multiselect
                            v-model="selectedHead"
                            :options="users"
                            track-by="id"
                            label="name"
                            placeholder="Select department head (optional)"
                            :searchable="true"
                            :show-labels="false"
                            :allow-empty="true"
                            @update:model-value="onHeadChange"
                        >
                            <template #option="{ option }">
                                <div>
                                    <div class="font-medium">
                                        {{ option.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ option.email }}
                                    </div>
                                </div>
                            </template>
                        </Multiselect>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Optional: Assign a head/manager to this department
                        </p>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="saving">
                        {{ saving ? 'Saving…' : 'Save' }}
                    </Button>
                    <Button variant="outline" as-child>
                        <Link href="/master-data/departments">Cancel</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
