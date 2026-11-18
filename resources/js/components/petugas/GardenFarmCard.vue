<script setup lang="ts">
import { LandPlot, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import FarmLocationMap from '@/components/farms/FarmLocationMap.vue';
import type { PetugasGarden } from '@/types/petugas-garden';
import { gardenArea } from '@/types/petugas-garden';

const props = defineProps<{
    farm: NonNullable<PetugasGarden['farm']>;
    summary: PetugasGarden['summary'];
}>();
const coordinates = computed<[number, number] | null>(() => {
    if (props.farm.latitude === null || props.farm.longitude === null)
        return null;

    const latitude = Number(props.farm.latitude);
    const longitude = Number(props.farm.longitude);
    if (
        !Number.isFinite(latitude) ||
        !Number.isFinite(longitude) ||
        Math.abs(latitude) > 90 ||
        Math.abs(longitude) > 180
    )
        return null;

    return [latitude, longitude];
});
</script>

<template>
    <section
        aria-labelledby="garden-farm-name"
        class="petugas-card space-y-4 p-4"
    >
        <div class="flex items-start gap-3">
            <span
                class="grid size-11 shrink-0 place-items-center"
            >
                <LandPlot
                    class="size-7 text-secondary dark:text-primary"
                    aria-hidden="true"
                />
            </span>
            <div class="min-w-0">
                <h2
                    id="garden-farm-name"
                    class="text-lg leading-6 font-bold break-words"
                >
                    {{ farm.name }}
                </h2>
                <p
                    v-if="farm.address"
                    class="mt-1 flex items-start gap-1.5 text-xs text-muted-foreground"
                >
                    <MapPin
                        class="mt-0.5 size-3.5 shrink-0 text-secondary dark:text-primary"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 break-words">{{ farm.address }}</span>
                </p>
            </div>
        </div>
        <dl
            class="grid grid-cols-3 divide-x divide-border border-t border-border pt-5"
        >
            <div
                class="garden-farm-metric flex min-w-0 flex-col px-1 py-2 text-center"
            >
                <dt
                    class="order-2 mt-3 text-xs leading-5 text-muted-foreground"
                >
                    Luas lahan
                </dt>
                <dd
                    class="order-1 inline-flex items-start justify-center gap-1"
                >
                    <span
                        class="garden-farm-metric-value font-bold tracking-tight text-foreground tabular-nums"
                        >{{ gardenArea(farm.total_area_ha) }}</span
                    >
                    <span class="mt-1 text-sm font-semibold text-primary"
                        >ha</span
                    >
                </dd>
            </div>
            <div
                class="garden-farm-metric flex min-w-0 flex-col px-1 py-2 text-center"
            >
                <dt
                    class="order-2 mt-3 text-xs leading-5 text-muted-foreground"
                >
                    Blok
                </dt>
                <dd
                    class="garden-farm-metric-value order-1 font-bold tracking-tight text-foreground tabular-nums"
                >
                    {{ summary.blocks }}
                </dd>
            </div>
            <div
                class="garden-farm-metric flex min-w-0 flex-col px-1 py-2 text-center"
            >
                <dt
                    class="order-2 mt-3 text-xs leading-5 text-muted-foreground"
                >
                    Subblok
                </dt>
                <dd
                    class="garden-farm-metric-value order-1 font-bold tracking-tight text-foreground tabular-nums"
                >
                    {{ summary.batches }}
                </dd>
            </div>
        </dl>
        <div class="space-y-3 border-t border-border pt-4">
            <h3 class="admin-section-title">Peta lokasi</h3>
            <FarmLocationMap
                :model-value="coordinates"
                :initial-zoom="18"
                :show-hint="false"
            />
        </div>
    </section>
</template>

<style scoped>
.garden-farm-metric {
    container-type: inline-size;
}
.garden-farm-metric-value {
    font-size: clamp(1.5rem, 30cqi, 2.5rem);
    line-height: 1.1;
}
</style>
