<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { LandPlot } from '@lucide/vue';
import AdminHeader from '@/components/AdminHeader.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import { Toaster } from '@/components/ui/sonner';
const page = usePage();
</script>

<template>
    <div
        class="admin-dashboard-theme min-h-dvh bg-background font-sans text-sm text-foreground"
    >
        <AppShell variant="sidebar" admin>
            <AppSidebar admin-theme />
            <AppContent variant="sidebar" class="min-w-0 bg-background">
                <AdminHeader
                    title="Farm Management"
                    href="/admin/farm-management"
                    :icon="LandPlot"
                />
                <div
                    class="mx-auto w-full max-w-screen-2xl"
                    :class="
                        ['farms/Index', 'farms/Show'].includes(page.component)
                            ? 'px-4 pt-3 pb-4 md:px-6 md:pt-4 md:pb-6 lg:px-8 lg:pb-8'
                            : 'p-4 md:p-6 lg:p-8'
                    "
                >
                    <slot />
                </div>
            </AppContent>
            <Toaster class="admin-dashboard-theme" />
        </AppShell>
    </div>
</template>
