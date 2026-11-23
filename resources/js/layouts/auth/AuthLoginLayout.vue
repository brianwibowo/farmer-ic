<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { home } from '@/routes';
import { useAppName } from '@/composables/useAppName';

const appName = useAppName();
const copyrightName = computed(() => {
    const name = appName.value.trim();
    const prefix = /^pt\.?(?:\s|$)/i.test(name) ? '' : 'PT ';
    const suffix = /(?:^|\s)indonesia$/i.test(name) ? '' : ' Indonesia';

    return `${prefix}${name}${suffix}`;
});

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
                    :aria-label="`Beranda ${appName}`"
                    class="inline-flex items-center gap-2.5 rounded-xl text-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                >
                    <BrandLogo
                        mark-class="size-[42px]"
                        wordmark-class="text-2xl"
                    />
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
                    &copy; {{ year }} {{ copyrightName }}. Hak Cipta Dilindungi.
                </p>
            </footer>
        </div>

        <aside
            :aria-label="appName"
            class="relative hidden min-h-[calc(100dvh-2rem)] overflow-hidden rounded-xl bg-surface lg:block"
        >
            <img
                src="/images/login-page.jpg"
                alt=""
                fetchpriority="high"
                class="absolute inset-0 h-full w-full object-cover"
            />
            <div
                class="relative z-10 px-8 pt-12 text-brand-secondary xl:px-10 xl:pt-16"
            >
                <h2
                    class="mt-5 text-[32px] leading-10 font-semibold tracking-tight xl:text-[40px] xl:leading-[48px]"
                >
                    Pengelolaan lahan Anda,<br />
                    menjadi lebih mudah.
                </h2>
                <p class="mt-4 max-w-xs text-sm leading-6">
                    Kelola lahan, aktivitas harian, dan catatan panen Anda dalam
                    satu tempat.
                </p>
            </div>
        </aside>
    </div>
</template>
