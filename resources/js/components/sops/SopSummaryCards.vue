<script setup lang="ts">
import { CheckCircle2, ClipboardList, Sprout } from "@lucide/vue";
import SummaryStatCard from "@/components/SummaryStatCard.vue";
import type { SopCommodity, SopSummary } from "@/types/sop";

defineProps<{ total: number; active: number; commodities: SopCommodity[]; summary?: SopSummary }>();
const number = new Intl.NumberFormat("id-ID");
</script>

<template>
    <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4"
        aria-label="Ringkasan template SOP"
    >
        <SummaryStatCard
            label="Total template"
            :value="number.format(summary?.total ?? total)"
            :icon="ClipboardList"
        />
        <SummaryStatCard
            label="Template aktif"
            :value="number.format(summary?.active ?? active)"
            :icon="CheckCircle2"
        />
        <SummaryStatCard
            label="Komoditas terdaftar"
            :value="number.format(commodities.length)"
            :icon="Sprout"
        />
    </section>
</template>
