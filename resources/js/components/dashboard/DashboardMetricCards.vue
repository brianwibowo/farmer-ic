<script setup lang="ts">
import {
    CircleAlert,
    LandPlot,
    Ruler,
    Sprout,
    TriangleAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import type { DashboardData } from '@/types/dashboard';

const props = defineProps<{ dashboard: DashboardData }>();
const icons = {
    farms: LandPlot,
    area: Ruler,
    cycles: Sprout,
    risks: CircleAlert,
};
const decimal = (value: string | number) =>
    typeof value === 'number'
        ? value
        : Number(value.replaceAll('.', '').replace(',', '.')) || 0;
const farmAreas = computed(() =>
    props.dashboard.landMap.farms
        .map((farm) => decimal(farm.area))
        .sort((a, b) => b - a)
        .slice(0, 3),
);
const plantedArea = computed(() => {
    const note =
        props.dashboard.metrics.find((metric) => metric.key === 'cycles')
            ?.note ?? '';
    return note.match(/^([\d.,]+)\s+ha/)?.[1] ?? '0';
});
const usage = computed(() => {
    const total = decimal(
        props.dashboard.metrics.find((metric) => metric.key === 'area')
            ?.value ?? 0,
    );
    return total > 0 ? Math.min(1, decimal(plantedArea.value) / total) : 0;
});
const stageMax = computed(() =>
    Math.max(1, ...props.dashboard.cycles.stages.map((stage) => stage.count)),
);
const gauge = computed(() => ({
    background: `conic-gradient(from -90deg at 50% 100%, var(--primary) 0 ${usage.value * 180}deg, var(--admin-chart-tertiary) ${usage.value * 180}deg 180deg, transparent 180deg)`,
}));
</script>

<template>
    <section class="dashboard-metrics" aria-label="Metrik utama">
        <article
            v-for="metric in dashboard.metrics"
            :key="metric.key"
            class="dashboard-panel dashboard-metric"
        >
            <div class="flex items-center justify-between gap-2">
                <p class="dashboard-metric-label">{{ metric.label }}</p>
                <component
                    :is="icons[metric.key]"
                    :size="14"
                    class="shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>
            <div class="dashboard-metric-middle">
                <p
                    class="dashboard-metric-number"
                    :class="{
                        'text-warning':
                            metric.key === 'risks' && Number(metric.value) > 0,
                    }"
                >
                    {{ metric.value
                    }}<small v-if="metric.key === 'area'"> ha</small
                    ><span v-else class="sr-only"> {{ metric.unit }}</span>
                </p>
                <div
                    v-if="metric.key === 'farms'"
                    class="dashboard-metric-mini"
                    aria-hidden="true"
                >
                    <span
                        v-for="(area, index) in farmAreas"
                        :key="index"
                        :style="{
                            height: `${Math.max(4, (area / Math.max(...farmAreas, 1)) * 36)}px`,
                        }"
                    />
                </div>
                <div
                    v-else-if="metric.key === 'area'"
                    class="dashboard-metric-gauge"
                    :style="gauge"
                    aria-hidden="true"
                />
                <div
                    v-else-if="metric.key === 'cycles'"
                    class="dashboard-metric-mini dashboard-metric-stages"
                    aria-hidden="true"
                >
                    <span
                        v-for="stage in dashboard.cycles.stages"
                        :key="stage.code"
                        :style="{
                            height: `${Math.max(3, (stage.count / stageMax) * 36)}px`,
                        }"
                    />
                </div>
                <TriangleAlert
                    v-else
                    class="dashboard-metric-risk"
                    :size="30"
                    aria-hidden="true"
                />
            </div>
            <p class="dashboard-metric-note">
                <template v-if="metric.key === 'area'"
                    ><strong class="font-medium text-primary"
                        >{{ plantedArea }} ha</strong
                    >
                    sudah ditanami</template
                >
                <template v-else-if="metric.key === 'cycles'"
                    >{{ dashboard.cycles.planned }} persiapan ·
                    {{ dashboard.cycles.active }} aktif</template
                >
                <template v-else-if="metric.key === 'farms'"
                    >Dalam cakupan akses</template
                >
                <template v-else>Membutuhkan tindak lanjut</template>
            </p>
        </article>
    </section>
</template>
