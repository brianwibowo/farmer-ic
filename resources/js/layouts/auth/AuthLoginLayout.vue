<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const year = new Date().getFullYear();
</script>

<template>
    <div
        class="grid min-h-dvh bg-background text-foreground lg:grid-cols-2 lg:gap-4 lg:p-4"
    >
        <div
            class="flex min-w-0 flex-col bg-card px-6 py-8 sm:px-10 lg:rounded-xl lg:px-12 lg:py-10 xl:px-20 xl:py-12"
        >
            <header class="mx-auto w-full max-w-sm">
                <Link
                    :href="home()"
                    aria-label="Farm IC home"
                    class="inline-flex items-center gap-2.5 rounded-xl text-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                >
                    <img
                        src="/images/logo.svg"
                        alt=""
                        width="42"
                        height="42"
                        class="size-[42px]"
                    />
                    <span class="text-2xl font-semibold tracking-tight"
                        >Farm <span class="text-primary">IC</span></span
                    >
                </Link>
            </header>

            <main class="flex flex-1 items-center py-12 sm:py-16 lg:py-12">
                <div class="mx-auto w-full max-w-sm">
                    <div class="mb-8 space-y-2">
                        <h1
                            class="text-[28px] leading-9 font-semibold tracking-tight sm:text-[32px] sm:leading-10"
                        >
                            {{ title }}
                        </h1>
                        <p class="text-sm leading-5 text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </main>

            <footer class="mx-auto w-full max-w-sm">
                <p class="text-xs leading-5 text-muted-foreground">
                    &copy; {{ year }} Farm IC. All rights reserved.
                </p>
            </footer>
        </div>

        <aside
            aria-label="Farm IC"
            class="relative hidden min-h-[calc(100dvh-2rem)] overflow-hidden rounded-xl bg-surface lg:block"
        >
            <img
                src="/images/login-page.jpg"
                alt=""
                fetchpriority="high"
                class="absolute inset-0 h-full w-full object-cover"
            />
            <div
                class="relative z-10 max-w-lg px-10 pt-12 text-brand-secondary xl:px-14 xl:pt-16"
            >
                <p class="text-xs font-semibold tracking-[0.16em] uppercase">
                    Farm management, made simple
                </p>
                <h2
                    class="mt-5 text-[32px] leading-10 font-semibold tracking-tight xl:text-[40px] xl:leading-[48px]"
                >
                    Manage your farm,<br />
                    one day at a time.
                </h2>
                <p class="mt-4 max-w-xs text-sm leading-6">
                    Keep your land, daily activities, and harvest records in one
                    place.
                </p>
            </div>
        </aside>
    </div>
</template>
