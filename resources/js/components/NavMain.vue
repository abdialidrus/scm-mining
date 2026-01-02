<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    title: string;
    items: NavItem[];
}>();

const page = usePage();

function getBadgeValue(badge: NavItem['badge']): number | undefined {
    if (badge === undefined) return undefined;
    if (typeof badge === 'number') return badge;
    return badge.value;
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ title }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="urlIsActive(item.href, page.url)"
                    :tooltip="item.title"
                >
                    <Link
                        :href="item.href"
                        class="flex w-full items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <component :is="item.icon" class="size-4" />
                            <span>{{ item.title }}</span>
                        </div>
                        <span
                            v-if="
                                getBadgeValue(item.badge) !== undefined &&
                                getBadgeValue(item.badge)! > 0
                            "
                            class="ml-auto flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-red-500 px-1.5 text-xs font-medium text-white"
                        >
                            {{ getBadgeValue(item.badge) }}
                        </span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
