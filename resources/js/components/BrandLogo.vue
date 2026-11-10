<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useAppName } from '@/composables/useAppName';

const appName = useAppName();

withDefaults(
    defineProps<{
        markClass?: HTMLAttributes['class'];
        wordmarkClass?: HTMLAttributes['class'];
    }>(),
    {
        markClass: 'size-9',
        wordmarkClass: 'text-base',
    },
);
</script>

<template>
    <span class="inline-flex min-w-0 items-center gap-2">
        <AppLogoIcon :class="markClass" />
        <span
            class="truncate leading-tight font-semibold tracking-tight"
            :class="wordmarkClass"
            >{{ appName }}</span
        >
    </span>
</template>
