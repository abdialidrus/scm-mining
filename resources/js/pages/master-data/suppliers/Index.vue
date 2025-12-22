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
import { listSuppliers, type SupplierDto } from '@/services/supplierApi';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref<string | null>(null);
const suppliers = ref<SupplierDto[]>([]);

const search = ref('');
const page = ref(1);
const hasNext = ref(false);
const hasPrev = computed(() => page.value > 1);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const res = await listSuppliers({
            search: search.value || undefined,
            page: page.value,
        });

        const paginated = (res as any).data;
        suppliers.value = (paginated?.data ?? []) as SupplierDto[];

        const meta = paginated?.meta;
        const currentPage = Number(meta?.current_page ?? page.value);
        const lastPage = Number(meta?.last_page ?? currentPage);
        page.value = currentPage;
        hasNext.value = currentPage < lastPage;
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load suppliers';
    } finally {
        loading.value = false;
    }
}

function nextPage() {
    if (!hasNext.value) return;
    page.value += 1;
    load();
}

function prevPage() {
    if (!hasPrev.value) return;
    page.value -= 1;
    load();
}

onMounted(load);
</script>

<template>
    <Head title="Master Data - Suppliers" />

    <AppLayout>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Suppliers</h1>
                <p class="text-sm text-muted-foreground">
                    Maintain supplier master data.
                </p>
            </div>

            <Button as-child>
                <Link href="/master-data/suppliers/create">Create</Link>
            </Button>
        </div>

        <div class="mt-6 flex items-end gap-2">
            <div class="flex-1">
                <label class="text-sm font-medium">Search</label>
                <Input v-model="search" placeholder="code or name" />
            </div>
            <Button
                variant="outline"
                type="button"
                @click="() => ((page = 1), load())"
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
                        <TableHead>Contact</TableHead>
                        <TableHead>Phone</TableHead>
                        <TableHead>Email</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="s in suppliers"
                        :key="s.id"
                        class="cursor-pointer hover:bg-muted/30"
                        @click="router.visit(`/master-data/suppliers/${s.id}`)"
                    >
                        <TableCell class="font-medium">{{ s.code }}</TableCell>
                        <TableCell>{{ s.name }}</TableCell>
                        <TableCell>
                            <span v-if="s.contact_name">{{
                                s.contact_name
                            }}</span>
                            <span v-else class="text-muted-foreground">-</span>
                        </TableCell>
                        <TableCell>
                            <span v-if="s.phone">{{ s.phone }}</span>
                            <span v-else class="text-muted-foreground">-</span>
                        </TableCell>
                        <TableCell>
                            <span v-if="s.email">{{ s.email }}</span>
                            <span v-else class="text-muted-foreground">-</span>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="suppliers.length === 0">
                        <TableCell
                            colspan="5"
                            class="py-6 text-center text-muted-foreground"
                        >
                            No suppliers.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div class="mt-4 flex items-center justify-between">
            <Button
                variant="outline"
                type="button"
                :disabled="!hasPrev"
                @click="prevPage"
                >Previous</Button
            >
            <div class="text-sm text-muted-foreground">Page {{ page }}</div>
            <Button
                variant="outline"
                type="button"
                :disabled="!hasNext"
                @click="nextPage"
                >Next</Button
            >
        </div>
    </AppLayout>
</template>
