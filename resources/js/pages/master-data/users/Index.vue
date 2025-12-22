<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { listUsers, type UserListItemDto } from '@/services/userAdminApi';
import { Head, Link, router } from '@inertiajs/vue3';
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
            <Table>
                <TableHeader class="bg-muted/40">
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Department</TableHead>
                        <TableHead>Roles</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="u in users"
                        :key="u.id"
                        class="cursor-pointer hover:bg-muted/30"
                        @click="router.visit(`/master-data/users/${u.id}`)"
                    >
                        <TableCell class="font-medium">{{ u.name }}</TableCell>
                        <TableCell>{{ u.email }}</TableCell>
                        <TableCell>{{ u.department?.code ?? '-' }}</TableCell>
                        <TableCell>
                            <span v-if="u.roles?.length">{{
                                u.roles
                                    .map((r: { name: string }) => r.name)
                                    .join(', ')
                            }}</span>
                            <span v-else class="text-muted-foreground">-</span>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="users.length === 0">
                        <TableCell
                            colspan="4"
                            class="py-6 text-center text-muted-foreground"
                        >
                            No users.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
