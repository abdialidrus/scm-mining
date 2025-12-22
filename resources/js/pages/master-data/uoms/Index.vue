<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchUoms, type UomDto } from '@/services/masterDataApi';
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

onMounted(load);
</script>

<template>
    <Head title="Master Data - UOMs" />

    <AppLayout>
        <div>
            <h1 class="text-xl font-semibold">UOMs</h1>
            <p class="text-sm text-muted-foreground">Read-only.</p>
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
                        <th class="px-3 py-2">Code</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">ID</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in uoms" :key="u.id" class="border-t">
                        <td class="px-3 py-2 font-medium">{{ u.code }}</td>
                        <td class="px-3 py-2">{{ u.name }}</td>
                        <td class="px-3 py-2">{{ u.id }}</td>
                    </tr>
                    <tr v-if="uoms.length === 0" class="border-t">
                        <td
                            colspan="3"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            No UOMs.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
