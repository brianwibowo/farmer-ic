<script setup lang="ts">
import { computed } from 'vue';
import {
    BookOpen,
    CalendarCheck,
    ClipboardList,
    LandPlot,
    TriangleAlert,
} from '@lucide/vue';
import type { LogbookSummary } from '@/types/logbook';
const props = defineProps<{ summary: LogbookSummary; directory?: boolean }>();
const metrics = computed(() => [
    props.directory
        ? { label: 'Kebun', value: props.summary.farms, icon: LandPlot }
        : {
              label: 'Kegiatan tercatat',
              value: props.summary.activities,
              icon: ClipboardList,
          },
    { label: 'Total logbook', value: props.summary.reports, icon: BookOpen },
    {
        label: 'Logbook hari ini',
        value: props.summary.today,
        icon: CalendarCheck,
    },
    {
        label: 'Kendala tercatat',
        value: props.summary.obstacles,
        icon: TriangleAlert,
    },
]);
</script>
<template>
    <section
        class="grid grid-cols-2 gap-3 lg:grid-cols-4"
        aria-label="Ringkasan logbook"
    >
        <article
            v-for="metric in metrics"
            :key="metric.label"
            class="admin-panel"
        >
            <div class="flex items-start justify-between gap-2">
                <h2 class="text-sm font-semibold">{{ metric.label }}</h2>
                <component
                    :is="metric.icon"
                    class="hidden size-5 shrink-0 text-primary sm:block"
                    aria-hidden="true"
                />
            </div>
            <p class="admin-metric-value mt-3">
                {{ new Intl.NumberFormat('id-ID').format(metric.value) }}
            </p>
        </article>
    </section>
</template>
