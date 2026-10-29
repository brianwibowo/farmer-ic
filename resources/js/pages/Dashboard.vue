<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { dashboard } from '@/routes';
import type { DashboardInvitation } from '@/types';

defineProps<{
    pendingInvitations?: DashboardInvitation[];
}>();
const page = usePage();
const isAdmin = computed(() => page.url.startsWith('/admin/'));

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
        :admin-theme="isAdmin"
    />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        :class="
            isAdmin
                ? 'mx-auto w-full max-w-screen-2xl md:p-6 lg:p-8'
                : undefined
        "
    >
        <header v-if="isAdmin" class="mb-2">
            <p
                class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase"
            >
                Administrasi
            </p>
            <h1 class="admin-page-title tracking-tight">Dashboard</h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                Ringkasan untuk pengelolaan aplikasi.
            </p>
        </header>
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                class="relative aspect-video overflow-hidden rounded-xl border"
                :class="
                    isAdmin
                        ? 'border-border bg-card'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
            >
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border"
                :class="
                    isAdmin
                        ? 'border-border bg-card'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
            >
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border"
                :class="
                    isAdmin
                        ? 'border-border bg-card'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
            >
                <PlaceholderPattern />
            </div>
        </div>
        <div
            class="relative min-h-[100vh] flex-1 rounded-xl border md:min-h-min"
            :class="
                isAdmin
                    ? 'border-border bg-card'
                    : 'border-sidebar-border/70 dark:border-sidebar-border'
            "
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
