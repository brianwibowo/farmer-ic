<script setup lang="ts">
import { BookOpen, CalendarCheck, ClipboardList, LandPlot, TriangleAlert } from "@lucide/vue";
import SummaryStatCard from "@/components/SummaryStatCard.vue";
import type { LogbookSummary } from "@/types/logbook";

defineProps<{ summary: LogbookSummary; directory?: boolean }>();
const number = new Intl.NumberFormat("id-ID");
</script>

<template>
    <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4"
        aria-label="Ringkasan logbook"
    >
        <SummaryStatCard
            :label="directory ? 'Total lahan' : 'Kegiatan tercatat'"
            :value="number.format(directory ? summary.farms : summary.activities)"
            :icon="directory ? LandPlot : ClipboardList"
        />
        <SummaryStatCard
            label="Total logbook"
            :value="number.format(summary.reports)"
            :icon="BookOpen"
        />
        <SummaryStatCard
            label="Logbook hari ini"
            :value="number.format(summary.today)"
            :icon="CalendarCheck"
        />
        <SummaryStatCard
            label="Kendala tercatat"
            :value="number.format(summary.obstacles)"
            :icon="TriangleAlert"
        />
    </section>
</template>
