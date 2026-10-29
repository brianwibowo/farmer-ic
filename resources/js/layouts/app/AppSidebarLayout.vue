<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import DashboardHeader from '@/components/farmer/DashboardHeader.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { useAppearance } from '@/composables/useAppearance';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
const page = usePage();
const isAdmin = computed(() => page.url.startsWith('/admin/'));
const { resolvedAppearance, updateAppearance } = useAppearance();
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
                <header
                    v-if="isAdmin"
                    class="flex min-h-16 flex-wrap items-center justify-between gap-3 border-b border-border bg-card px-4 py-3 lg:px-8"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <SidebarTrigger aria-label="Buka atau tutup navigasi" />
                        <span class="h-5 w-px bg-border" aria-hidden="true" />
                        <LayoutGrid
                            class="size-5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <Link
                            href="/admin/dashboard"
                            class="admin-navbar-title focus-visible:outline-2 focus-visible:outline-ring"
                            >Dashboard</Link
                        >
                    </div>
                    <div class="flex min-w-0 items-center gap-2">
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="
                                resolvedAppearance === 'dark'
                                    ? 'Aktifkan mode terang'
                                    : 'Aktifkan mode gelap'
                            "
                            @click="
                                updateAppearance(
                                    resolvedAppearance === 'dark'
                                        ? 'light'
                                        : 'dark',
                                )
                            "
                            ><Sun v-if="resolvedAppearance === 'dark'" /><Moon
                                v-else
                        /></Button>
                        <span
                            class="max-w-full truncate rounded-full bg-muted px-3 py-1 text-xs text-muted-foreground"
                            >{{
                                page.props.currentTeam?.name ??
                                'Tim belum dipilih'
                            }}</span
                        >
                    </div>
                </header>
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
