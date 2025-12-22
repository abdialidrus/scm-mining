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
import { fetchItems, type ItemDto } from '@/services/masterDataApi';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref<string | null>(null);
const items = ref<ItemDto[]>([]);

const search = ref('');

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await fetchItems({ search: search.value, limit: 50 });
        items.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load items';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <Head title="Master Data - Items" />

    <AppLayout>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Items</h1>
                <p class="text-sm text-muted-foreground">Read-only.</p>
            </div>
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
                        <TableHead>SKU</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Base UOM</TableHead>
                        <TableHead class="text-right">ID</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="it in items"
                        :key="it.id"
                        class="cursor-pointer hover:bg-muted/30"
                        title="Read-only"
                    >
                        <TableCell class="font-medium">{{ it.sku }}</TableCell>
                        <TableCell>{{ it.name }}</TableCell>
                        <TableCell>{{ it.base_uom_code ?? '-' }}</TableCell>
                        <TableCell class="text-right">{{ it.id }}</TableCell>
                    </TableRow>

                    <TableRow v-if="items.length === 0">
                        <TableCell
                            colspan="4"
                            class="py-6 text-center text-muted-foreground"
                        >
                            No items.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
