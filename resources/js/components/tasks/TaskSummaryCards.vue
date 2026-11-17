<script setup lang="ts">
import { CheckCircle2, CircleDot, ListChecks } from "@lucide/vue";
import SummaryStatCard from "@/components/SummaryStatCard.vue";
import type { AdminTaskSummary } from "@/types/tasks";

defineProps<{ summary: AdminTaskSummary }>();
const number = new Intl.NumberFormat("id-ID");
</script>

<template>
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4" aria-label="Ringkasan tugas">
        <SummaryStatCard
            label="Total tugas"
            :value="number.format(summary.total)"
            :icon="ListChecks"
        />
        <SummaryStatCard
            label="Tugas terbuka"
            :value="number.format(summary.open)"
            :icon="CircleDot"
        />
        <SummaryStatCard
            label="Selesai / tidak dikerjakan"
            :value="number.format(summary.completed)"
            :icon="CheckCircle2"
        />
    </section>
</template>
