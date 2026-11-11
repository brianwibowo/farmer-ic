<script setup lang="ts">
import { computed, ref } from 'vue';
import OwnerDistributionChart from '@/components/owner/OwnerDistributionChart.vue';
import ExecutivePanel from './ExecutivePanel.vue';
import {
    executiveCommodities,
    executiveMoney as money,
    executiveNumber as number,
} from '@/services/ownerExecutiveDemoService';

const props = defineProps<{
    commodities: ReturnType<typeof executiveCommodities>;
    selected: string;
}>();
defineEmits<{ select: [id: string] }>();
const measure = ref('area');
const byId = computed(
    () => new Map(props.commodities.map((item) => [item.id, item])),
);
const items = computed(() =>
    props.commodities.map((crop) => ({
        id: crop.id,
        name: crop.name,
        value: measure.value === 'area' ? crop.planted : crop.harvested,
        detail: `${number(crop.planted)} Ha, panen ${number(crop.harvested)} ton, proyeksi ${number(crop.projected)} ton; produktivitas ${crop.tonPerHa} ton/Ha/cycle`,
    })),
);
</script>

<template>
    <ExecutivePanel id="komoditas" title="Komposisi & ringkasan komoditas">
        <template #actions
            ><label class="exec-label"
                ><span class="sr-only">Ukuran komposisi komoditas</span
                ><select v-model="measure" class="exec-field">
                    <option value="area">Luas tanam (Ha)</option>
                    <option value="production">Produksi aktual (ton)</option>
                </select></label
            ></template
        >
        <OwnerDistributionChart
            title="Komposisi komoditas"
            :items="items"
            :selected="selected || null"
            :unit="measure === 'area' ? 'Ha' : 'ton'"
            :total-label="measure === 'area' ? 'Luas tanam' : 'Panen aktual'"
            category-label="komoditas"
            embedded
            compact
            :show-footer="false"
            @update:selected="$emit('select', $event ?? '')"
        >
            <template #item="{ item }"
                ><span class="min-w-0 flex-1"
                    ><span class="flex items-center justify-between gap-2"
                        ><span class="text-sm font-semibold">{{
                            item.name
                        }}</span
                        ><span
                            class="text-xs font-medium text-muted-foreground tabular-nums"
                            >{{ item.percentage }}</span
                        ></span
                    ><span
                        class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs sm:grid-cols-3"
                        ><span class="text-muted-foreground"
                            >Tanam
                            <strong class="block font-semibold text-foreground"
                                >{{
                                    number(byId.get(item.id)?.planted ?? 0)
                                }}
                                Ha</strong
                            ></span
                        ><span class="text-muted-foreground"
                            >Aktual / proyeksi<strong
                                class="block font-semibold text-foreground"
                                >{{
                                    number(byId.get(item.id)?.harvested ?? 0)
                                }}
                                /
                                {{
                                    number(byId.get(item.id)?.projected ?? 0)
                                }}
                                ton</strong
                            ></span
                        ><span class="text-muted-foreground"
                            >Potensi omzet<strong
                                class="block font-semibold text-secondary dark:text-primary"
                                >{{
                                    money(byId.get(item.id)?.revenue ?? 0, true)
                                }}</strong
                            ></span
                        ></span
                    ></span
                ></template
            >
        </OwnerDistributionChart>
    </ExecutivePanel>
</template>
