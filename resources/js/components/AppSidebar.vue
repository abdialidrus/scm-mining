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
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    FileText,
    Folder,
    LayoutGrid,
    Package,
    Ruler,
    Users,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const procurementNavItems: NavItem[] = [
    {
        title: 'Purchase Requests',
        href: '/purchase-requests',
        icon: FileText,
    },
];

const masterDataNavItems: NavItem[] = [
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
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
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
            <NavMain :title="'Procurement'" :items="procurementNavItems" />
            <NavMain :title="'Master Data'" :items="masterDataNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
