<script setup lang="ts">
import { ArrowUpRight, ChevronDown } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';
import ExecutiveChart from './ExecutiveChart.vue';
import ExecutivePanel from './ExecutivePanel.vue';
import ExecutiveTable from './ExecutiveTable.vue';
import {
    executiveNumber as number,
    executiveAvailableProjection,
    executiveProjection as projection,
    executiveTotals,
} from '@/services/ownerExecutiveSummary';
import type { ExecutiveColumn, ExecutiveRow } from '@/types/owner-executive';

const props = defineProps<{ rows: ExecutiveRow[]; period: string }>();
defineEmits<{ select: [id: string] }>();
const unit = 'ton';
const showProjection = ref(true);
const showActual = ref(true);
const landName = (name: string) => name.replace(/^kebun\b/i, 'Lahan');
const farmGroups = computed(() => {
    const groups = new Map<string, { label: string; rows: ExecutiveRow[] }>();
    for (const row of props.rows) {
        let group = groups.get(row.farm.id);
        if (!group) {
            group = { label: landName(row.farm.name), rows: [] };
            groups.set(row.farm.id, group);
        }
        group.rows.push(row);
    }
    return [...groups.values()];
});
const chartRows = computed(() =>
    farmGroups.value.flatMap((group) => group.rows),
);
const totals = computed(() => executiveTotals(props.rows));
const availableProjection = computed(() =>
    executiveAvailableProjection(props.rows),
);
const achievement = computed(() =>
    totals.value.projected === null
        ? null
        : totals.value.projected
          ? (totals.value.harvested / totals.value.projected) * 100
          : 0,
);
const allColumns: ExecutiveColumn[] = [
    { key: 'farm', label: 'Lahan' },
    { key: 'crop', label: 'Komoditas' },
    { key: 'projected', label: `Proyeksi (${unit})`, numeric: true },
    { key: 'actual', label: `Aktual (${unit})`, numeric: true },
    { key: 'difference', label: `Selisih (${unit})`, numeric: true },
    { key: 'achievement', label: 'Pencapaian', numeric: true },
];
const columns = computed(() =>
    allColumns.filter((column) => {
        if (column.key === 'projected') return showProjection.value;
        if (column.key === 'actual') return showActual.value;
        if (column.key === 'difference' || column.key === 'achievement')
            return showProjection.value && showActual.value;
        return true;
    }),
);
const table = computed(() =>
    props.rows.map((row) => ({
        id: row.id,
        farmId: row.farm.id,
        farm: landName(row.farm.name),
        crop: row.commodity.name,
        projected: projection(row.projectedTon, ''),
        actual: number(row.harvestedTon),
        difference:
            row.projectedTon === null
                ? '—'
                : number(row.harvestedTon - row.projectedTon),
        achievement:
            row.projectedTon === null
                ? '—'
                : `${number(row.projectedTon ? (row.harvestedTon / row.projectedTon) * 100 : 0)}%`,
    })),
);
</script>

<template>
    <ExecutivePanel
        id="panen"
        title="Panen aktual vs proyeksi per lahan"
        :description="period"
    >
        <template #actions>
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center rounded-lg border border-border bg-muted/50 px-3 py-1.5 text-xs font-medium text-muted-foreground"
                >
                    Satuan: Ton
                </span>
                <Button as-child variant="outline" size="sm" class="min-h-11"
                    ><Link
                        href="/proyeksi"
                        aria-label="Lihat detail proyeksi panen"
                        >Lihat detail<ArrowUpRight aria-hidden="true" /></Link
                ></Button>
            </div>
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
                    {{ projection(availableProjection.projected) }}
                </dd>
            </div>
            <div>
                <dt>Pencapaian</dt>
                <dd
                    class="mt-1 text-base font-semibold text-secondary tabular-nums sm:text-xl dark:text-primary"
                >
                    {{ achievement === null ? '—' : `${number(achievement)}%` }}
                </dd>
            </div>
        </dl>
        <p
            v-if="availableProjection.missing"
            role="status"
            class="mb-3 text-xs text-muted-foreground"
        >
            <template v-if="availableProjection.projected !== null">
                {{ availableProjection.missing }}
                lahan dan komoditas belum memiliki proyeksi.
            </template>
            <template v-else>
                Proyeksi belum tersedia untuk {{ availableProjection.missing }}
                baris lahan dan komoditas.
            </template>
        </p>
        <div
            class="mb-3 flex flex-wrap justify-center gap-4 text-xs"
            aria-label="Pilih data grafik"
        >
            <button
                type="button"
                class="inline-flex min-h-11 items-center gap-1.5 rounded focus-visible:outline-2 focus-visible:outline-ring"
                :aria-pressed="showProjection"
                @click="showProjection = !showProjection"
            >
                <span
                    class="size-3 rounded-full bg-[var(--chart-2)]"
                    aria-hidden="true"
                />
                <span
                    :class="{
                        'text-muted-foreground line-through': !showProjection,
                    }"
                    >Proyeksi</span
                >
            </button>
            <button
                type="button"
                class="inline-flex min-h-11 items-center gap-1.5 rounded focus-visible:outline-2 focus-visible:outline-ring"
                :aria-pressed="showActual"
                @click="showActual = !showActual"
            >
                <span
                    class="size-3 rounded-full bg-[var(--chart-1)]"
                    aria-hidden="true"
                />
                <span
                    :class="{
                        'text-muted-foreground line-through': !showActual,
                    }"
                    >Aktual</span
                >
            </button>
        </div>
        <ExecutiveChart
            title="Perbandingan panen aktual dan proyeksi per lahan"
            kind="grouped"
            :show-legend="false"
            :labels="chartRows.map((row) => row.commodity.name)"
            :details="
                chartRows.map((row) =>
                    row.projectedTon === null
                        ? `${landName(row.farm.name)}\nProyeksi: Belum tersedia`
                        : landName(row.farm.name),
                )
            "
            :category-groups="
                farmGroups.map((group) => ({
                    label: group.label,
                    count: group.rows.length,
                }))
            "
            :min-width="Math.max(320, chartRows.length * 160 + 48)"
            :series="[
                {
                    label: 'Proyeksi',
                    hidden: !showProjection,
                    values: chartRows.map((row) => row.projectedTon),
                },
                {
                    label: 'Aktual',
                    hidden: !showActual,
                    values: chartRows.map((row) => row.harvestedTon),
                },
            ]"
            :unit="unit"
            interactive
            @select="$emit('select', chartRows[$event]?.farm.id ?? '')"
        />
        <details class="group mt-4 border-t border-border pt-2">
            <summary
                class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 rounded-lg px-2 text-sm font-medium text-secondary hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
            >
                <span
                    >Lihat rincian panen
                    <span class="ml-1 font-normal text-muted-foreground"
                        >·
                        {{ new Set(rows.map((row) => row.farm.id)).size }}
                        lahan</span
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
                    @select="$emit('select', String($event.farmId))"
                />
            </div>
        </details>
    </ExecutivePanel>
</template>
