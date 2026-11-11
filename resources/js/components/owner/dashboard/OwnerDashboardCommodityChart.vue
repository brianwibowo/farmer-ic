<script setup lang="ts">
import OwnerDistributionChart from '@/components/owner/OwnerDistributionChart.vue';
import type { DashboardData } from '@/types/dashboard';
defineProps<{ commodities: DashboardData['commodities'] }>();
</script>

<template>
    <OwnerDistributionChart
        class="owner-dashboard-commodity-chart"
        title="Komoditas"
        description="Luas tanam aktif dan sudah panen"
        total-label="Luas tanam"
        category-label="komoditas"
        unit="ha"
        :items="
            commodities.map((crop) => ({
                id: crop.id,
                name: crop.name,
                value: crop.areaValue,
            }))
        "
    />
</template>

<style scoped>
/* Retain the style block for HMR requests from chart instances mounted before extraction. */
.owner-dashboard-commodity-chart {
    min-width: 0;
}
</style>
