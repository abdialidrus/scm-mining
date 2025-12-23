<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { deleteUser, getUser, type UserDto } from '@/services/userAdminApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ userId: number }>();

const loading = ref(true);
const deleting = ref(false);
const error = ref<string | null>(null);
const user = ref<UserDto | null>(null);

const title = computed(() => (user.value ? `User ${user.value.name}` : 'User'));

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await getUser(props.userId);
        user.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load user';
    } finally {
        loading.value = false;
    }
}

async function destroy() {
    if (!user.value) return;

    if (!confirm(`Delete user ${user.value.email}?`)) return;

    deleting.value = true;
    error.value = null;

    try {
        await deleteUser(user.value.id);
        router.visit('/master-data/users');
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to delete user';
    } finally {
        deleting.value = false;
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/master-data/users',
    },
    {
        title: 'User Details',
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
                        {{ user?.name ?? 'User' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ user?.email ?? '' }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/master-data/users">Back</Link>
                    </Button>

                    <Button v-if="user" variant="outline" as-child>
                        <Link :href="`/master-data/users/${user.id}/edit`"
                            >Edit</Link
                        >
                    </Button>

                    <Button
                        v-if="user"
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

            <div v-else-if="user" class="mt-6 space-y-6">
                <div class="rounded-lg border p-4">
                    <div class="grid gap-2 md:grid-cols-2">
                        <div>
                            <span class="text-xs text-muted-foreground"
                                >ID</span
                            >
                            — {{ user.id }}
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground"
                                >Department</span
                            >
                            —
                            {{
                                user.department?.code ??
                                user.department_id ??
                                '-'
                            }}
                        </div>
                        <div class="md:col-span-2">
                            <span class="text-xs text-muted-foreground"
                                >Roles</span
                            >
                            —
                            <span v-if="user.roles?.length">{{
                                user.roles.map((r) => r.name).join(', ')
                            }}</span>
                            <span v-else>-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
