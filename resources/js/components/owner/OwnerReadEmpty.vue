<script setup lang="ts">
import { SearchX } from '@lucide/vue';

defineProps<{ title: string; description: string }>();
</script>

<template>
    <div
        class="rounded-xl border border-dashed border-border bg-card px-5 py-9 text-center"
    >
        <SearchX
            class="mx-auto mb-3 size-8 text-muted-foreground"
            aria-hidden="true"
        />
        <h2 class="text-base font-semibold text-foreground">{{ title }}</h2>
        <p class="mt-2 text-sm leading-6 text-muted-foreground">
            {{ description }}
        </p>
        <slot />
    </div>
</template>
