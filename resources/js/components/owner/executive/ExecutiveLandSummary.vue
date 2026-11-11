<script setup lang="ts">
import { computed } from 'vue';
import OwnerDistributionChart from '@/components/owner/OwnerDistributionChart.vue';
import ExecutivePanel from './ExecutivePanel.vue';
import { executiveNumber as number } from '@/services/ownerExecutiveDemoService';

const props = defineProps<{
    partners: {
        id: string;
        name: string;
        area: number;
        planted: number;
        utilization: number;
        farmCount: number;
    }[];
    selected: string;
}>();
defineEmits<{ select: [id: string] }>();
const byId = computed(
    () => new Map(props.partners.map((item) => [item.id, item])),
);
const items = computed(() =>
    props.partners.map((partner) => ({
        id: partner.id,
        name: partner.name,
        value: partner.area,
        detail: `${partner.farmCount} kebun, tertanam ${number(partner.planted)} Ha, utilisasi ${number(partner.utilization)}%`,
    })),
);
</script>

<template>
    <ExecutivePanel
        id="kerja-sama"
        title="Kerja sama & luas lahan"
        description="Kontribusi mitra dan pemanfaatan lahannya. Pilih mitra untuk memfilter ringkasan."
    >
        <OwnerDistributionChart
            title="Kontribusi luas lahan per mitra"
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
            <template #item="{ item }"
                ><span class="min-w-0 flex-1"
                    ><span
                        class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1"
                        ><span class="text-sm font-semibold">{{
                            item.name
                        }}</span
                        ><span class="shrink-0 text-xs tabular-nums"
                            ><strong>{{ number(item.value) }} Ha</strong>
                            <span class="text-muted-foreground"
                                >· {{ item.percentage }}</span
                            ></span
                        ></span
                    ><span
                        class="mt-1 flex flex-wrap justify-between gap-x-3 gap-y-1 text-xs text-muted-foreground"
                        ><span
                            >{{ byId.get(item.id)?.farmCount }} kebun ·
                            {{ number(byId.get(item.id)?.planted ?? 0) }} Ha
                            tertanam</span
                        ><span
                            >{{ number(byId.get(item.id)?.utilization ?? 0) }}%
                            utilisasi</span
                        ></span
                    ><span
                        class="mt-2 flex h-2 overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                        ><span
                            class="h-full bg-primary"
                            :style="{
                                width: `${byId.get(item.id)?.utilization ?? 0}%`,
                            }" /></span></span
            ></template>
        </OwnerDistributionChart>
        <p class="mt-3 flex flex-wrap gap-4 text-xs text-muted-foreground">
            <span class="inline-flex items-center gap-1.5"
                ><span
                    class="size-2 rounded-full bg-primary"
                    aria-hidden="true"
                />Tertanam</span
            ><span class="inline-flex items-center gap-1.5"
                ><span
                    class="size-2 rounded-full bg-muted ring-1 ring-border"
                    aria-hidden="true"
                />Belum ditanam</span
            >
        </p>
    </ExecutivePanel>
</template>
