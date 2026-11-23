<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        currentPage: number;
        totalPages: number;
        disabled?: boolean;
        label?: string;
    }>(),
    { disabled: false, label: 'Pagination' },
);
const emit = defineEmits<{ change: [page: number] }>();

function pageWindow(size: number): number[] {
    const last = Math.max(1, props.totalPages);
    const current = Math.min(last, Math.max(1, props.currentPage));
    const start = Math.max(
        1,
        Math.min(current - Math.floor((size - 1) / 2), last - size + 1),
    );
    return Array.from(
        { length: Math.min(size, last) },
        (_, index) => start + index,
    );
}
const pages = computed(() => pageWindow(4));
const mobilePages = computed(() => pageWindow(3));
const buttonClass =
    'group grid size-8 shrink-0 cursor-pointer place-items-center rounded-full text-xs font-semibold tabular-nums focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50 pointer-coarse:size-11';

function select(page: number): void {
    if (
        props.disabled ||
        page === props.currentPage ||
        page < 1 ||
        page > props.totalPages
    )
        return;
    emit('change', page);
}
</script>

<template>
    <nav class="flex items-center gap-1" :aria-label="label">
        <button
            v-if="currentPage > 1"
            type="button"
            :class="[buttonClass, 'text-muted-foreground hover:text-primary']"
            :disabled="disabled"
            aria-label="Halaman sebelumnya"
            @click="select(currentPage - 1)"
        >
            <ChevronLeft class="size-4" aria-hidden="true" />
        </button>
        <button
            v-for="page in pages"
            :key="page"
            type="button"
            :class="[
                buttonClass,
                mobilePages.includes(page) ? 'grid' : 'hidden sm:grid',
            ]"
            :disabled="disabled"
            :aria-current="page === currentPage ? 'page' : undefined"
            :aria-label="`Halaman ${page}`"
            @click="select(page)"
        >
            <span
                class="grid size-8 place-items-center rounded-full transition-colors motion-reduce:transition-none"
                :class="
                    page === currentPage
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary'
                "
                >{{ page }}</span
            >
        </button>
        <button
            v-if="currentPage < totalPages"
            type="button"
            :class="[buttonClass, 'text-muted-foreground hover:text-primary']"
            :disabled="disabled"
            aria-label="Halaman berikutnya"
            @click="select(currentPage + 1)"
        >
            <ChevronRight class="size-4" aria-hidden="true" />
        </button>
    </nav>
</template>
