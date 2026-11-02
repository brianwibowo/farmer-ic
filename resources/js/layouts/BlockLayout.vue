<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Moon, Sun } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();
</script>

<template>
    <div class="min-h-dvh bg-background text-foreground">
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
                        ><img
                            src="/images/logo.svg"
                            alt=""
                            class="size-9"
                        />Farm IC</Link
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
