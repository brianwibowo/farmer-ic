<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        backHref?: string;
        backLabel?: string;
    }>(),
    { backLabel: 'Kembali' },
);
</script>

<template>
    <header class="space-y-2">
        <Link
            v-if="backHref"
            :href="backHref"
            class="inline-flex min-h-11 items-center gap-2 rounded-xl text-sm text-muted-foreground hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />{{ backLabel }}
        </Link>
        <slot name="heading">
            <h1 class="text-2xl leading-8 font-semibold break-words">
                {{ title }}
            </h1>
        </slot>
        <p v-if="description" class="text-sm leading-6 text-muted-foreground">
            {{ description }}
        </p>
        <slot />
    </header>
</template>
