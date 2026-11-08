<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';

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
    <nav
        v-if="paginator.total > 0"
        aria-label="Pagination"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4"
    >
        <p class="text-xs text-muted-foreground">
            {{ paginator.total }} hasil · Halaman {{ paginator.current_page }} /
            {{ paginator.last_page }}
        </p>
        <div class="flex gap-2">
            <Link
                v-if="paginator.prev_page_url"
                :href="paginator.prev_page_url"
                preserve-scroll
                class="inline-flex min-h-11 items-center gap-1 rounded-xl border border-border px-3 text-sm hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                aria-label="Halaman sebelumnya"
                ><ChevronLeft class="size-4" />Sebelumnya</Link
            >
            <Link
                v-if="paginator.next_page_url"
                :href="paginator.next_page_url"
                preserve-scroll
                class="inline-flex min-h-11 items-center gap-1 rounded-xl border border-border px-3 text-sm hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                aria-label="Halaman berikutnya"
                >Berikutnya<ChevronRight class="size-4"
            /></Link>
        </div>
    </nav>
</template>
