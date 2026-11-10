<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useAppName } from '@/composables/useAppName';

const appName = useAppName();
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-accent text-sidebar-accent-foreground"
    >
        <AppLogoIcon class="size-8" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            appName
        }}</span>
    </div>
</template>
