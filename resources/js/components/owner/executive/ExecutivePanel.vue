<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <section
        class="min-w-0 rounded-xl border border-border bg-card p-4 sm:p-5 lg:p-6"
    >
        <header class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-foreground lg:text-lg">
                    {{ title }}
                </h2>
                <p
                    v-if="description"
                    class="mt-1 max-w-prose text-sm leading-6 text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>
            <slot name="actions" />
        </header>
        <slot />
    </section>
</template>
