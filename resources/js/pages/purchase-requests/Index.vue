<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    listPurchaseRequests,
    type PurchaseRequestListItemDto,
} from '@/services/purchaseRequestApi';
import { Head, Link } from '@inertiajs/vue3';
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
            <table class="w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr>
                        <th class="px-3 py-2">PR No</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">ID</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="pr in items" :key="pr.id" class="border-t">
                        <td class="px-3 py-2 font-medium">
                            <Link
                                class="hover:underline"
                                :href="`/purchase-requests/${pr.id}`"
                            >
                                {{ pr.pr_number }}
                            </Link>
                        </td>
                        <td class="px-3 py-2">{{ pr.status }}</td>
                        <td class="px-3 py-2">{{ pr.id }}</td>
                        <td class="px-3 py-2 text-right">
                            <Link
                                class="text-primary hover:underline"
                                :href="`/purchase-requests/${pr.id}`"
                            >
                                View
                            </Link>
                        </td>
                    </tr>

                    <tr v-if="items.length === 0" class="border-t">
                        <td
                            colspan="4"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            No purchase requests.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
