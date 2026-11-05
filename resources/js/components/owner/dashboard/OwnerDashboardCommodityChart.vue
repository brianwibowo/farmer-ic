<script setup lang="ts">
import { Leaf } from '@lucide/vue';
import { computed, useId } from 'vue';
import type { DashboardData } from '@/types/dashboard';

const props = defineProps<{ commodities: DashboardData['commodities'] }>();
const headingId = useId();
const areaFormat = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 4,
});
const percentFormat = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 1,
});
const colors = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-5)',
    'var(--chart-4)',
];
const crops = computed(() =>
    props.commodities.filter(
        (crop) => Number.isFinite(crop.areaValue) && crop.areaValue > 0,
    ),
);
const total = computed(() =>
    crops.value.reduce((sum, crop) => sum + crop.areaValue, 0),
);

function percent(value: number): string {
    return value < 0.1 ? '<0,1%' : `${percentFormat.format(value)}%`;
}

function sector(start: number, end: number): string {
    const point = (angle: number) => {
        const radians = ((angle - 90) * Math.PI) / 180;
        return `${100 + 94 * Math.cos(radians)} ${100 + 94 * Math.sin(radians)}`;
    };
    return `M 100 100 L ${point(start)} A 94 94 0 ${end - start > 180 ? 1 : 0} 1 ${point(end)} Z`;
}

const slices = computed(() => {
    if (!Number.isFinite(total.value) || total.value <= 0) return [];
    let angle = 0;
    return crops.value.map((crop, index) => {
        const share = (crop.areaValue / total.value) * 100;
        const end =
            index === crops.value.length - 1 ? 360 : angle + share * 3.6;
        const slice = {
            ...crop,
            areaLabel: areaFormat.format(crop.areaValue),
            percentage: percent(share),
            color: colors[index % colors.length],
            path: sector(angle, end),
        };
        angle = end;
        return slice;
    });
});
const chartDescription = computed(
    () =>
        `Komposisi luas tanam: ${slices.value
            .map(
                (crop) =>
                    `${crop.name}, ${crop.areaLabel} hektar, ${crop.percentage}`,
            )
            .join('; ')}.`,
);
</script>

<template>
    <section
        class="flex h-full min-w-0 flex-col rounded-xl border border-border bg-card p-4 lg:p-5"
        :aria-labelledby="headingId"
    >
        <div class="flex items-center justify-between gap-3">
            <h2 :id="headingId" class="text-base font-semibold 2xl:text-lg">
                Komoditas
            </h2>
            <Leaf class="size-4 text-muted-foreground" aria-hidden="true" />
        </div>
        <div
            v-if="slices.length"
            class="mt-5 flex flex-1 flex-wrap items-center justify-center gap-6"
        >
            <svg
                viewBox="0 0 200 200"
                class="mx-auto aspect-square w-40 max-w-full shrink-0 2xl:w-48"
                role="img"
                :aria-label="chartDescription"
            >
                <circle
                    v-if="slices.length === 1"
                    cx="100"
                    cy="100"
                    r="94"
                    :fill="slices[0].color"
                />
                <template v-else>
                    <path
                        v-for="crop in slices"
                        :key="crop.id"
                        :d="crop.path"
                        :fill="crop.color"
                        stroke="var(--card)"
                        stroke-width="2"
                        stroke-linejoin="round"
                    />
                </template>
            </svg>
            <dl class="min-w-0 flex-1 basis-40 space-y-4">
                <div v-for="crop in slices" :key="crop.id">
                    <dt class="flex items-start gap-2 text-sm leading-5">
                        <span
                            class="mt-1 size-3 shrink-0 rounded-full border border-border"
                            :style="{ background: crop.color }"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 break-words">{{ crop.name }}</span>
                    </dt>
                    <dd
                        class="mt-1 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 pl-5 text-sm leading-5 tabular-nums"
                    >
                        <span class="font-semibold"
                            >{{ crop.areaLabel }} ha</span
                        >
                        <span class="text-muted-foreground">{{
                            crop.percentage
                        }}</span>
                    </dd>
                </div>
            </dl>
        </div>
        <p
            v-else
            role="status"
            class="flex min-h-40 flex-1 items-center justify-center py-6 text-center text-sm leading-6 text-muted-foreground"
        >
            Belum ada luas tanam komoditas yang tercatat.
        </p>
        <p class="mt-5 text-xs leading-5 text-muted-foreground">
            Berdasarkan luas tanam pada siklus aktif atau sudah panen.
        </p>
    </section>
</template>
