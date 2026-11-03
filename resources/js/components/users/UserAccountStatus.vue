<script setup lang="ts">
defineProps<{ active: boolean }>();
</script>

<template>
    <span
        class="inline-flex items-center justify-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold whitespace-nowrap"
        :class="
            active
                ? 'bg-success/10 text-success'
                : 'bg-muted text-muted-foreground'
        "
    >
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />{{
            active ? 'Aktif' : 'Nonaktif'
        }}
    </span>
</template>
