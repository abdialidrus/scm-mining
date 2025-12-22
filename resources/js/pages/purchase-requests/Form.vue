<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchDepartments, type DepartmentDto } from '@/services/masterDataApi';
import {
    createPurchaseRequest,
    fetchItems,
    fetchUoms,
    getPurchaseRequest,
    updatePurchaseRequest,
    type ItemDto,
    type PurchaseRequestDto,
    type UomDto,
} from '@/services/purchaseRequestApi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps<{ purchaseRequestId: number | null }>();

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);

const uoms = ref<UomDto[]>([]);
const items = ref<ItemDto[]>([]);
const itemSearch = ref('');
const departments = ref<DepartmentDto[]>([]);

const page = usePage();
const userDepartmentId = computed(
    () => (page.props.auth?.user as any)?.department_id ?? null,
);

const form = reactive({
    department_id: 1,
    remarks: '' as string,
    lines: [
        {
            item_id: 0,
            quantity: 1,
            uom_id: null as number | null,
            remarks: '' as string,
        },
    ],
});

const isEdit = computed(() => props.purchaseRequestId !== null);

function setFromDto(dto: PurchaseRequestDto) {
    form.department_id = dto.department_id;
    form.remarks = dto.remarks ?? '';
    form.lines = (dto.lines ?? []).map((l) => ({
        item_id: l.item_id,
        quantity: Number(l.quantity),
        uom_id: l.uom_id ?? null,
        remarks: l.remarks ?? '',
    }));

    if (form.lines.length === 0) {
        form.lines.push({ item_id: 0, quantity: 1, uom_id: null, remarks: '' });
    }
}

async function loadMasters() {
    const [u, it, d] = await Promise.all([
        fetchUoms(),
        fetchItems({ limit: 50 }),
        fetchDepartments(),
    ]);
    uoms.value = u.data;
    items.value = it.data;
    departments.value = d.data;
}

async function loadExisting() {
    if (!isEdit.value) return;
    const res = await getPurchaseRequest(props.purchaseRequestId as number);
    setFromDto(res.data);
}

async function load() {
    loading.value = true;
    error.value = null;

    try {
        await loadMasters();

        // For CREATE: force department_id to user's department.
        // For EDIT: department_id comes from PR payload.
        if (!isEdit.value && userDepartmentId.value) {
            form.department_id = Number(userDepartmentId.value);
        }

        await loadExisting();

        // If departments loaded and user has department, keep it consistent.
        if (!isEdit.value && userDepartmentId.value) {
            form.department_id = Number(userDepartmentId.value);
        }
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load form';
    } finally {
        loading.value = false;
    }
}

function addLine() {
    form.lines.push({ item_id: 0, quantity: 1, uom_id: null, remarks: '' });
}

function removeLine(idx: number) {
    if (form.lines.length <= 1) return;
    form.lines.splice(idx, 1);
}

async function doSearchItems() {
    try {
        const res = await fetchItems({ search: itemSearch.value, limit: 50 });
        items.value = res.data;
    } catch {
        // ignore
    }
}

function onSelectItem(line: {
    item_id: number;
    quantity: number;
    uom_id: number | null;
    remarks: string;
}) {
    if (line.uom_id) return;

    const it = items.value.find((x) => x.id === line.item_id);
    if (!it) return;

    if (it.base_uom_id) {
        line.uom_id = it.base_uom_id;
    }
}

const fieldErrors = ref<Record<string, string[]>>({});

function setApiError(e: any, fallback: string) {
    error.value = e?.payload?.message ?? e?.message ?? fallback;
    fieldErrors.value = (e?.payload?.errors ?? {}) as Record<string, string[]>;
}

async function save() {
    saving.value = true;
    error.value = null;
    fieldErrors.value = {};

    try {
        const payload = {
            department_id: form.department_id,
            remarks: form.remarks || null,
            lines: form.lines
                .filter((l) => l.item_id && Number(l.quantity) > 0)
                .map((l) => ({
                    item_id: l.item_id,
                    quantity: Number(l.quantity),
                    uom_id: l.uom_id,
                    remarks: l.remarks || null,
                })),
        };

        if (!payload.lines.length) {
            throw new Error('At least 1 line is required.');
        }

        if (isEdit.value) {
            await updatePurchaseRequest(props.purchaseRequestId as number, {
                remarks: payload.remarks,
                lines: payload.lines,
            });
        } else {
            await createPurchaseRequest(payload);
        }

        router.visit('/purchase-requests');
    } catch (e: any) {
        setApiError(e, 'Failed to save');
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <Head
        :title="isEdit ? 'Edit Purchase Request' : 'Create Purchase Request'"
    />

    <AppLayout>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">
                    {{
                        isEdit
                            ? 'Edit Purchase Request'
                            : 'Create Purchase Request'
                    }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Draft only. Submit from the detail page.
                </p>
            </div>

            <Button variant="outline" as-child>
                <Link href="/purchase-requests">Back</Link>
            </Button>
        </div>

        <div
            v-if="error"
            class="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm"
        >
            <div class="font-medium">{{ error }}</div>
            <ul
                v-if="Object.keys(fieldErrors).length"
                class="mt-2 list-disc pl-5"
            >
                <li v-for="(errs, k) in fieldErrors" :key="k">
                    <span class="font-medium">{{ k }}:</span>
                    {{ errs.join(', ') }}
                </li>
            </ul>
        </div>

        <div v-if="loading" class="mt-6 text-sm text-muted-foreground">
            Loading…
        </div>

        <form v-else class="mt-6 space-y-6" @submit.prevent="save">
            <div class="rounded-lg border p-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium">Department</label>
                        <select
                            v-model.number="form.department_id"
                            :disabled="true"
                            class="mt-1 w-full rounded-md border bg-background px-2 py-2 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <option
                                v-for="d in departments"
                                :key="d.id"
                                :value="d.id"
                            >
                                {{ d.code }} — {{ d.name }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-muted-foreground">
                            This is set from your user department and cannot be
                            changed.
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-medium">Remarks</label>
                        <textarea
                            v-model="form.remarks"
                            rows="3"
                            class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Lines</h2>
                    <Button type="button" variant="outline" @click="addLine"
                        >Add line</Button
                    >
                </div>

                <div class="mb-4 flex items-end gap-2">
                    <div class="flex-1">
                        <label class="text-sm font-medium">Search Items</label>
                        <Input
                            v-model="itemSearch"
                            placeholder="Search by SKU/name"
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        @click="doSearchItems"
                        >Search</Button
                    >
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(line, idx) in form.lines"
                        :key="idx"
                        class="grid gap-3 rounded-md border p-3 md:grid-cols-12"
                    >
                        <div class="md:col-span-5">
                            <label class="text-xs font-medium">Item</label>
                            <select
                                v-model.number="line.item_id"
                                class="mt-1 w-full rounded-md border bg-background px-2 py-2"
                                @change="onSelectItem(line)"
                            >
                                <option :value="0">Select item…</option>
                                <option
                                    v-for="it in items"
                                    :key="it.id"
                                    :value="it.id"
                                >
                                    {{ it.sku }} — {{ it.name }}
                                </option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-xs font-medium">Qty</label>
                            <Input
                                v-model.number="line.quantity"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                        </div>

                        <div class="md:col-span-3">
                            <label class="text-xs font-medium">UOM</label>
                            <select
                                v-model.number="line.uom_id"
                                class="mt-1 w-full rounded-md border bg-background px-2 py-2"
                            >
                                <option :value="null">(auto/none)</option>
                                <option
                                    v-for="u in uoms"
                                    :key="u.id"
                                    :value="u.id"
                                >
                                    {{ u.code }} — {{ u.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-end justify-end md:col-span-2">
                            <Button
                                type="button"
                                variant="destructive"
                                @click="removeLine(idx)"
                                >Remove</Button
                            >
                        </div>

                        <div class="md:col-span-12">
                            <label class="text-xs font-medium"
                                >Line remarks</label
                            >
                            <Input
                                v-model="line.remarks"
                                placeholder="Optional"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="saving">
                    {{ saving ? 'Saving…' : 'Save Draft' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
