<script setup lang="ts">
import { Building2, LandPlot, Ruler } from '@lucide/vue';
import { computed } from 'vue';
import { hectares, type FarmRow } from '@/types/farms';

const props = defineProps<{
    summary: { count: number; area_ha: string; cities: number };
    farms: FarmRow[];
    locationOptions: { cities: string[]; provinces: string[] };
}>();

const number = new Intl.NumberFormat('id-ID');

const topAreas = computed(() =>
    props.farms
        .map((farm) => Number(farm.total_area_ha) || 0)
        .sort((a, b) => b - a)
        .slice(0, 3),
);

const maxArea = computed(() => Math.max(1, ...topAreas.value));

const averageArea = computed(() => {
    const total = Number(props.summary.area_ha) || 0;
    if (props.summary.count <= 0 || total <= 0) {
        return null;
    }
    return hectares((total / props.summary.count).toFixed(2));
});

const previewCities = computed(() => props.locationOptions.cities.slice(0, 2));
const remainingCities = computed(() =>
    Math.max(0, props.locationOptions.cities.length - previewCities.value.length),
);
</script>

<template>
    <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4"
        aria-label="Ringkasan manajemen kebun"
    >
        <!-- 1. Total kebun + mini area comparison bars -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Total kebun</h2>
                <LandPlot
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(summary.count) }}
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
                Dalam cakupan akses tim
            </p>
        </article>

        <!-- 2. Luas terdaftar + average per farm pill -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Luas terdaftar</h2>
                <Ruler
                    class="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ hectares(summary.area_ha) }}
                    <small
                        class="ml-1 text-xs font-medium tracking-normal text-muted-foreground"
                        >ha</small
                    >
                </p>
                <span
                    v-if="averageArea"
                    class="shrink-0 rounded-full bg-accent px-2.5 py-1 text-xs font-semibold tabular-nums text-accent-foreground"
                >
                    ~{{ averageArea }} ha/kebun
                </span>
            </div>

            <p class="text-xs text-muted-foreground">
                Total luasan kebun terdaftar
            </p>
        </article>

        <!-- 3. Wilayah kota/kabupaten + preview city tags -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Wilayah kota/kabupaten</h2>
                <Building2
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] flex-wrap items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(summary.cities) }}
                </p>
                <div
                    v-if="previewCities.length"
                    class="flex min-w-0 flex-wrap items-center justify-end gap-1.5"
                >
                    <span
                        v-for="city in previewCities"
                        :key="city"
                        class="rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground"
                    >
                        {{ city }}
                    </span>
                    <span
                        v-if="remainingCities > 0"
                        class="rounded-full bg-muted px-2 py-1 text-xs font-semibold tabular-nums text-muted-foreground"
                    >
                        +{{ remainingCities }}
                    </span>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                {{
                    locationOptions.provinces.length
                        ? `${number.format(locationOptions.provinces.length)} provinsi terjangkau`
                        : 'Sebaran wilayah kebun'
                }}
            </p>
        </article>
    </section>
</template>
