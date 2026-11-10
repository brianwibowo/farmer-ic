<script setup lang="ts">
import { LandPlot, Ruler } from '@lucide/vue';
import { computed } from 'vue';
import { ownerArea } from '@/types/owner';
import type { OwnerDashboardLandTable } from '@/types/owner-dashboard';

const props = defineProps<{ table: OwnerDashboardLandTable }>();
const metrics = computed(() => [
    {
        key: 'farms',
        label: 'Total lahan',
        value: ownerArea(props.table.total),
        unit: 'lahan',
        icon: LandPlot,
    },
    {
        key: 'area',
        label: 'Luas lahan',
        value: ownerArea(props.table.total_area_ha),
        unit: 'ha',
        icon: Ruler,
    },
]);
</script>

<template>
    <section
        class="grid min-w-0 grid-cols-2 gap-3 lg:gap-6"
        aria-label="Ringkasan total lahan"
    >
        <article
            v-for="metric in metrics"
            :key="metric.key"
            class="min-w-0 rounded-xl border border-border bg-card p-3.5 lg:p-5"
        >
            <div class="flex items-start justify-between gap-2">
                <h2
                    class="text-sm leading-5 font-medium lg:text-base lg:leading-6"
                >
                    {{ metric.label }}
                </h2>
                <component
                    :is="metric.icon"
                    class="mt-0.5 size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </div>
            <p
                class="admin-metric-value mt-3 break-words tabular-nums"
            >
                {{ metric.value }}
                <span class="text-sm font-normal text-muted-foreground">{{
                    metric.unit
                }}</span>
            </p>
        </article>
    </section>
</template>
