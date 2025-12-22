<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchDepartments, type DepartmentDto } from '@/services/masterDataApi';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref<string | null>(null);
const departments = ref<DepartmentDto[]>([]);

const search = ref('');

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await fetchDepartments({ search: search.value });
        departments.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load departments';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <Head title="Master Data - Departments" />

    <AppLayout>
        <div>
            <h1 class="text-xl font-semibold">Departments</h1>
            <p class="text-sm text-muted-foreground">Read-only.</p>
        </div>

        <div class="mt-6 flex items-end gap-2">
            <div class="flex-1">
                <label class="text-sm font-medium">Search</label>
                <Input v-model="search" placeholder="code or name" />
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
                        <th class="px-3 py-2">Code</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Head</th>
                        <th class="px-3 py-2">ID</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in departments" :key="d.id" class="border-t">
                        <td class="px-3 py-2 font-medium">{{ d.code }}</td>
                        <td class="px-3 py-2">{{ d.name }}</td>
                        <td class="px-3 py-2">
                            <span v-if="d.head">{{ d.head.name }}</span>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>
                        <td class="px-3 py-2">{{ d.id }}</td>
                    </tr>
                    <tr v-if="departments.length === 0" class="border-t">
                        <td
                            colspan="4"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            No departments.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
