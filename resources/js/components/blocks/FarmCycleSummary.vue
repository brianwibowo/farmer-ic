<script setup lang="ts">
import { computed } from 'vue';
import { ChevronRight, Recycle } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { useFarmCycles } from '@/composables/useFarmCycles';
const props = withDefaults(defineProps<{ limit?: 2 | 3 }>(), { limit: 3 });
const { cycles, detailUrl } = useFarmCycles();
const recentCycles = computed(() =>
    [...cycles.value]
        .sort((first, second) => second.cycle_number - first.cycle_number)
        .slice(0, props.limit),
);
const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 });
</script>

<template>
    <section
        v-if="recentCycles.length"
        class="farm-cycle-summary relative isolate overflow-hidden rounded-[20px] bg-card p-5"
        aria-label="Ringkasan siklus lahan"
    >
        <Recycle
            class="farm-cycle-summary-pattern"
            :stroke-width="1.25"
            aria-hidden="true"
            focusable="false"
        />
        <div class="mb-5 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold">Siklus lahan</h2>
            <Link
                v-if="detailUrl"
                :href="detailUrl"
                class="inline-flex min-h-9 items-center gap-1 rounded-md text-xs font-medium text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                aria-label="Lihat detail seluruh siklus lahan"
                >Detail cycle<ChevronRight class="size-3.5" aria-hidden="true"
            /></Link>
        </div>
        <ul
            class="farm-cycle-summary-list grid gap-y-6"
            :style="{
                '--cycle-columns': Math.min(recentCycles.length, 2),
                '--cycle-wide-columns': Math.min(recentCycles.length, 3),
            }"
        >
            <li
                v-for="cycle in recentCycles"
                :key="cycle.id"
                class="farm-cycle-summary-item min-w-0 px-2 py-2 text-center"
            >
                <p class="inline-flex items-start justify-center gap-1">
                    <span
                        class="farm-cycle-summary-value font-bold tracking-tight text-foreground tabular-nums"
                        >{{ number.format(Number(cycle.harvested_ton)) }}</span
                    >
                    <span class="mt-1 text-sm font-semibold text-primary"
                        >ton</span
                    >
                </p>
                <p class="mt-3 text-xs leading-5 text-muted-foreground">
                    <span class="whitespace-nowrap"
                        >Siklus {{ cycle.cycle_number }}</span
                    >
                </p>
            </li>
        </ul>
    </section>
</template>

<style scoped>
.farm-cycle-summary,
.farm-cycle-summary-item {
    container-type: inline-size;
}
.farm-cycle-summary-pattern {
    position: absolute;
    z-index: -1;
    pointer-events: none;
    width: 6.5rem;
    height: 6.5rem;
    inset-inline-end: 0;
    bottom: -0.25rem;
    color: color-mix(in srgb, var(--primary) 10%, transparent);
    transform: rotate(-12deg);
}
.farm-cycle-summary-list {
    grid-template-columns: repeat(var(--cycle-columns), minmax(0, 1fr));
}
.farm-cycle-summary-item {
    border-inline-start: 1px solid var(--border);
}
.farm-cycle-summary-item:nth-child(2n + 1) {
    border-inline-start: 0;
}
.farm-cycle-summary-value {
    font-size: clamp(1.375rem, 20cqi, 2.5rem);
    line-height: 1.1;
}
@container (min-width: 40rem) {
    .farm-cycle-summary-list {
        grid-template-columns: repeat(
            var(--cycle-wide-columns),
            minmax(0, 1fr)
        );
    }
    .farm-cycle-summary-item:nth-child(n) {
        border-inline-start: 1px solid var(--border);
    }
    .farm-cycle-summary-item:nth-child(3n + 1) {
        border-inline-start: 0;
    }
}
</style>
