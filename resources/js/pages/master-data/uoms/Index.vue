<script setup lang="ts">
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchUoms, type UomDto } from '@/services/masterDataApi';
import { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref<string | null>(null);
const uoms = ref<UomDto[]>([]);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await fetchUoms();
        uoms.value = res.data;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load UOMs';
    } finally {
        loading.value = false;
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'UOMs',
        href: '/master-data/uoms',
    },
];

onMounted(load);
</script>

<template>
    <Head title="Master Data - UOMs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div>
                <h1 class="text-xl font-semibold">UOMs</h1>
                <p class="text-sm text-muted-foreground">
                    Manage UOMs (Unit of Measurement)
                </p>
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
                            <TableHead class="text-right">ID</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="u in uoms"
                            :key="u.id"
                            class="cursor-pointer hover:bg-muted/30"
                            title="Read-only"
                        >
                            <TableCell class="font-medium">{{
                                u.code
                            }}</TableCell>
                            <TableCell>{{ u.name }}</TableCell>
                            <TableCell class="text-right">{{ u.id }}</TableCell>
                        </TableRow>

                        <TableRow v-if="uoms.length === 0">
                            <TableCell
                                colspan="3"
                                class="py-6 text-center text-muted-foreground"
                            >
                                No UOMs.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
