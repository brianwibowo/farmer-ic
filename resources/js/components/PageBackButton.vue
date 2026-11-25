<script setup lang="ts">
import { Link, type InertiaLinkProps } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';

withDefaults(
    defineProps<{ href?: InertiaLinkProps['href']; label?: string }>(),
    {
        label: 'Kembali',
    },
);
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        :type="href ? undefined : 'button'"
        :aria-label="label"
        :title="label"
        class="group inline-flex size-11 shrink-0 items-center justify-center rounded-full text-primary-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50"
    >
        <span
            class="inline-flex size-9 items-center justify-center rounded-full bg-primary transition-colors group-hover:bg-primary/90 motion-reduce:transition-none"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
        </span>
    </component>
</template>
