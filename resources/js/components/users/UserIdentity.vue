<script setup lang="ts">
import { getInitials } from '@/composables/useInitials';

defineProps<{ name: string; email?: string }>();
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <span
            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-bold text-accent-foreground"
            aria-hidden="true"
            >{{ getInitials(name) }}</span
        >
        <div class="min-w-0">
            <p class="font-semibold break-words">{{ name }}</p>
            <p
                v-if="email"
                class="mt-1 text-xs break-all text-muted-foreground"
            >
                {{ email }}
            </p>
        </div>
    </div>
</template>
