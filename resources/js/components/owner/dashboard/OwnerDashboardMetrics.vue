<script setup lang="ts">
import { LandPlot, Ruler, Sprout } from '@lucide/vue';
import { computed } from 'vue';
import type { DashboardData } from '@/types/dashboard';

const props = defineProps<{ dashboard: DashboardData }>();
const metrics = computed(() =>
    props.dashboard.metrics.filter((metric) => metric.key !== 'risks'),
);
const activeAreaNote = computed(
    () =>
        props.dashboard.metrics.find((metric) => metric.key === 'cycles')?.note,
);
const icons = { farms: LandPlot, area: Ruler, cycles: Sprout, risks: Sprout };
</script>

<template>
    <section
        class="grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-3 lg:gap-6"
        aria-label="Ringkasan kebun"
    >
        <article
            v-for="metric in metrics"
            :key="metric.key"
            class="min-w-0 rounded-xl border border-border bg-card p-3.5 lg:p-5"
            :class="metric.key === 'cycles' ? 'col-span-2 sm:col-span-1' : ''"
        >
            <div class="flex items-start justify-between gap-2">
                <h2
                    class="text-sm leading-5 font-medium lg:text-base lg:leading-6"
                >
                    {{ metric.label }}
                </h2>
                <component
                    :is="icons[metric.key]"
                    class="mt-0.5 size-4 shrink-0 text-secondary dark:text-primary"
                    aria-hidden="true"
                />
            </div>
            <p
                class="mt-3 text-[28px] leading-9 font-bold break-words tabular-nums lg:text-4xl lg:leading-10 2xl:text-[40px]"
            >
                {{ metric.value }}
                <span class="text-sm font-normal text-muted-foreground">{{
                    metric.unit
                }}</span>
            </p>
            <p
                class="mt-2 text-xs leading-5 text-muted-foreground lg:text-sm lg:leading-6"
            >
                <template v-if="metric.key === 'cycles'"
                    >{{ dashboard.cycles.planned }} persiapan ·
                    {{ dashboard.cycles.active }} aktif</template
                >
                <template v-else-if="metric.key === 'area' && activeAreaNote">{{
                    activeAreaNote
                }}</template>
                <template v-else>{{ metric.note }}</template>
            </p>
        </article>
        <p
            v-if="!metrics.length"
            role="status"
            class="col-span-2 rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground sm:col-span-3"
        >
            Ringkasan kebun belum tersedia.
        </p>
    </section>
</template>
