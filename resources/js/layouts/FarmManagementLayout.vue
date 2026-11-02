<script setup lang="ts">
import { LandPlot } from '@lucide/vue';
import AdminHeader from '@/components/AdminHeader.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import { Toaster } from '@/components/ui/sonner';
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
                <div class="mx-auto w-full max-w-screen-2xl p-4 md:p-6 lg:p-8">
                    <slot />
                </div>
            </AppContent>
            <Toaster class="admin-dashboard-theme" />
        </AppShell>
    </div>
</template>
