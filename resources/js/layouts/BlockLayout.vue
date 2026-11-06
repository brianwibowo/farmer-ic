<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Moon, Sun } from '@lucide/vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { useAppearance } from '@/composables/useAppearance';
import FarmManagementLayout from '@/layouts/FarmManagementLayout.vue';
import '../../css/farm-block-styling.css';

defineOptions({ inheritAttrs: false });

const { resolvedAppearance, updateAppearance } = useAppearance();
const page = usePage();
const farm = computed(
    () => page.props.farm as { id: string; name: string } | undefined,
);
</script>

<template>
    <FarmManagementLayout v-if="page.props.navigation?.farms">
        <div class="space-y-[18px]">
            <Link
                :href="
                    farm
                        ? `/admin/farm-management/${farm.id}`
                        : '/admin/farm-management'
                "
                class="inline-flex min-h-10 items-center rounded-lg text-sm font-medium text-muted-foreground hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
                >{{
                    farm ? `Manajemen Kebun / ${farm.name}` : 'Manajemen Kebun'
                }}</Link
            >
            <div class="space-y-[18px]"><slot /></div>
        </div>
    </FarmManagementLayout>
    <div
        v-else
        class="farm-block-theme min-h-dvh bg-background text-foreground"
    >
        <header class="border-b border-border bg-card">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6"
            >
                <nav
                    aria-label="Navigasi blok"
                    class="flex min-w-0 items-center gap-3"
                >
                    <Link
                        href="/"
                        class="flex shrink-0 items-center gap-2 rounded-xl font-semibold focus-visible:outline-2 focus-visible:outline-ring"
                        ><BrandLogo /></Link
                    >
                    <span aria-hidden="true" class="text-muted-foreground"
                        >/</span
                    >
                    <Link
                        href="/blocks"
                        class="rounded-xl text-sm text-muted-foreground focus-visible:outline-2 focus-visible:outline-ring"
                        >Blok kebun</Link
                    >
                </nav>
                <Button
                    variant="outline"
                    size="icon"
                    class="shrink-0 rounded-xl"
                    :aria-label="
                        resolvedAppearance === 'dark'
                            ? 'Aktifkan mode terang'
                            : 'Aktifkan mode gelap'
                    "
                    @click="
                        updateAppearance(
                            resolvedAppearance === 'dark' ? 'light' : 'dark',
                        )
                    "
                    ><Sun v-if="resolvedAppearance === 'dark'" /><Moon v-else
                /></Button>
            </div>
        </header>
        <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
            <slot />
        </main>
        <Toaster />
    </div>
</template>
