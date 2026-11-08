<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';

defineProps<{ loading: boolean; error: string; feedback: string }>();
</script>

<template>
    <p
        v-if="loading || error || feedback"
        :role="error ? 'alert' : 'status'"
        class="flex items-start gap-2 text-xs leading-5"
        :class="error ? 'text-error' : 'text-muted-foreground'"
    >
        <LoaderCircle
            v-if="loading"
            class="mt-0.5 size-4 shrink-0 animate-spin motion-reduce:animate-none"
            aria-hidden="true"
        />
        {{ loading ? 'Memuat data…' : error || feedback }}
    </p>
</template>
