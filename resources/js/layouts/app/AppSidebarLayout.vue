<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { LayoutGrid } from '@lucide/vue';
import { computed } from 'vue';
import AdminHeader from '@/components/AdminHeader.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import DashboardHeader from '@/components/farmer/DashboardHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
const page = usePage();
const isAdmin = computed(() => page.url.startsWith('/admin/'));
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
                    title="Dashboard"
                    href="/admin/dashboard"
                    :icon="LayoutGrid"
                />
                <div v-else class="shrink-0 bg-card px-4 pt-4 pb-2">
                    <DashboardHeader />
                </div>
                <AppSidebarHeader v-if="!isAdmin" :breadcrumbs="breadcrumbs" />
                <slot />
            </AppContent>
            <Toaster :class="isAdmin ? 'admin-dashboard-theme' : undefined" />
        </AppShell>
    </div>
</template>
