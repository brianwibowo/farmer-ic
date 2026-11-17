<script setup lang="ts">
import type { Component } from "vue";

defineProps<{
    label: string;
    value: string | number;
    icon: Component;
}>();
</script>

<template>
    <article class="admin-panel flex min-w-0 flex-col gap-3 rounded-[20px] bg-card p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <h2 class="min-w-0 text-sm leading-5 font-semibold break-words">
                {{ label }}
            </h2>
            <component :is="icon" class="size-5 shrink-0 text-primary" aria-hidden="true" />
        </div>
        <p
            class="admin-metric-value text-3xl leading-10 font-bold tracking-tight break-words text-foreground tabular-nums"
        >
            {{ value }}
        </p>
    </article>
</template>
