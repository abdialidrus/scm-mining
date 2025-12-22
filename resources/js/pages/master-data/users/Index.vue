<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    deleteUser,
    listUsers,
    type UserListItemDto,
} from '@/services/userAdminApi';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref<string | null>(null);
const users = ref<UserListItemDto[]>([]);
const search = ref('');

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await listUsers({ search: search.value });
        const page = (res as any).data;
        users.value = (page?.data ?? []) as UserListItemDto[];
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load users';
    } finally {
        loading.value = false;
    }
}

async function remove(id: number) {
    if (!confirm('Delete this user?')) return;

    try {
        await deleteUser(id);
        await load();
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to delete user';
    }
}

onMounted(load);
</script>

<template>
    <Head title="Master Data - Users" />

    <AppLayout>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Users</h1>
                <p class="text-sm text-muted-foreground">
                    Admin CRUD (super_admin only).
                </p>
            </div>

            <Button as-child>
                <Link href="/master-data/users/create">Create</Link>
            </Button>
        </div>

        <div class="mt-6 flex items-end gap-2">
            <div class="flex-1">
                <label class="text-sm font-medium">Search</label>
                <Input v-model="search" placeholder="name or email" />
            </div>
            <Button variant="outline" type="button" @click="load"
                >Search</Button
            >
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

        <div v-else class="mt-6 overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Email</th>
                        <th class="px-3 py-2">Department</th>
                        <th class="px-3 py-2">Roles</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users" :key="u.id" class="border-t">
                        <td class="px-3 py-2 font-medium">{{ u.name }}</td>
                        <td class="px-3 py-2">{{ u.email }}</td>
                        <td class="px-3 py-2">
                            {{ u.department?.code ?? '-' }}
                        </td>
                        <td class="px-3 py-2">
                            <span v-if="u.roles?.length">{{
                                u.roles
                                    .map((r: { name: string }) => r.name)
                                    .join(', ')
                            }}</span>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <Link
                                class="mr-3 text-primary hover:underline"
                                :href="`/master-data/users/${u.id}/edit`"
                                >Edit</Link
                            >
                            <button
                                class="text-destructive hover:underline"
                                type="button"
                                @click="remove(u.id)"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                    <tr v-if="users.length === 0" class="border-t">
                        <td
                            colspan="5"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            No users.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
