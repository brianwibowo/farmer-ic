<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Moon, Sun } from '@lucide/vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';

defineProps<{ title: string; href: string; icon: Component }>();

const page = usePage();
const { resolvedAppearance, updateAppearance } = useAppearance();
</script>

<template>
    <header
        class="flex min-h-16 items-center justify-between gap-2 border-b border-border bg-card px-4 py-3 lg:px-8"
    >
        <div class="flex min-w-0 items-center gap-3">
            <SidebarTrigger
                class="shrink-0"
                aria-label="Buka atau tutup navigasi"
            />
            <span class="h-5 w-px shrink-0 bg-border" aria-hidden="true" />
            <component
                :is="icon"
                class="hidden size-5 shrink-0 text-primary sm:block"
                aria-hidden="true"
            />
            <Link
                :href="href"
                class="admin-navbar-title min-w-0 rounded-sm break-words focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                >{{ title }}</Link
            >
        </div>
        <div class="flex min-w-0 shrink-0 items-center gap-2">
            <span
                class="admin-header-tenant max-w-48 truncate rounded-full bg-muted px-3 py-1 text-xs text-muted-foreground"
                :title="page.props.currentTeam?.name ?? 'Tim belum dipilih'"
                >{{ page.props.currentTeam?.name ?? 'Tim belum dipilih' }}</span
            >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="shrink-0"
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
                        resolvedAppearance === 'dark' ? 'light' : 'dark',
                    )
                "
            >
                <Sun v-if="resolvedAppearance === 'dark'" aria-hidden="true" />
                <Moon v-else aria-hidden="true" />
            </Button>
        </div>
    </header>
</template>
