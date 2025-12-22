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
import {
    listGoodsReceipts,
    type GoodsReceiptDto,
} from '@/services/goodsReceiptApi';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref<string | null>(null);
const receipts = ref<GoodsReceiptDto[]>([]);
const search = ref('');
const status = ref('');

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await listGoodsReceipts({
            search: search.value,
            status: status.value,
        });
        const page = (res as any).data;
        receipts.value = (page?.data ?? []) as GoodsReceiptDto[];
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load goods receipts';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <Head title="Goods Receipts" />

    <AppLayout>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Goods Receipts</h1>
                <p class="text-sm text-muted-foreground">
                    Receive goods against Purchase Orders.
                </p>
            </div>

            <Button as-child>
                <Link href="/goods-receipts/create">Create</Link>
            </Button>
        </div>

        <div class="mt-6 grid gap-2 md:grid-cols-3">
            <div>
                <label class="text-sm font-medium">Search</label>
                <Input v-model="search" placeholder="GR number or PO number" />
            </div>

            <div>
                <label class="text-sm font-medium">Status</label>
                <select
                    v-model="status"
                    class="mt-1 w-full rounded-md border bg-background px-2 py-2"
                >
                    <option value="">(all)</option>
                    <option value="DRAFT">DRAFT</option>
                    <option value="POSTED">POSTED</option>
                    <option value="CANCELLED">CANCELLED</option>
                </select>
            </div>

            <div class="flex items-end">
                <Button variant="outline" type="button" @click="load"
                    >Search</Button
                >
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
                        <TableHead>GR No</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>PO</TableHead>
                        <TableHead>Warehouse</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="gr in receipts"
                        :key="gr.id"
                        class="cursor-pointer hover:bg-muted/30"
                        @click="router.visit(`/goods-receipts/${gr.id}`)"
                    >
                        <TableCell class="font-medium">{{
                            gr.gr_number
                        }}</TableCell>
                        <TableCell>{{ gr.status }}</TableCell>
                        <TableCell>{{
                            gr.purchaseOrder?.po_number ?? gr.purchase_order_id
                        }}</TableCell>
                        <TableCell>
                            {{
                                gr.warehouse
                                    ? `${gr.warehouse.code}  ${gr.warehouse.name}`
                                    : gr.warehouse_id
                            }}
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="receipts.length === 0">
                        <TableCell
                            colspan="4"
                            class="py-6 text-center text-muted-foreground"
                        >
                            No goods receipts.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
