<script setup lang="ts">
import {
    ClipboardList,
    Ruler,
    Sprout,
    Wheat,
} from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    mode: 'planting' | 'harvest';
    subblocks: number;
    reports: number;
    areaHa: string;
    primaryMetricValue: string;
    rowAreas: number[];
}>();

const number = new Intl.NumberFormat('id-ID');

function formatDecimal(value: number, digits = 2): string {
    const [whole, fraction] = value.toFixed(digits).split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return fraction ? `${grouped},${fraction}` : grouped;
}

const topAreas = computed(() =>
    props.rowAreas
        .filter((value) => Number.isFinite(value) && value > 0)
        .slice(0, 3),
);
const maxArea = computed(() => Math.max(1, ...topAreas.value));

const densityLabel = computed(() => {
    const area = Number(props.areaHa) || 0;
    const metric = Number(props.primaryMetricValue) || 0;
    if (area <= 0 || metric <= 0) {
        return null;
    }
    const ratio = formatDecimal(metric / area);
    return props.mode === 'planting' ? `${ratio} kg/ha` : `${ratio} ton/ha`;
});

const copy = computed(() =>
    props.mode === 'planting'
        ? {
              ariaLabel: 'Ringkasan transaksi penanaman',
              subblockLabel: 'Subblok tertanam',
              subblockHint: 'Subblok dengan realisasi tanam',
              areaLabel: 'Luas tanam',
              areaHint: 'Akumulasi luas laporan',
              metricLabel: 'Benih',
              metricUnit: 'kg',
              metricHint: 'Akumulasi jumlah benih',
              metricIcon: Sprout,
          }
        : {
              ariaLabel: 'Ringkasan transaksi panen',
              subblockLabel: 'Subblok terpanen',
              subblockHint: 'Subblok dengan realisasi panen',
              areaLabel: 'Luas panen',
              areaHint: 'Akumulasi luas laporan',
              metricLabel: 'Berat panen',
              metricUnit: 'ton',
              metricHint: 'Akumulasi berat kotor',
              metricIcon: Wheat,
          },
);
</script>

<template>
    <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4"
        :aria-label="copy.ariaLabel"
    >
        <!-- 1. Subblocks + report count pill -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">{{ copy.subblockLabel }}</h2>
                <ClipboardList
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(subblocks) }}
                </p>
                <span
                    class="shrink-0 rounded-full bg-accent px-2.5 py-1 text-xs font-semibold tabular-nums text-accent-foreground"
                >
                    {{ number.format(reports) }} laporan
                </span>
            </div>

            <p class="text-xs text-muted-foreground">
                {{ copy.subblockHint }}
            </p>
        </article>

        <!-- 2. Area (ha) + mini row distribution bars -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">{{ copy.areaLabel }}</h2>
                <Ruler
                    class="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ formatDecimal(Number(areaHa) || 0) }}
                    <small
                        class="ml-1 text-xs font-medium tracking-normal text-muted-foreground"
                        >ha</small
                    >
                </p>
                <div
                    v-if="topAreas.length"
                    class="flex h-9 w-[54px] shrink-0 items-end justify-end gap-1.5"
                    aria-hidden="true"
                >
                    <span
                        v-for="(area, index) in topAreas"
                        :key="index"
                        class="w-3.5 rounded-t transition-[height]"
                        :class="
                            index === 0
                                ? 'bg-primary'
                                : index === 1
                                  ? 'bg-[var(--admin-chart-secondary)]'
                                  : 'bg-[var(--admin-chart-tertiary)]'
                        "
                        :style="{
                            height: `${Math.max(5, (area / maxArea) * 36)}px`,
                        }"
                    />
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                {{ copy.areaHint }}
            </p>
        </article>

        <!-- 3. Seed (kg) / Harvest weight (ton) + density ratio pill -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">{{ copy.metricLabel }}</h2>
                <component
                    :is="copy.metricIcon"
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ formatDecimal(Number(primaryMetricValue) || 0) }}
                    <small
                        class="ml-1 text-xs font-medium tracking-normal text-muted-foreground"
                        >{{ copy.metricUnit }}</small
                    >
                </p>
                <span
                    v-if="densityLabel"
                    class="shrink-0 rounded-full bg-accent px-2.5 py-1 text-xs font-semibold tabular-nums text-accent-foreground"
                >
                    {{ densityLabel }}
                </span>
            </div>

            <p class="text-xs text-muted-foreground">
                {{ copy.metricHint }}
            </p>
        </article>
    </section>
</template>
