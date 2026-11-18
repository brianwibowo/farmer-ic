<script setup lang="ts">
import { computed } from 'vue';
import type { GardenBlock } from '@/types/petugas-garden';
import { gardenArea } from '@/types/petugas-garden';

const props = defineProps<{
    block: Pick<GardenBlock, 'name' | 'area_ha' | 'available_ha'>;
}>();
const metrics = computed(() => [
    { label: 'Total luas blok', value: props.block.area_ha },
    { label: 'Sisa luas blok', value: props.block.available_ha },
]);
</script>

<template>
    <section class="petugas-card p-5" :aria-label="`Kapasitas ${block.name}`">
        <dl class="grid grid-cols-2 divide-x divide-border">
            <div
                v-for="metric in metrics"
                :key="metric.label"
                class="garden-block-capacity-metric flex min-w-0 flex-col px-2 py-2 text-center"
            >
                <dt
                    class="order-2 mt-3 text-xs leading-5 text-muted-foreground"
                >
                    {{ metric.label }}
                </dt>
                <dd
                    class="order-1 inline-flex items-start justify-center gap-1"
                >
                    <span
                        class="garden-block-capacity-value font-bold tracking-tight text-foreground tabular-nums"
                        >{{ gardenArea(metric.value) }}</span
                    >
                    <span class="mt-1 text-sm font-semibold text-primary"
                        >ha</span
                    >
                </dd>
            </div>
        </dl>
    </section>
</template>

<style scoped>
.garden-block-capacity-metric {
    container-type: inline-size;
}
.garden-block-capacity-value {
    font-size: clamp(1.375rem, 20cqi, 2.5rem);
    line-height: 1.1;
}
</style>
