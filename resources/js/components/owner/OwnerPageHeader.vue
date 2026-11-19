<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';

withDefaults(
    defineProps<{
        title: string;
        hideTitle?: boolean;
        description?: string;
        backHref?: string;
        backLabel?: string;
    }>(),
    { backLabel: 'Kembali' },
);
</script>

<template>
    <header class="space-y-2">
        <div
            v-if="backHref || $slots.actions"
            class="flex min-w-0 items-center justify-between gap-3"
        >
            <Link
                v-if="backHref"
                :href="backHref"
                :aria-label="backLabel"
                :title="backLabel"
                class="inline-flex min-h-11 min-w-0 items-center gap-2 rounded-xl text-sm text-muted-foreground hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
            >
                <ArrowLeft class="size-4 shrink-0" aria-hidden="true" />
                <span class="truncate">{{ backLabel }}</span>
            </Link>
            <div
                v-if="$slots.actions"
                class="ml-auto flex shrink-0 items-center gap-2"
            >
                <slot name="actions" />
            </div>
        </div>
        <slot v-if="!hideTitle" name="heading">
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
