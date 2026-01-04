<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { getApprovalStatistics } from '@/services/approvalApi';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Bell,
    CheckCircle,
    FileText,
    GitBranch,
    LayoutGrid,
    Package,
    PackageCheck,
    PackageMinus,
    PackageOpen,
    Ruler,
    ShoppingCart,
    TrendingUp,
    Users,
    Warehouse,
} from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const user = page.props.auth?.user as any;
const isSuperAdmin = user?.roles?.some(
    (role: any) => role.name === 'super_admin',
);
const isProcurement = user?.roles?.some(
    (role: any) => role.name === 'procurement',
);
const isFinance = user?.roles?.some((role: any) => role.name === 'finance');
const isWarehouse = user?.roles?.some((role: any) => role.name === 'warehouse');
const isRequestor = user?.roles?.some((role: any) => role.name === 'requester');
const isDeptHead = user?.roles?.some((role: any) => role.name === 'dept_head');
const isGm = user?.roles?.some((role: any) => role.name === 'gm');
const isDirector = user?.roles?.some((role: any) => role.name === 'director');

const canShowMasterData = isSuperAdmin || isProcurement;

const canShowPurchaseRequests =
    isSuperAdmin ||
    isDeptHead ||
    isProcurement ||
    isRequestor ||
    isGm ||
    isDirector;

const canShowPurchaseOrders =
    isSuperAdmin || isProcurement || isFinance || isGm || isDirector;

const canShowGoodsReceipts =
    isSuperAdmin || isWarehouse || isFinance || isGm || isDirector;

const canShowPutAways = isSuperAdmin || isWarehouse || isGm || isDirector;

const canShowPickingOrders = isSuperAdmin || isWarehouse || isGm || isDirector;

const canShowInventoryReports =
    isSuperAdmin || isProcurement || isGm || isDirector;

const canShowWarehouses = isSuperAdmin || isWarehouse || isProcurement;

// Check if user has any approver role
const canShowMyApprovals =
    isSuperAdmin || isDeptHead || isFinance || isGm || isDirector;

const pendingApprovalsCount = ref(0);
const unreadNotificationsCount = ref(0);

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

if (canShowMyApprovals) {
    mainNavItems.push({
        title: 'My Approvals',
        href: '/my-approvals',
        icon: CheckCircle,
        badge: pendingApprovalsCount,
    });
}

// Notifications menu - available for all users
mainNavItems.push({
    title: 'Notifications',
    href: '/notifications',
    icon: Bell,
    badge: unreadNotificationsCount,
});

// Reports menu - available for management, procurement, and finance roles
if (isSuperAdmin || isGm || isDirector || isProcurement || isFinance) {
    mainNavItems.push({
        title: 'Reports',
        href: '/reports',
        icon: BarChart3,
    });
}

// Load pending approvals count
onMounted(async () => {
    if (canShowMyApprovals) {
        try {
            const stats = await getApprovalStatistics();
            pendingApprovalsCount.value = stats.data.pending_count;
        } catch (error) {
            console.error('Failed to load approval statistics:', error);
        }
    }

    // Load unread notifications count
    try {
        const response = await fetch('/api/notifications/unread-count', {
            credentials: 'include',
        });
        if (response.ok) {
            const data = await response.json();
            unreadNotificationsCount.value = data.count || 0;
        }
    } catch (error) {
        console.error('Failed to load unread notifications count:', error);
    }
});

function getProcurementNavItems(): NavItem[] {
    const items: NavItem[] = [];

    if (canShowPurchaseRequests) {
        items.push({
            title: 'Purchase Requests',
            href: '/purchase-requests',
            icon: FileText,
        });
    }

    if (canShowPurchaseOrders) {
        items.push({
            title: 'Purchase Orders',
            href: '/purchase-orders',
            icon: ShoppingCart,
        });
    }

    if (canShowGoodsReceipts) {
        items.push({
            title: 'Goods Receipts',
            href: '/goods-receipts',
            icon: PackageCheck,
        });
    }

    if (canShowPutAways) {
        items.push({
            title: 'Put Away',
            href: '/put-aways',
            icon: PackageOpen,
        });
    }

    if (canShowPickingOrders) {
        items.push({
            title: 'Picking Orders',
            href: '/picking-orders',
            icon: PackageMinus,
        });
    }

    return items;
}

const inventoryNavItems: NavItem[] = [
    {
        title: 'Stock Reports',
        href: '/stock-reports',
        icon: TrendingUp,
    },
];

function getMasterDataNavItems(): NavItem[] {
    const items: NavItem[] = [];

    if (canShowWarehouses) {
        items.push({
            title: 'Warehouses',
            href: '/master-data/warehouses',
            icon: Warehouse,
        });
    }

    if (canShowMasterData) {
        items.push(
            {
                title: 'Departments',
                href: '/master-data/departments',
                icon: Users,
            },
            {
                title: 'Items',
                href: '/master-data/items',
                icon: Package,
            },
            {
                title: 'Item Categories',
                href: '/master-data/item-categories',
                icon: Package,
            },
            {
                title: 'UOMs',
                href: '/master-data/uoms',
                icon: Ruler,
            },
            {
                title: 'Suppliers',
                href: '/master-data/suppliers',
                icon: Users,
            },
            {
                title: 'Users',
                href: '/master-data/users',
                icon: Users,
            },
        );
    }

    return items;
}

const masterDataNavItems: NavItem[] = [];

const settingsNavItems: NavItem[] = isSuperAdmin
    ? [
          {
              title: 'Approval Workflows',
              href: '/approval-workflows',
              icon: GitBranch,
          },
      ]
    : [];

// Remove unused footerNavItems to avoid lint errors.
// const footerNavItems: NavItem[] = [
//     {
//         title: 'Github Repo',
//         href: 'https://github.com/laravel/vue-starter-kit',
//         icon: Folder,
//     },
//     {
//         title: 'Documentation',
//         href: 'https://laravel.com/docs/starter-kits#vue',
//         icon: BookOpen,
//     },
// ];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :title="'Main'" :items="mainNavItems" />
            <NavMain :title="'Procurement'" :items="getProcurementNavItems()" />
            <NavMain
                v-if="canShowInventoryReports"
                :title="'Inventory'"
                :items="inventoryNavItems"
            />
            <NavMain
                v-if="canShowMasterData || canShowWarehouses"
                :title="'Master Data'"
                :items="getMasterDataNavItems()"
            />
            <NavMain
                v-if="isSuperAdmin"
                :title="'Settings'"
                :items="settingsNavItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
