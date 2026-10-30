<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
const { setOpenMobile } = useSidebar();
const isActive = (item: NavItem): boolean =>
    item.isActive ??
    (item.exact ? isCurrentUrl(item.href) : isCurrentOrParentUrl(item.href));
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <nav aria-label="Navigasi utama">
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isActive(item)"
                        :tooltip="item.title"
                    >
                        <Link
                            :href="item.href"
                            :aria-current="
                                isActive(item)
                                    ? isCurrentUrl(item.href)
                                        ? 'page'
                                        : 'location'
                                    : undefined
                            "
                            @success="setOpenMobile(false)"
                        >
                            <component :is="item.icon" aria-hidden="true" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </nav>
    </SidebarGroup>
</template>
