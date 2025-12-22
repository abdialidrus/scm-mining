<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
            <Table>
                <TableHeader class="bg-muted/40">
                    <TableRow>
                        <TableHead>Code</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Head</TableHead>
                        <TableHead class="text-right">ID</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="d in departments"
                        :key="d.id"
                        class="cursor-pointer hover:bg-muted/30"
                        title="Read-only"
                    >
                        <TableCell class="font-medium">{{ d.code }}</TableCell>
                        <TableCell>{{ d.name }}</TableCell>
                        <TableCell>
                            <span v-if="d.head">{{ d.head.name }}</span>
                            <span v-else class="text-muted-foreground">-</span>
                        </TableCell>
                        <TableCell class="text-right">{{ d.id }}</TableCell>
                    </TableRow>

                    <TableRow v-if="departments.length === 0">
                        <TableCell
                            colspan="4"
                            class="py-6 text-center text-muted-foreground"
                        >
                            No departments.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
