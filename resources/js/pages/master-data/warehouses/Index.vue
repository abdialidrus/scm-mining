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
import { fetchWarehouses, type WarehouseDto } from '@/services/masterDataApi';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref<string | null>(null);
const warehouses = ref<WarehouseDto[]>([]);
const search = ref('');

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await fetchWarehouses({ search: search.value });
        warehouses.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load warehouses';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <Head title="Master Data - Warehouses" />

    <AppLayout>
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Warehouses</h1>
                    <p class="text-sm text-muted-foreground">
                        Warehouse master.
                    </p>
                </div>

                <Button as-child>
                    <Link href="/master-data/warehouses/create">Create</Link>
                </Button>
            </div>

            <div class="mt-6 grid items-end gap-3 md:grid-cols-12">
                <div class="md:col-span-10">
                    <label class="text-sm font-medium">Search</label>
                    <div class="mt-1 flex h-10 items-center">
                        <Input
                            v-model="search"
                            class="h-10"
                            placeholder="Enter warehouse code or name"
                        />
                    </div>
                </div>
                <div class="md:col-span-2">
                    <div class="mt-1 flex h-10 items-center">
                        <Button
                            variant="outline"
                            type="button"
                            class="h-10 w-full"
                            @click="load"
                            >Search</Button
                        >
                    </div>
                </div>
            </div>

            <div
                v-if="error"
                class="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm"
            >
                {{ error }}
            </div>

            <div v-if="loading" class="mt-6 text-sm text-muted-foreground">
                Loading...
            </div>

            <div v-else class="mt-6 overflow-hidden rounded-lg border">
                <Table>
                    <TableHeader class="bg-muted/40">
                        <TableRow>
                            <TableHead>Code</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Active</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="wh in warehouses"
                            :key="wh.id"
                            class="cursor-pointer hover:bg-muted/30"
                            @click="
                                router.visit(`/master-data/warehouses/${wh.id}`)
                            "
                        >
                            <TableCell class="font-medium">{{
                                wh.code
                            }}</TableCell>
                            <TableCell>{{ wh.name }}</TableCell>
                            <TableCell>{{
                                wh.is_active ? 'Yes' : 'No'
                            }}</TableCell>
                        </TableRow>

                        <TableRow v-if="warehouses.length === 0">
                            <TableCell
                                colspan="3"
                                class="py-6 text-center text-muted-foreground"
                            >
                                No warehouses.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
