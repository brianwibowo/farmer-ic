<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LandPlot, Moon, Sun } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';

const page = usePage();
const { resolvedAppearance, updateAppearance } = useAppearance();
</script>

<template>
    <div class="admin-dashboard-theme min-h-dvh bg-background font-sans text-sm text-foreground">
        <AppShell variant="sidebar" admin>
            <AppSidebar admin-theme />
            <AppContent variant="sidebar" class="min-w-0 bg-background">
                <header class="flex min-h-16 flex-wrap items-center justify-between gap-3 border-b border-border bg-card px-4 py-3 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <SidebarTrigger aria-label="Buka atau tutup navigasi" />
                        <span class="h-5 w-px bg-border" aria-hidden="true" />
                        <LandPlot class="size-5 shrink-0 text-primary" aria-hidden="true" />
                        <Link href="/admin/farm-management" class="admin-navbar-title focus-visible:outline-2 focus-visible:outline-ring">Farm Management</Link>
                    </div>
                    <div class="flex min-w-0 items-center gap-2"><Button variant="ghost" size="icon" :aria-label="resolvedAppearance === 'dark' ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'" @click="updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark')"><Sun v-if="resolvedAppearance === 'dark'" /><Moon v-else /></Button><span class="max-w-full truncate rounded-full bg-muted px-3 py-1 text-xs text-muted-foreground">{{ page.props.currentTeam?.name ?? 'Tim belum dipilih' }}</span></div>
                </header>
                <div class="mx-auto w-full max-w-screen-2xl p-4 md:p-6 lg:p-8">
                    <slot />
                </div>
            </AppContent>
            <Toaster class="admin-dashboard-theme" />
        </AppShell>
    </div>
</template>
