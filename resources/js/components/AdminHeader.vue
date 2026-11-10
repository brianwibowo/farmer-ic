<script setup lang="ts">
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, Moon, Sun } from '@lucide/vue';
import { computed, type Component } from 'vue';
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';

withDefaults(
    defineProps<{
        title: string;
        href: string;
        icon: Component;
        showTenant?: boolean;
    }>(),
    { showTenant: true },
);

const page = usePage();
const isSectionIndex = computed(() =>
    [
        'Dashboard',
        'farms/Index',
        'users/Index',
        'sops/Index',
        'logbook/Index',
        'planting-transactions/Index',
        'harvest-transactions/Index',
        'notifications/Index',
    ].includes(page.component),
);
const { isMobile, openMobile, setOpenMobile } = useSidebar();
const { resolvedAppearance, updateAppearance } = useAppearance();
</script>

<template>
    <header class="sticky top-0 z-30 shrink-0 border-b border-border bg-card">
        <div class="admin-navbar-content">
            <div class="flex min-w-0 items-center gap-3">
                <Button
                    v-if="isMobile"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="shrink-0"
                    aria-label="Buka navigasi"
                    :aria-expanded="openMobile"
                    aria-controls="admin-sidebar-navigation"
                    @click="setOpenMobile(true)"
                >
                    <Menu aria-hidden="true" />
                </Button>
                <component
                    :is="icon"
                    class="hidden size-5 shrink-0 text-primary sm:block"
                    aria-hidden="true"
                />
                <component :is="isSectionIndex ? 'h1' : 'div'" class="min-w-0">
                    <Link
                        :href="href"
                        class="admin-navbar-title min-w-0 rounded-sm break-words focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                        >{{ title }}</Link
                    >
                </component>
            </div>
            <div class="flex min-w-0 shrink-0 items-center gap-2">
                <span
                    v-if="showTenant"
                    class="admin-header-tenant max-w-48 truncate rounded-full bg-muted px-3 py-1 text-xs text-muted-foreground"
                    :title="page.props.currentTeam?.name ?? 'Tim belum dipilih'"
                    >{{
                        page.props.currentTeam?.name ?? 'Tim belum dipilih'
                    }}</span
                >
                <div class="flex min-w-0 items-center gap-2">
                    <NotificationBell />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-11 shrink-0 rounded-full! bg-muted/40 text-primary hover:bg-muted hover:text-primary active:bg-muted active:text-primary dark:hover:bg-muted"
                        :aria-label="
                            resolvedAppearance === 'dark'
                                ? 'Aktifkan mode terang'
                                : 'Aktifkan mode gelap'
                        "
                        :title="
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
                    >
                        <Sun
                            v-if="resolvedAppearance === 'dark'"
                            class="size-5"
                            aria-hidden="true"
                        />
                        <Moon v-else class="size-5" aria-hidden="true" />
                    </Button>
                </div>
            </div>
        </div>
    </header>
</template>
