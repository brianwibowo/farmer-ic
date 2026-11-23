<script setup lang="ts">
import { computed } from 'vue';
import OwnerDistributionChart from '@/components/owner/OwnerDistributionChart.vue';
import ExecutivePanel from './ExecutivePanel.vue';
import {
    executiveCommodityColor,
    executiveCommodities,
    executiveNumber as number,
    executiveProjection as projection,
} from '@/services/ownerExecutiveSummary';

const props = defineProps<{
    commodities: ReturnType<typeof executiveCommodities>;
}>();
const byId = computed(
    () => new Map(props.commodities.map((crop) => [crop.id, crop])),
);
const coloredCommodities = computed(() =>
    props.commodities.map((crop) => ({
        ...crop,
        color: executiveCommodityColor(crop.name),
    })),
);
const cards = computed(() => [
    {
        id: 'planted',
        title: 'Luas tanam per komoditas',
        label: 'Luas tanam',
        unit: 'Ha',
        items: coloredCommodities.value.map((crop) => ({
            id: crop.id,
            name: crop.name,
            color: crop.color,
            value: crop.planted,
            detail: `Panen aktual ${number(crop.harvested)} ton, proyeksi ${projection(crop.projected)}`,
        })),
    },
    {
        id: 'harvested',
        title: 'Panen aktual per komoditas',
        label: 'Panen aktual',
        unit: 'ton',
        items: coloredCommodities.value.map((crop) => ({
            id: crop.id,
            name: crop.name,
            color: crop.color,
            value: crop.harvested,
            detail: `Luas tanam ${number(crop.planted)} Ha, proyeksi ${projection(crop.projected)}`,
        })),
    },
]);
</script>

<template>
    <div id="komoditas" class="grid min-w-0 gap-4 lg:gap-5 xl:grid-cols-2">
        <ExecutivePanel
            v-for="card in cards"
            :key="card.id"
            :title="card.title"
        >
            <OwnerDistributionChart
                :title="card.title"
                :items="card.items"
                :unit="card.unit"
                :total-label="card.label"
                :floor-center-value="card.id === 'harvested'"
                category-label="komoditas"
                embedded
                compact
                :show-footer="false"
            >
                <template #item="{ item }"
                    ><span class="min-w-0 flex-1"
                        ><span class="flex items-center justify-between gap-2"
                            ><span
                                class="flex min-w-0 items-center gap-2 text-sm font-semibold"
                            >
                                <span
                                    class="size-2 shrink-0 rounded-full"
                                    :style="{ backgroundColor: item.color }"
                                    aria-hidden="true"
                                />
                                <span class="min-w-0 break-words">{{
                                    item.name
                                }}</span> </span
                            ><span
                                class="shrink-0 text-xs text-muted-foreground tabular-nums"
                                >{{ item.percentage }}</span
                            ></span
                        ><span
                            class="mt-2 grid grid-cols-3 gap-x-2 gap-y-1 text-xs"
                            ><span class="text-muted-foreground"
                                >Tanam<strong
                                    class="block font-semibold text-foreground"
                                    >{{
                                        number(byId.get(item.id)?.planted ?? 0)
                                    }}
                                    Ha</strong
                                ></span
                            ><span class="text-muted-foreground"
                                >Aktual<strong
                                    class="block font-semibold text-foreground"
                                    >{{
                                        number(
                                            byId.get(item.id)?.harvested ?? 0,
                                        )
                                    }}
                                    ton</strong
                                ></span
                            ><span class="text-muted-foreground"
                                >Proyeksi<strong
                                    class="block font-semibold text-foreground"
                                    >{{
                                        projection(byId.get(item.id)?.projected)
                                    }}
                                </strong></span
                            ></span
                        ></span
                    ></template
                >
            </OwnerDistributionChart>
        </ExecutivePanel>
    </div>
</template>
