<script setup lang="ts">
import { computed } from 'vue';
import OwnerDistributionChart from '@/components/owner/OwnerDistributionChart.vue';
import ExecutivePanel from './ExecutivePanel.vue';
import { executiveNumber as number } from '@/services/ownerExecutiveDemoService';

const props = defineProps<{
    partners: { id: string; name: string; area: number; farmCount: number }[];
    categories: { id: string; name: string; value: number }[];
    selected: string;
}>();
defineEmits<{ select: [id: string] }>();
const items = computed(() =>
    props.partners.map((partner) => ({
        id: partner.id,
        name: partner.name,
        value: partner.area,
        detail: `${partner.farmCount} kebun`,
    })),
);
</script>

<template>
    <ExecutivePanel id="kerja-sama" title="Kerja sama & luas lahan">
        <div class="grid min-w-0 gap-5 xl:grid-cols-2">
            <div class="min-w-0">
                <h3 class="mb-4 text-sm font-semibold">Per kategori</h3>
                <OwnerDistributionChart
                    class="land-distribution"
                    title="Luas lahan per kategori"
                    :items="categories"
                    unit="Ha"
                    total-label="Luas lahan"
                    category-label="kategori"
                    embedded
                    compact
                    :show-footer="false"
                />
            </div>
            <div
                class="min-w-0 border-t border-border pt-5 xl:border-t-0 xl:border-l xl:pt-0 xl:pl-5"
            >
                <h3 class="mb-4 text-sm font-semibold">Per mitra</h3>
                <OwnerDistributionChart
                    class="land-distribution"
                    title="Luas lahan per mitra"
                    :items="items"
                    :selected="selected || null"
                    unit="Ha"
                    total-label="Luas lahan"
                    category-label="mitra"
                    embedded
                    compact
                    :show-footer="false"
                    @update:selected="$emit('select', $event ?? '')"
                >
                    <template #item="{ item }">
                        <span class="min-w-0 flex-1">
                            <span
                                class="flex items-start justify-between gap-2"
                            >
                                <span class="text-sm font-semibold">{{
                                    item.name
                                }}</span>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                                    >{{ item.percentage }}</span
                                >
                            </span>
                            <span
                                class="mt-1 block text-xs text-muted-foreground"
                                ><strong class="font-medium text-foreground"
                                    >{{ number(item.value) }} Ha</strong
                                >
                                · {{ item.detail }}</span
                            >
                        </span>
                    </template>
                </OwnerDistributionChart>
            </div>
        </div>
    </ExecutivePanel>
</template>

<style scoped>
.land-distribution :deep(.owner-commodity-center > p:first-child) {
    flex-shrink: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
@container (min-width: 400px) and (max-width: 479px) {
    .land-distribution :deep(.owner-commodity-body) {
        grid-template-columns: 170px minmax(0, 1fr);
        gap: 12px;
    }
    .land-distribution :deep(.owner-commodity-plot) {
        width: 170px;
    }
}
</style>
