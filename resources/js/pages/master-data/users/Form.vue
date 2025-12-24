<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    fetchDepartments as fetchDepts,
    type DepartmentDto as DeptDto,
} from '@/services/masterDataApi';
import {
    createUser,
    getRoles,
    getUser,
    updateUser,
    type RoleDto,
    type UserDto,
} from '@/services/userAdminApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps<{ userId: number | null }>();

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);

const roles = ref<RoleDto[]>([]);
const departments = ref<DeptDto[]>([]);

const form = reactive({
    name: '',
    email: '',
    password: '',
    department_id: null as number | null,
    roles: [] as string[],
});

const isEdit = computed(() => props.userId !== null);

function setFromDto(dto: UserDto) {
    form.name = dto.name;
    form.email = dto.email;
    form.department_id = dto.department_id ?? null;
    form.roles = (dto.roles ?? []).map((r: { name: string }) => r.name);
}

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const [r, d] = await Promise.all([getRoles(), fetchDepts()]);
        roles.value = r.data;
        departments.value = d.data;

        if (isEdit.value) {
            const res = await getUser(props.userId as number);
            setFromDto(res.data);
        }
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load form';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = null;

    try {
        if (isEdit.value) {
            await updateUser(props.userId as number, {
                name: form.name,
                email: form.email,
                password: form.password || null,
                department_id: form.department_id,
                roles: form.roles,
            });
        } else {
            await createUser({
                name: form.name,
                email: form.email,
                password: form.password,
                department_id: form.department_id,
                roles: form.roles,
            });
        }

        router.visit('/master-data/users');
    } catch (e: any) {
        error.value = e?.payload?.message ?? e?.message ?? 'Failed to save';
    } finally {
        saving.value = false;
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/master-data/users',
    },
    {
        title: isEdit.value ? 'Edit' : 'Create',
        href: '#',
    },
];

onMounted(load);
</script>

<template>
    <Head :title="isEdit ? 'Edit User' : 'Create User'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">
                    {{ isEdit ? 'Edit User' : 'Create User' }}
                </h1>
                <Button variant="outline" as-child>
                    <Link href="/master-data/users">Back</Link>
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

            <form v-else class="mt-6 space-y-6" @submit.prevent="save">
                <div class="space-y-4 rounded-lg border p-4">
                    <div>
                        <label class="text-sm font-medium">Name</label>
                        <Input v-model="form.name" required />
                    </div>

                    <div>
                        <label class="text-sm font-medium">Email</label>
                        <Input v-model="form.email" type="email" required />
                    </div>

                    <div>
                        <label class="text-sm font-medium"
                            >Password
                            {{ isEdit ? '(leave blank to keep)' : '' }}</label
                        >
                        <Input
                            v-model="form.password"
                            type="password"
                            :required="!isEdit"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium">Department</label>
                        <select
                            v-model.number="form.department_id"
                            class="mt-1 w-full rounded-md border bg-background px-2 py-2"
                        >
                            <option :value="null">(none)</option>
                            <option
                                v-for="d in departments"
                                :key="d.id"
                                :value="d.id"
                            >
                                {{ d.code }} — {{ d.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium">Roles</label>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="r in roles"
                                :key="r.name"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    :value="r.name"
                                    v-model="form.roles"
                                />
                                <span>{{ r.name }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="saving">{{
                        saving ? 'Saving…' : 'Save'
                    }}</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
