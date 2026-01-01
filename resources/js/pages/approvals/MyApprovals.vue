<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { formatCurrency, formatDateTime } from '@/lib/format';
import {
    getApprovalStatistics,
    getMyPendingApprovals,
    type PendingApprovalDto,
} from '@/services/approvalApi';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Clock,
    TrendingUp,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'My Approvals',
        href: '/my-approvals',
    },
];

const loading = ref(true);
const approvals = ref<PendingApprovalDto[]>([]);
const statistics = ref<{
    pending_count: number;
    approved_last_30_days: number;
    rejected_last_30_days: number;
    average_approval_time_hours: number | null;
} | null>(null);

// Filters
const searchQuery = ref('');
const selectedDocType = ref<{ name: string; value: string } | null>(null);
const documentTypes = [
    { name: 'All Documents', value: '' },
    { name: 'Purchase Request', value: 'purchase_request' },
    { name: 'Purchase Order', value: 'purchase_order' },
];

// Pagination
const currentPage = ref(1);
const perPage = ref(15);
const totalPages = ref(1);
const paginationMeta = ref<any>(null);

const displayedPages = computed(() => {
    const pages: (number | string)[] = [];
    const delta = 2;

    for (let i = 1; i <= totalPages.value; i++) {
        if (
            i === 1 ||
            i === totalPages.value ||
            (i >= currentPage.value - delta && i <= currentPage.value + delta)
        ) {
            pages.push(i);
        } else if (pages[pages.length - 1] !== '...') {
            pages.push('...');
        }
    }

    return pages;
});

async function loadApprovals() {
    loading.value = true;
    try {
        const [approvalsRes, statsRes] = await Promise.all([
            getMyPendingApprovals({
                search: searchQuery.value,
                document_type: selectedDocType.value?.value || undefined,
                page: currentPage.value,
                per_page: perPage.value,
            }),
            getApprovalStatistics(),
        ]);

        approvals.value = approvalsRes.data.data;
        paginationMeta.value = approvalsRes.data.meta;
        totalPages.value = paginationMeta.value?.last_page || 1;
        statistics.value = statsRes.data;
    } catch (error) {
        console.error('Failed to load approvals:', error);
    } finally {
        loading.value = false;
    }
}

function handleSearch() {
    currentPage.value = 1;
    loadApprovals();
}

function handleDocTypeChange() {
    currentPage.value = 1;
    loadApprovals();
}

function goToPage(page: number) {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
        loadApprovals();
    }
}

function handleRowClick(approval: PendingApprovalDto) {
    if (approval.document.url) {
        router.visit(approval.document.url);
    }
}

onMounted(() => {
    loadApprovals();
});
</script>

<template>
    <Head title="My Approvals" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        My Approvals
                    </h1>
                    <p class="mt-1 text-muted-foreground">
                        Review and approve pending documents
                    </p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div
                v-if="statistics"
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-4"
            >
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Pending Approvals
                        </CardTitle>
                        <Clock class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ statistics.pending_count }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Awaiting your action
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Approved (30 days)
                        </CardTitle>
                        <CheckCircle class="h-4 w-4 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ statistics.approved_last_30_days }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Last 30 days
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Rejected (30 days)
                        </CardTitle>
                        <XCircle class="h-4 w-4 text-red-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ statistics.rejected_last_30_days }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Last 30 days
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Avg. Approval Time
                        </CardTitle>
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{
                                statistics.average_approval_time_hours
                                    ? statistics.average_approval_time_hours.toFixed(
                                          1,
                                      ) + 'h'
                                    : 'N/A'
                            }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Last 30 days
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card>
                <CardHeader>
                    <CardTitle>Pending Approvals</CardTitle>
                    <CardDescription>
                        Documents waiting for your approval
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="mb-4 flex flex-col gap-4 md:flex-row">
                        <div class="flex-1">
                            <Input
                                v-model="searchQuery"
                                placeholder="Search by document number..."
                                @keyup.enter="handleSearch"
                            />
                        </div>
                        <div class="w-full md:w-64">
                            <Multiselect
                                v-model="selectedDocType"
                                :options="documentTypes"
                                label="name"
                                track-by="value"
                                placeholder="Filter by type"
                                :searchable="false"
                                :show-labels="false"
                                @update:model-value="handleDocTypeChange"
                            />
                        </div>
                        <Button @click="handleSearch">Search</Button>
                    </div>

                    <!-- Table -->
                    <div v-if="loading" class="py-8 text-center">
                        <p class="text-muted-foreground">Loading...</p>
                    </div>

                    <div
                        v-else-if="approvals.length === 0"
                        class="py-8 text-center"
                    >
                        <p class="text-muted-foreground">
                            No pending approvals found
                        </p>
                    </div>

                    <div v-else class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Document Type</TableHead>
                                    <TableHead>Document Number</TableHead>
                                    <TableHead>Step</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Submitted By</TableHead>
                                    <TableHead>Submitted At</TableHead>
                                    <TableHead>Pending Since</TableHead>
                                    <TableHead class="text-right"
                                        >Actions</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="approval in approvals"
                                    :key="approval.id"
                                    class="cursor-pointer hover:bg-muted/50"
                                    @click="handleRowClick(approval)"
                                >
                                    <TableCell>
                                        <span
                                            class="rounded bg-blue-100 px-2 py-1 text-sm font-medium text-blue-800"
                                        >
                                            {{ approval.document.type }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="font-medium">
                                        {{ approval.document.number }}
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            v-if="approval.step"
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ approval.step.step_name }}
                                        </span>
                                        <span v-else>-</span>
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            v-if="
                                                approval.document.amount !==
                                                null
                                            "
                                            class="font-medium"
                                        >
                                            {{
                                                formatCurrency(
                                                    approval.document.amount,
                                                    { currency: 'IDR' },
                                                )
                                            }}
                                        </span>
                                        <span v-else>-</span>
                                    </TableCell>
                                    <TableCell>
                                        <div
                                            v-if="approval.document.submitter"
                                            class="flex flex-col"
                                        >
                                            <span class="text-sm font-medium">{{
                                                approval.document.submitter.name
                                            }}</span>
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    approval.document.submitter
                                                        .email
                                                }}</span
                                            >
                                        </div>
                                        <span v-else>-</span>
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            v-if="
                                                approval.document.submitted_at
                                            "
                                            class="text-sm"
                                        >
                                            {{
                                                formatDateTime(
                                                    approval.document
                                                        .submitted_at,
                                                )
                                            }}
                                        </span>
                                        <span v-else>-</span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="text-sm text-orange-600">
                                            {{
                                                Math.floor(
                                                    (Date.now() -
                                                        new Date(
                                                            approval.created_at,
                                                        ).getTime()) /
                                                        (1000 * 60 * 60),
                                                )
                                            }}h ago
                                        </span>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            size="sm"
                                            @click.stop="
                                                handleRowClick(approval)
                                            "
                                        >
                                            Review
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="!loading && approvals.length > 0"
                        class="mt-4 flex items-center justify-between"
                    >
                        <div class="text-sm text-muted-foreground">
                            Showing
                            {{ (currentPage - 1) * perPage + 1 }} to
                            {{
                                Math.min(
                                    currentPage * perPage,
                                    paginationMeta?.total || 0,
                                )
                            }}
                            of {{ paginationMeta?.total || 0 }} results
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="currentPage === 1"
                                @click="goToPage(1)"
                            >
                                <ChevronsLeft class="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="currentPage === 1"
                                @click="goToPage(currentPage - 1)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </Button>

                            <Button
                                v-for="page in displayedPages"
                                :key="page"
                                variant="outline"
                                size="sm"
                                :class="{
                                    'bg-primary text-primary-foreground':
                                        page === currentPage,
                                }"
                                :disabled="page === '...'"
                                @click="
                                    typeof page === 'number' && goToPage(page)
                                "
                            >
                                {{ page }}
                            </Button>

                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="currentPage === totalPages"
                                @click="goToPage(currentPage + 1)"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="currentPage === totalPages"
                                @click="goToPage(totalPages)"
                            >
                                <ChevronsRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
