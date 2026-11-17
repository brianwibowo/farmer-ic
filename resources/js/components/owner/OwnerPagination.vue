<script setup lang="ts">
import InertiaPagination from '@/components/InertiaPagination.vue';

defineProps<{
    paginator: {
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        from?: number | null;
        to?: number | null;
    };
}>();
</script>

<template>
    <footer
        v-if="paginator.total > 0"
        aria-label="Pagination"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4"
    >
        <p class="text-xs text-muted-foreground">{{ paginator.total }} hasil</p>
        <InertiaPagination
            :paginator="paginator"
            label="Pagination"
            class="ml-auto justify-end"
        />
    </footer>
</template>
