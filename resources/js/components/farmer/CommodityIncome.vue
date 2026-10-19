<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LoaderCircle, Sprout } from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import CommodityCard from '@/components/CommodityCardDashboard.vue';
import {
    closestCarouselSlide,
    useCarouselDrag,
} from '@/composables/useCarouselDrag';
import {
    listCommoditySummaries,
    type CommoditySummary,
} from '@/services/commoditySummaryService';

const items = ref<CommoditySummary[]>([]);
const activeIndex = ref(0);
const track = ref<HTMLUListElement>();
const loading = ref(true);
const error = ref('');
let resizeObserver: ResizeObserver | undefined;
let scrollFrame = 0;
let disposed = false;
const {
    isDragging,
    pointerDown,
    pointerMove,
    pointerUp,
    pointerCancel,
    click,
} = useCarouselDrag(track, (index) => goTo(index));

function goTo(index: number, smooth = true): void {
    const boundedIndex = Math.max(0, Math.min(index, items.value.length - 1));
    const slide = track.value?.children[boundedIndex] as
        | HTMLElement
        | undefined;
    if (!track.value || !slide) return;
    activeIndex.value = boundedIndex;
    track.value.scrollTo({
        left: slide.offsetLeft,
        behavior:
            smooth &&
            !window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ? 'smooth'
                : 'instant',
    });
}

function updateActiveSlide(): void {
    if (scrollFrame) return;
    scrollFrame = window.requestAnimationFrame(() => {
        scrollFrame = 0;
        if (!track.value) return;
        activeIndex.value = closestCarouselSlide(track.value);
    });
}

async function loadSummaries(): Promise<void> {
    loading.value = true;
    error.value = '';
    resizeObserver?.disconnect();
    try {
        const summaries = await listCommoditySummaries();
        if (disposed) return;
        items.value = summaries;
        activeIndex.value = 0;
    } catch {
        if (!disposed)
            error.value =
                'Ringkasan kebun belum dapat dimuat. Silakan coba lagi.';
    } finally {
        if (!disposed) loading.value = false;
    }
    await nextTick();
    if (disposed || !track.value) return;
    resizeObserver = new ResizeObserver(() => goTo(activeIndex.value, false));
    resizeObserver.observe(track.value);
}

onMounted(() => void loadSummaries());
onBeforeUnmount(() => {
    disposed = true;
    resizeObserver?.disconnect();
    window.cancelAnimationFrame(scrollFrame);
});
</script>

<template>
    <section
        class="min-w-0 space-y-3"
        role="region"
        aria-roledescription="carousel"
        aria-labelledby="commodity-summary-heading"
    >
        <div class="flex items-center justify-between gap-3">
            <h2
                id="commodity-summary-heading"
                class="text-base font-black text-card-foreground"
            >
                Ringkasan Kebun
            </h2>
            <Link
                href="/laporan"
                class="inline-flex min-h-8 shrink-0 items-center justify-center rounded-xl bg-primary px-3 text-xs font-semibold text-primary-foreground transition-colors hover:bg-secondary hover:text-secondary-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                Laporan
            </Link>
        </div>

        <div
            v-if="loading"
            class="flex min-h-[174px] items-center justify-center gap-2 rounded-2xl border border-border bg-muted text-sm text-muted-foreground"
            role="status"
        >
            <LoaderCircle
                :size="18"
                class="animate-spin"
                aria-hidden="true"
            />Memuat ringkasan kebun…
        </div>
        <div
            v-else-if="error"
            class="space-y-3 rounded-2xl border border-border bg-card p-5 text-center"
            role="status"
        >
            <p class="text-sm text-error">{{ error }}</p>
            <button
                type="button"
                class="rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground focus-visible:outline-2 focus-visible:outline-ring"
                @click="void loadSummaries()"
            >
                Coba lagi
            </button>
        </div>
        <div
            v-else-if="items.length === 0"
            class="rounded-2xl bg-muted p-6 text-center"
            role="status"
        >
            <Sprout
                :size="28"
                class="mx-auto text-primary"
                aria-hidden="true"
            />
            <p class="mt-2 text-sm text-muted-foreground">
                Belum ada ringkasan kebun.
            </p>
        </div>
        <template v-else>
            <ul
                id="commodity-summary-slides"
                ref="track"
                class="commodity-carousel relative grid snap-x snap-mandatory auto-cols-[100%] grid-flow-col gap-3 overflow-x-auto overscroll-x-contain rounded-2xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                :class="{ 'is-dragging': isDragging }"
                tabindex="0"
                aria-label="Geser untuk melihat kartu ringkasan kebun"
                @scroll.passive="updateActiveSlide"
                @pointerdown="pointerDown"
                @pointermove="pointerMove"
                @pointerup="pointerUp"
                @pointercancel="pointerCancel"
                @lostpointercapture="pointerCancel"
                @click.capture="click"
                @dragstart.prevent
                @keydown.left.prevent="goTo(activeIndex - 1)"
                @keydown.right.prevent="goTo(activeIndex + 1)"
                @keydown.home.prevent="goTo(0)"
                @keydown.end.prevent="goTo(items.length - 1)"
            >
                <li
                    v-for="(item, index) in items"
                    :key="item.id"
                    class="min-w-0 snap-start snap-always"
                    :aria-label="`${item.name}, kartu ${index + 1} dari ${items.length}`"
                    aria-roledescription="slide"
                >
                    <CommodityCard :item="item" />
                </li>
            </ul>
            <div
                v-if="items.length > 1"
                class="flex items-center justify-center"
                aria-label="Pilih kartu ringkasan"
            >
                <button
                    v-for="(item, index) in items"
                    :key="item.id"
                    type="button"
                    class="flex size-8 items-center justify-center rounded-full focus-visible:outline-2 focus-visible:outline-ring"
                    :aria-label="`Tampilkan ${item.name}`"
                    :aria-current="activeIndex === index ? 'true' : undefined"
                    aria-controls="commodity-summary-slides"
                    @click="goTo(index)"
                >
                    <span
                        :class="[
                            'h-1.5 rounded-full transition-all motion-reduce:transition-none',
                            activeIndex === index
                                ? 'w-5 bg-primary'
                                : 'w-1.5 bg-border',
                        ]"
                    />
                </button>
            </div>
            <p class="sr-only" aria-live="polite" aria-atomic="true">
                Kartu {{ activeIndex + 1 }} dari {{ items.length }}:
                {{ items[activeIndex]?.name }}
            </p>
        </template>
    </section>
</template>

<style scoped>
.commodity-carousel {
    scrollbar-width: none;
    touch-action: auto;
    cursor: grab;
    -webkit-user-select: none;
    user-select: none;
}
.commodity-carousel.is-dragging {
    scroll-snap-type: none;
    scroll-behavior: auto;
    cursor: grabbing;
}
.commodity-carousel::-webkit-scrollbar {
    display: none;
}
</style>
