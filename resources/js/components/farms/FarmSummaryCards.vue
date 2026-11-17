<script setup lang="ts">
import { Building2, LandPlot, Ruler } from "@lucide/vue";
import SummaryStatCard from "@/components/SummaryStatCard.vue";
import { hectares } from "@/types/farms";

defineProps<{ summary: { count: number; area_ha: string; cities: number } }>();
const number = new Intl.NumberFormat("id-ID");
</script>

<template>
    <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4"
        aria-label="Ringkasan manajemen lahan"
    >
        <SummaryStatCard
            label="Total lahan"
            :value="number.format(summary.count)"
            :icon="LandPlot"
        />
        <SummaryStatCard
            label="Luas terdaftar (ha)"
            :value="hectares(summary.area_ha)"
            :icon="Ruler"
        />
        <SummaryStatCard
            label="Wilayah kota/kabupaten"
            :value="number.format(summary.cities)"
            :icon="Building2"
        />
    </section>
</template>
