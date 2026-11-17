<script setup lang="ts">
import { ClipboardList, Ruler, Sprout, Wheat } from "@lucide/vue";
import SummaryStatCard from "@/components/SummaryStatCard.vue";

defineProps<{
    mode: "planting" | "harvest";
    subblocks: number;
    areaHa: string;
    primaryMetricValue: string;
}>();
const number = new Intl.NumberFormat("id-ID");
const decimal = new Intl.NumberFormat("id-ID", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
</script>

<template>
    <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4"
        :aria-label="
            mode === 'planting' ? 'Ringkasan transaksi penanaman' : 'Ringkasan transaksi panen'
        "
    >
        <SummaryStatCard
            :label="mode === 'planting' ? 'Subblok tertanam' : 'Subblok terpanen'"
            :value="number.format(subblocks)"
            :icon="ClipboardList"
        />
        <SummaryStatCard
            :label="mode === 'planting' ? 'Luas tanam (ha)' : 'Luas panen (ha)'"
            :value="decimal.format(Number(areaHa) || 0)"
            :icon="Ruler"
        />
        <SummaryStatCard
            :label="mode === 'planting' ? 'Benih (kg)' : 'Berat panen (ton)'"
            :value="decimal.format(Number(primaryMetricValue) || 0)"
            :icon="mode === 'planting' ? Sprout : Wheat"
        />
    </section>
</template>
