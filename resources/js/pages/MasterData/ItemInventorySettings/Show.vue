<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { ArrowLeft, Edit, Trash2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    id: number;
}>();

interface ItemInventorySetting {
    id: number;
    item_id: number;
    warehouse_id: number | null;
    reorder_point: string;
    reorder_quantity: string;
    min_stock: string;
    max_stock: string | null;
    lead_time_days: number;
    safety_stock: string;
    is_active: boolean;
    notes: string | null;
    created_at: string;
    updated_at: string;
    item: { id: number; sku: string; name: string };
    warehouse: { id: number; code: string; name: string } | null;
}

const setting = ref<ItemInventorySetting | null>(null);
const loading = ref(true);
const deleteDialog = ref(false);

async function loadSetting() {
    loading.value = true;
    try {
        const res = await axios.get(`/api/item-inventory-settings/${props.id}`);
        setting.value = res.data.data || res.data;
    } catch (e) {
        console.error('Failed to load setting', e);
        alert('Failed to load inventory setting');
        window.location.href = '/master-data/item-inventory-settings';
    } finally {
        loading.value = false;
    }
}

function editSetting() {
    window.location.href = `/master-data/item-inventory-settings/${props.id}/edit`;
}

async function deleteSetting() {
    try {
        await axios.delete(`/api/item-inventory-settings/${props.id}`);
        window.location.href = '/master-data/item-inventory-settings';
    } catch (e) {
        console.error('Failed to delete', e);
        alert('Failed to delete inventory setting');
    }
}

function goBack() {
    window.location.href = '/master-data/item-inventory-settings';
}

function formatNumber(value: string | null) {
    if (!value) return '-';
    return parseFloat(value).toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    });
}

function formatDate(date: string) {
    return new Date(date).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

onMounted(() => loadSetting());
</script>

<template>
    <AppLayout title="Inventory Setting Details">
        <div class="container mx-auto py-6">
            <div class="mb-6">
                <Button variant="ghost" @click="goBack" class="mb-4">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to List
                </Button>
            </div>

            <div v-if="loading" class="flex justify-center py-12">
                <p class="text-muted-foreground">Loading...</p>
            </div>

            <div v-else-if="setting" class="space-y-6">
                <!-- Header -->
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-3xl font-bold">
                            {{ setting.item.name }}
                        </h1>
                        <p class="text-muted-foreground">
                            {{ setting.item.sku }}
                        </p>
                        <div class="mt-2">
                            <Badge
                                :variant="
                                    setting.is_active ? 'default' : 'secondary'
                                "
                                class="text-sm"
                            >
                                {{ setting.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button @click="editSetting">
                            <Edit class="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                        <Button
                            variant="destructive"
                            @click="deleteDialog = true"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Delete
                        </Button>
                    </div>
                </div>

                <!-- Warehouse Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>Warehouse</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="setting.warehouse">
                            <p class="text-lg font-medium">
                                {{ setting.warehouse.code }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ setting.warehouse.name }}
                            </p>
                        </div>
                        <div v-else>
                            <Badge variant="secondary">Global Setting</Badge>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Applies to all warehouses as default
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Stock Level Thresholds -->
                <Card>
                    <CardHeader>
                        <CardTitle>Stock Level Thresholds</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Reorder Point
                                </p>
                                <p class="text-2xl font-bold">
                                    {{ formatNumber(setting.reorder_point) }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Stock level that triggers reorder
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Reorder Quantity
                                </p>
                                <p class="text-2xl font-bold">
                                    {{ formatNumber(setting.reorder_quantity) }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Quantity to order when reorder point is
                                    reached
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Minimum Stock
                                </p>
                                <p class="text-2xl font-bold">
                                    {{ formatNumber(setting.min_stock) }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Minimum stock level to maintain
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Maximum Stock
                                </p>
                                <p class="text-2xl font-bold">
                                    {{ formatNumber(setting.max_stock) }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Maximum stock level (optional)
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Lead Time & Safety Stock -->
                <Card>
                    <CardHeader>
                        <CardTitle>Lead Time & Safety Stock</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Lead Time
                                </p>
                                <p class="text-2xl font-bold">
                                    {{ setting.lead_time_days }}
                                    <span class="text-lg font-normal"
                                        >days</span
                                    >
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Supplier lead time
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Safety Stock
                                </p>
                                <p class="text-2xl font-bold">
                                    {{ formatNumber(setting.safety_stock) }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Extra buffer stock for emergencies
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Notes -->
                <Card v-if="setting.notes">
                    <CardHeader>
                        <CardTitle>Notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="whitespace-pre-wrap">{{ setting.notes }}</p>
                    </CardContent>
                </Card>

                <!-- Audit Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>Audit Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Created At
                                </p>
                                <p class="font-medium">
                                    {{ formatDate(setting.created_at) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Last Updated
                                </p>
                                <p class="font-medium">
                                    {{ formatDate(setting.updated_at) }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="deleteDialog" @update:open="deleteDialog = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Inventory Setting?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this inventory setting
                        for
                        <strong>{{ setting?.item.name }}</strong>
                        {{
                            setting?.warehouse
                                ? ` at ${setting.warehouse.name}`
                                : ' (Global)'
                        }}? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteDialog = false"
                        >Cancel</Button
                    >
                    <Button variant="destructive" @click="deleteSetting"
                        >Delete</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
