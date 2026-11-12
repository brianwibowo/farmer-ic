<script setup lang="ts">
import { computed } from 'vue';
import OwnerDistributionChart from '@/components/owner/OwnerDistributionChart.vue';
import ExecutivePanel from './ExecutivePanel.vue';
import {
    executiveCommodities,
    executiveNumber as number,
} from '@/services/ownerExecutiveDemoService';

const props = defineProps<{
    commodities: ReturnType<typeof executiveCommodities>;
    selected: string;
}>();
const emit = defineEmits<{ select: [id: string] }>();
const byId = computed(
    () => new Map(props.commodities.map((crop) => [crop.id, crop])),
);
const cards = computed(() => [
    {
        id: 'planted',
        title: 'Luas tanam per komoditas',
        label: 'Luas tanam',
        unit: 'Ha',
        items: props.commodities.map((crop) => ({
            id: crop.id,
            name: crop.name,
            value: crop.planted,
            detail: `Panen aktual ${number(crop.harvested)} ton, proyeksi ${number(crop.projected)} ton`,
        })),
    },
    {
        id: 'harvested',
        title: 'Panen aktual per komoditas',
        label: 'Panen aktual',
        unit: 'ton',
        items: props.commodities.map((crop) => ({
            id: crop.id,
            name: crop.name,
            value: crop.harvested,
            detail: `Luas tanam ${number(crop.planted)} Ha, proyeksi ${number(crop.projected)} ton`,
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
                :selected="selected || null"
                :unit="card.unit"
                :total-label="card.label"
                category-label="komoditas"
                embedded
                compact
                :show-footer="false"
                @update:selected="emit('select', $event ?? '')"
            >
                <template #item="{ item }"
                    ><span class="min-w-0 flex-1"
                        ><span class="flex items-center justify-between gap-2"
                            ><span class="text-sm font-semibold">{{
                                item.name
                            }}</span
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
                                        number(
                                            byId.get(item.id)?.projected ?? 0,
                                        )
                                    }}
                                    ton</strong
                                ></span
                            ></span
                        ></span
                    ></template
                >
            </OwnerDistributionChart>
        </ExecutivePanel>
    </div>
</template>
