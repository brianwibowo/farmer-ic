<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { DashboardTrend } from '@/types/dashboard';

const props = defineProps<{ trend: DashboardTrend }>();
const selected = ref<string | null>(null);
const axisMax = computed(() =>
    Math.max(
        4,
        Math.ceil(
            Math.max(...props.trend.days.map((day) => day.value), 0) / 4,
        ) * 4,
    ),
);
const selectedDay = computed(() =>
    props.trend.days.find((day) => day.date === selected.value),
);
watch(
    () => props.trend,
    () => {
        selected.value = null;
    },
);
</script>

<template>
    <article
        class="owner-activity-chart flex min-w-0 flex-col rounded-xl border border-border bg-card p-4"
        aria-labelledby="activity-chart-heading"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h2 id="activity-chart-heading" class="text-base font-semibold">
                Aktivitas lahan
            </h2>
            <div
                class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
                aria-label="Jenis aktivitas"
            >
                <span
                    v-for="(label, index) in trend.series"
                    :key="label"
                    class="inline-flex items-center gap-1.5"
                >
                    <span
                        class="size-2 rounded-full"
                        :class="`dashboard-series-${index}`"
                        aria-hidden="true"
                    />{{ label }}
                </span>
            </div>
        </div>
        <p class="mt-4 flex flex-wrap items-baseline gap-x-2 gap-y-1">
            <strong class="text-[28px] font-bold tabular-nums">{{
                trend.total
            }}</strong>
            <span class="text-xs text-muted-foreground"
                >aktivitas · {{ trend.periodLabel }}</span
            >
        </p>
        <p
            v-if="trend.total === 0"
            class="mt-3 text-sm text-muted-foreground"
            role="status"
        >
            Belum ada aktivitas pada periode ini.
        </p>
        <div
            class="mt-4 flex min-w-0 gap-3"
            role="group"
            aria-label="Grafik jumlah aktivitas"
        >
            <div
                class="flex h-40 w-6 shrink-0 flex-col justify-between text-right text-xs text-muted-foreground"
                aria-hidden="true"
            >
                <span v-for="step in [4, 3, 2, 1, 0]" :key="step">{{
                    (axisMax * step) / 4
                }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <div
                    class="dashboard-chart-grid grid h-40 gap-2 sm:gap-4"
                    :style="{
                        gridTemplateColumns: `repeat(${trend.days.length}, minmax(0, 1fr))`,
                    }"
                >
                    <div
                        v-for="day in trend.days"
                        :key="day.date"
                        class="flex h-full min-w-0 flex-col justify-end"
                    >
                        <button
                            type="button"
                            class="dashboard-chart-bar mx-auto flex h-full w-full max-w-12 flex-col justify-end rounded-lg bg-transparent p-0 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                            :aria-pressed="selected === day.date"
                            :aria-label="`${day.label}: ${day.value} aktivitas. ${trend.series.map((name, index) => `${name} ${day.values[index]}`).join(', ')}`"
                            :title="`${day.label}: ${day.value} aktivitas`"
                            @click="selected = day.date"
                        >
                            <span
                                class="flex w-full flex-col overflow-hidden rounded-t-lg"
                                :style="{
                                    height: `${(day.value / axisMax) * 100}%`,
                                }"
                                aria-hidden="true"
                            >
                                <span
                                    v-for="index in [2, 1, 0]"
                                    :key="index"
                                    class="w-full shrink-0"
                                    :class="`dashboard-series-${index}`"
                                    :style="{
                                        height: `${day.value ? (day.values[index]! / day.value) * 100 : 0}%`,
                                    }"
                                />
                            </span>
                            <span
                                v-if="day.value === 0"
                                class="h-0.5 w-full bg-border"
                            />
                        </button>
                    </div>
                </div>
                <div
                    class="mt-3 grid gap-1 text-center text-xs text-muted-foreground sm:text-xs"
                    :style="{
                        gridTemplateColumns: `repeat(${trend.days.length}, minmax(0, 1fr))`,
                    }"
                >
                    <span
                        v-for="day in trend.days"
                        :key="day.date"
                        class="min-w-0"
                        >{{ day.label }}</span
                    >
                </div>
            </div>
        </div>
        <p
            class="mt-2 min-h-4 text-xs leading-4 text-muted-foreground"
            aria-live="polite"
        >
            <template v-if="selectedDay"
                >{{ selectedDay.label }} ·
                {{
                    trend.series
                        .map(
                            (name, index) =>
                                `${selectedDay!.values[index]} ${name.toLocaleLowerCase('id-ID')}`,
                        )
                        .join(' · ')
                }}</template
            >
            <template v-else
                >Pilih batang untuk melihat rincian aktivitas.</template
            >
        </p>
    </article>
</template>

<style scoped>
.dashboard-series-0 {
    background-color: var(--secondary);
}
.dashboard-series-1 {
    background-color: var(--primary);
}
.dashboard-series-2 {
    background-color: var(--accent);
}
.dashboard-chart-grid {
    background: repeating-linear-gradient(
        to top,
        var(--border) 0 1px,
        transparent 1px 25%
    );
}
</style>
