<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    listPurchaseRequests,
    type PurchaseRequestListItemDto,
} from '@/services/purchaseRequestApi';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref<string | null>(null);
const items = ref<PurchaseRequestListItemDto[]>([]);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await listPurchaseRequests();
        // Our API wraps paginator under { data: { data: [...] } }
        // Keep defensive defaults in case shape changes.
        const page = (res as any).data;
        items.value = (page?.data ?? []) as PurchaseRequestListItemDto[];
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load purchase requests';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <Head title="Purchase Requests" />

    <AppLayout>
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Purchase Requests</h1>

            <Button as-child>
                <Link href="/purchase-requests/create">Create</Link>
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

        <div v-else class="mt-6 overflow-hidden rounded-lg border">
            <Table>
                <TableHeader class="bg-muted/40">
                    <TableRow>
                        <TableHead>PR No</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">ID</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="pr in items"
                        :key="pr.id"
                        class="cursor-pointer hover:bg-muted/30"
                        @click="router.visit(`/purchase-requests/${pr.id}`)"
                    >
                        <TableCell class="font-medium">{{
                            pr.pr_number
                        }}</TableCell>
                        <TableCell>{{ pr.status }}</TableCell>
                        <TableCell class="text-right">{{ pr.id }}</TableCell>
                    </TableRow>

                    <TableRow v-if="items.length === 0">
                        <TableCell
                            colspan="3"
                            class="py-6 text-center text-muted-foreground"
                        >
                            No purchase requests.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
