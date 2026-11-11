<script setup lang="ts">
import type { Component } from 'vue';
import AdminHeader from '@/components/AdminHeader.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import { Toaster } from '@/components/ui/sonner';

defineProps<{ title: string; href: string; icon: Component }>();
</script>

<template>
    <div
        class="admin-dashboard-theme min-h-dvh bg-background font-sans text-sm text-foreground"
    >
        <AppShell variant="sidebar" admin>
            <AppSidebar admin-theme />
            <AppContent variant="sidebar" class="min-w-0 bg-background">
                <AdminHeader :title="title" :href="href" :icon="icon" />
                <div class="admin-page-content"><slot /></div>
            </AppContent>
            <Toaster class="admin-dashboard-theme" />
        </AppShell>
    </div>
</template>
