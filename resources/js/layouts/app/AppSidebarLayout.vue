<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { LayoutGrid } from '@lucide/vue';
import { computed } from 'vue';
import AdminHeader from '@/components/AdminHeader.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
const page = usePage();
const isNotifications = computed(
    () => page.url.split('?')[0] === '/notifications',
);
const isAdmin = computed(
    () =>
        page.url.startsWith('/admin/') || page.props.navigation?.admin === true || page.props.navigation?.owner === true,
);
const adminSection = computed(() => {
    if (isNotifications.value) {
        return { title: 'Notifikasi', href: '/notifications' };
    }

    if (page.url.split('?')[0].startsWith('/settings')) {
        return { title: 'Pengaturan', href: '/settings/profile' };
    }

    return { title: 'Dashboard', href: page.props.navigation?.owner ? '/' : '/admin/dashboard' };
});
</script>

<template>
    <div
        :class="
            isAdmin
                ? 'admin-dashboard-theme min-h-dvh bg-background font-sans text-sm text-foreground'
                : undefined
        "
    >
        <AppShell variant="sidebar" :admin="isAdmin">
            <AppSidebar :admin-theme="isAdmin" />
            <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
                <AdminHeader
                    v-if="isAdmin"
                    :title="adminSection.title"
                    :href="adminSection.href"
                    :icon="LayoutGrid"
                    :show-tenant="
                        !isNotifications || page.props.canFilterTenant === true
                    "
                />
                <AppSidebarHeader v-if="!isAdmin" :breadcrumbs="breadcrumbs" />
                <slot />
            </AppContent>
            <Toaster :class="isAdmin ? 'admin-dashboard-theme' : undefined" />
        </AppShell>
    </div>
</template>
