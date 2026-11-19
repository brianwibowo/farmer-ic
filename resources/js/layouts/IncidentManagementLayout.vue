<script setup lang="ts">
import { ShieldAlert } from '@lucide/vue';
import AdminHeader from '@/components/AdminHeader.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
</script>

<template>
    <div
        class="admin-dashboard-theme min-h-dvh bg-background font-sans text-sm text-foreground"
    >
        <AppShell variant="sidebar" admin>
            <AppSidebar admin-theme />
            <AppContent variant="sidebar" class="min-w-0 bg-background">
                <AdminHeader
                    title="Tiket Insiden"
                    href="/admin/incidents"
                    :icon="ShieldAlert"
                />
                <div class="admin-page-content">
                    <slot />
                </div>
            </AppContent>
        </AppShell>
    </div>
</template>
