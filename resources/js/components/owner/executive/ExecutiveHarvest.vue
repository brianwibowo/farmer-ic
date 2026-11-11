<script setup lang="ts">
import { ChevronDown } from "@lucide/vue";
import { computed } from "vue";
import ExecutiveChart from "./ExecutiveChart.vue";
import ExecutivePanel from "./ExecutivePanel.vue";
import ExecutiveTable from "./ExecutiveTable.vue";
import {
    executiveNumber as number,
    executiveTotals,
} from "@/services/ownerExecutiveDemoService";
import type { ExecutiveColumn, ExecutiveRow } from "@/types/owner-executive";

const props = defineProps<{ rows: ExecutiveRow[]; period: string }>();
defineEmits<{ select: [id: string] }>();
const unit = "ton";
const totals = computed(() => executiveTotals(props.rows));
const achievement = computed(() =>
    totals.value.projected
        ? (totals.value.harvested / totals.value.projected) * 100
        : 0,
);
const columns: ExecutiveColumn[] = [
    { key: "farm", label: "Kebun" },
    { key: "crop", label: "Komoditas" },
    { key: "projected", label: `Proyeksi (${unit})`, numeric: true },
    { key: "actual", label: `Aktual (${unit})`, numeric: true },
    { key: "difference", label: `Selisih (${unit})`, numeric: true },
    { key: "achievement", label: "Pencapaian", numeric: true },
];
const table = computed(() =>
    props.rows.map((row) => ({
        id: row.farm.id,
        farm: row.farm.name,
        crop: row.commodity.name,
        projected: number(row.projectedTon),
        actual: number(row.harvestedTon),
        difference: number(row.harvestedTon - row.projectedTon),
        achievement: `${number(row.projectedTon ? (row.harvestedTon / row.projectedTon) * 100 : 0)}%`,
    })),
);
</script>

<template>
    <ExecutivePanel
        id="panen"
        title="Panen aktual vs proyeksi"
        :description="period"
    >
        <template #actions>
            <span
                class="inline-flex items-center rounded-lg border border-border bg-muted/50 px-3 py-1.5 text-xs font-medium text-muted-foreground"
            >
                Satuan: Ton
            </span>
        </template>
        <dl class="mb-3 grid grid-cols-3 gap-3 text-xs text-muted-foreground">
            <div>
                <dt>Panen aktual</dt>
                <dd
                    class="mt-1 text-base font-semibold text-foreground tabular-nums sm:text-xl"
                >
                    {{ number(totals.harvested) }} {{ unit }}
                </dd>
            </div>
            <div>
                <dt>Proyeksi</dt>
                <dd
                    class="mt-1 text-base font-semibold text-foreground tabular-nums sm:text-xl"
                >
                    {{ number(totals.projected) }} {{ unit }}
                </dd>
            </div>
            <div>
                <dt>Pencapaian</dt>
                <dd
                    class="mt-1 text-base font-semibold text-secondary tabular-nums sm:text-xl dark:text-primary"
                >
                    {{ number(achievement) }}%
                </dd>
            </div>
        </dl>
        <ExecutiveChart
            title="Perbandingan panen aktual dan proyeksi per kebun"
            kind="grouped"
            :labels="rows.map((row) => row.farm.name.replace('Kebun ', ''))"
            :series="[
                {
                    label: 'Proyeksi',
                    values: rows.map((row) => row.projectedTon),
                },
                {
                    label: 'Aktual',
                    values: rows.map((row) => row.harvestedTon),
                },
            ]"
            :unit="unit"
            interactive
            @select="$emit('select', rows[$event]?.farm.id ?? '')"
        />
        <details class="group mt-4 border-t border-border pt-2">
            <summary
                class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 rounded-lg px-2 text-sm font-medium text-secondary hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
            >
                <span
                    >Lihat rincian panen
                    <span class="ml-1 font-normal text-muted-foreground"
                        >· {{ rows.length }} kebun</span
                    ></span
                ><ChevronDown
                    class="size-4 group-open:rotate-180"
                    aria-hidden="true"
                />
            </summary>
            <div class="mt-2">
                <ExecutiveTable
                    :columns="columns"
                    :rows="table"
                    :caption="`Rincian panen ${period}`"
                    selectable
                    @select="$emit('select', String($event.id))"
                />
            </div>
        </details>
    </ExecutivePanel>
</template>
