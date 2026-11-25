<script setup lang="ts">
import PageBackButton from '@/components/PageBackButton.vue';
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
            v-if="backHref || !hideTitle || $slots.actions"
            class="flex min-w-0 items-center justify-between gap-2"
        >
            <PageBackButton
                v-if="backHref"
                :href="backHref"
                :label="backLabel"
            />
            <div v-if="!hideTitle" class="min-w-0 flex-1">
                <slot name="heading">
                    <h1 class="text-2xl leading-8 font-semibold break-words">
                        {{ title }}
                    </h1>
                </slot>
            </div>
            <div
                v-if="$slots.actions"
                class="ml-auto flex shrink-0 items-center gap-2"
            >
                <slot name="actions" />
            </div>
        </div>
        <p v-if="description" class="text-sm leading-6 text-muted-foreground">
            {{ description }}
        </p>
        <slot />
    </header>
</template>
