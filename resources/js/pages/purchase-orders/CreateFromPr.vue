<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { createPurchaseOrder } from '@/services/purchaseOrderApi';
import {
    listPurchaseRequests,
    type PurchaseRequestListItemDto,
} from '@/services/purchaseRequestApi';
import { listSuppliers, type SupplierDto } from '@/services/supplierApi';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);

const supplierSearch = ref('');
const suppliers = ref<SupplierDto[]>([]);
const supplierId = ref<number | null>(null);

const prSearch = ref('');
const prs = ref<PurchaseRequestListItemDto[]>([]);
const selectedPrIds = ref<number[]>([]);

const taxRate = ref<number | null>(null);
const currencyCode = ref<string>('IDR');

const canSave = computed(
    () => supplierId.value !== null && selectedPrIds.value.length > 0,
);

async function loadSuppliers() {
    const res = await listSuppliers({
        search: supplierSearch.value || undefined,
        page: 1,
    });
    const paginated = (res as any).data;
    suppliers.value = (paginated?.data ?? []) as SupplierDto[];
}

async function loadPRs() {
    const res = await listPurchaseRequests({
        search: prSearch.value || undefined,
        status: 'APPROVED',
        page: 1,
    });
    const paginated = (res as any).data;
    prs.value = (paginated?.data ?? []) as PurchaseRequestListItemDto[];
}

function togglePr(id: number) {
    const idx = selectedPrIds.value.indexOf(id);
    if (idx >= 0) selectedPrIds.value.splice(idx, 1);
    else selectedPrIds.value.push(id);
}

async function save() {
    if (!canSave.value) return;

    saving.value = true;
    error.value = null;

    try {
        const payload = {
            supplier_id: supplierId.value as number,
            purchase_request_ids: selectedPrIds.value,
            currency_code: currencyCode.value || null,
            tax_rate: taxRate.value,
        };

        const res = await createPurchaseOrder(payload);
        router.visit(`/purchase-orders/${res.data.id}`);
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to create PO';
    } finally {
        saving.value = false;
    }
}

async function load() {
    loading.value = true;
    error.value = null;
    try {
        await Promise.all([loadSuppliers(), loadPRs()]);
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <Head title="Create PO" />

    <AppLayout>
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Create PO (merge PRs)</h1>
            <Button variant="outline" as-child>
                <Link href="/purchase-orders">Back</Link>
            </Button>
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

        <div v-else class="mt-6 grid gap-6 md:grid-cols-2">
            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Supplier</h2>
                </div>

                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <label class="text-sm font-medium"
                            >Search supplier</label
                        >
                        <Input
                            v-model="supplierSearch"
                            placeholder="code or name"
                        />
                    </div>
                    <Button
                        variant="outline"
                        type="button"
                        @click="loadSuppliers"
                        >Search</Button
                    >
                </div>

                <div class="mt-3 space-y-2">
                    <div
                        v-for="s in suppliers"
                        :key="s.id"
                        class="flex items-center justify-between rounded-md border p-2"
                    >
                        <div>
                            <div class="text-sm font-medium">{{ s.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ s.code }}
                            </div>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                supplierId === s.id ? 'default' : 'outline'
                            "
                            @click="supplierId = s.id"
                        >
                            {{ supplierId === s.id ? 'Selected' : 'Select' }}
                        </Button>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold">
                        Purchase Requests (APPROVED)
                    </h2>
                </div>

                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <label class="text-sm font-medium">Search PR</label>
                        <Input v-model="prSearch" placeholder="PR number" />
                    </div>
                    <Button variant="outline" type="button" @click="loadPRs"
                        >Search</Button
                    >
                </div>

                <div class="mt-3 space-y-2">
                    <div
                        v-for="pr in prs"
                        :key="pr.id"
                        class="flex items-center justify-between rounded-md border p-2"
                    >
                        <div>
                            <div class="text-sm font-medium">
                                {{ pr.pr_number }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ pr.department?.code ?? pr.department_id }}
                            </div>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                selectedPrIds.includes(pr.id)
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="togglePr(pr.id)"
                        >
                            {{
                                selectedPrIds.includes(pr.id)
                                    ? 'Selected'
                                    : 'Select'
                            }}
                        </Button>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border p-4 md:col-span-2">
                <h2 class="text-sm font-semibold">Tax & Currency</h2>

                <div class="mt-3 grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-sm font-medium">Currency</label>
                        <Input v-model="currencyCode" placeholder="IDR" />
                        <p class="mt-1 text-xs text-muted-foreground">
                            Default: IDR
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium">PPN Rate</label>
                        <Input
                            :model-value="
                                taxRate === null ? '' : String(taxRate)
                            "
                            placeholder="0.11"
                            @update:model-value="
                                (v: any) =>
                                    (taxRate = v === '' ? null : Number(v))
                            "
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            Default: 0.11 (11%)
                        </p>
                    </div>
                    <div class="flex items-end">
                        <Button
                            type="button"
                            :disabled="!canSave || saving"
                            @click="save"
                        >
                            {{ saving ? 'Creating...' : 'Create PO Draft' }}
                        </Button>
                    </div>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    NOTE: Supplier consistency is enforced on the backend. If
                    selected PRs belong to different suppliers, creation will
                    fail.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
