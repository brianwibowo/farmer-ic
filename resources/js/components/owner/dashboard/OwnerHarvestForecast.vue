<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { OwnerHarvestForecast } from '@/types/owner-dashboard';
import OwnerChartCard from './OwnerChartCard.vue';
import { chartDate, chartNumber, chartRange } from './chartFormat';

const props = defineProps<{ forecast: OwnerHarvestForecast }>();
const measure = ref<'batches' | 'weight'>('batches');
const selected = ref<number | null>(null);
const periods = computed(() => {
    const groups: OwnerHarvestForecast['weeks'] = [];
    for (let index = 0; index < props.forecast.weeks.length; index += 3) {
        const weeks = props.forecast.weeks.slice(index, index + 3);
        const first = weeks[0];
        const last = weeks[weeks.length - 1];
        if (!first || !last) continue;
        const batchCount = weeks.reduce(
            (total, week) => total + week.batchCount,
            0,
        );
        const knownWeights = weeks.filter(
            (week) => week.batchCount > 0 && week.estimatedTon !== null,
        );
        groups.push({
            label: `${chartDate(first.start)}–${chartDate(last.end)}`,
            start: first.start,
            end: last.end,
            batchCount,
            areaHa: weeks.reduce((total, week) => total + week.areaHa, 0),
            estimatedTon:
                batchCount > 0 && knownWeights.length === 0
                    ? null
                    : knownWeights.reduce(
                          (total, week) => total + (week.estimatedTon ?? 0),
                          0,
                      ),
            missingWeightCount: weeks.reduce(
                (total, week) => total + week.missingWeightCount,
                0,
            ),
        });
    }
    return groups;
});
const batchTotal = computed(() =>
    props.forecast.weeks.reduce((total, week) => total + week.batchCount, 0),
);
const hasWeight = computed(() =>
    props.forecast.weeks.some(
        (week) => week.batchCount > 0 && week.estimatedTon !== null,
    ),
);
const missingWeights = computed(() =>
    props.forecast.weeks.reduce(
        (total, week) => total + week.missingWeightCount,
        0,
    ),
);
const selectedPeriod = computed(() =>
    selected.value === null ? null : periods.value[selected.value],
);
const maximum = computed(() => {
    const maximumValue = Math.max(
        0,
        ...periods.value.map((week) =>
            measure.value === 'batches'
                ? week.batchCount
                : (week.estimatedTon ?? 0),
        ),
    );
    return Math.max(2, Math.ceil(maximumValue / 2) * 2);
});
function valueAt(index: number): number {
    const week = periods.value[index];
    if (!week) return 0;
    return measure.value === 'batches'
        ? week.batchCount
        : (week.estimatedTon ?? 0);
}
function periodLabel(index: number): string {
    const week = periods.value[index];
    if (!week) return '';
    const weight =
        week.estimatedTon === null
            ? 'bobot belum tercatat'
            : `${chartNumber(week.estimatedTon)} ton estimasi yang tercatat`;
    return `${chartRange(week.start, week.end)}: ${week.batchCount} batch, ${chartNumber(week.areaHa)} hektare, ${weight}${week.missingWeightCount ? `; ${week.missingWeightCount} batch belum memiliki estimasi bobot` : ''}`;
}
watch(
    () => props.forecast,
    () => {
        const upcomingIndex = periods.value.findIndex(
            (period) => period.batchCount > 0,
        );
        selected.value = upcomingIndex >= 0 ? upcomingIndex : null;
        if (!hasWeight.value) measure.value = 'batches';
    },
    { immediate: true },
);
</script>

<template>
    <OwnerChartCard
        id="owner-harvest-forecast"
        title="Proyeksi panen"
        :eyebrow="`Proyeksi · ${forecast.weeks.length} minggu`"
        description="Jadwal mendatang berdasarkan tanggal estimasi panen yang dicatat."
    >
        <p class="text-xs leading-5 text-muted-foreground">
            {{ chartRange(forecast.start, forecast.end) }}
        </p>
        <div
            v-if="hasWeight"
            class="mt-3 inline-flex rounded-lg border border-border bg-muted/50 p-1"
            role="group"
            aria-label="Ukuran grafik proyeksi panen"
        >
            <button
                type="button"
                class="min-h-11 rounded-md px-3 text-xs font-medium focus-visible:outline-2 focus-visible:outline-ring"
                :class="
                    measure === 'batches'
                        ? 'bg-card text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
                :aria-pressed="measure === 'batches'"
                @click="measure = 'batches'"
            >
                Jumlah batch
            </button>
            <button
                type="button"
                class="min-h-11 rounded-md px-3 text-xs font-medium focus-visible:outline-2 focus-visible:outline-ring"
                :class="
                    measure === 'weight'
                        ? 'bg-card text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
                :aria-pressed="measure === 'weight'"
                @click="measure = 'weight'"
            >
                Bobot tercatat
            </button>
        </div>
        <p
            v-if="!batchTotal"
            class="mt-5 rounded-xl border border-dashed border-border bg-muted/40 px-4 py-8 text-center text-sm leading-6 text-muted-foreground"
            role="status"
        >
            Belum ada batch dengan estimasi panen pada rentang ini.
        </p>
        <template v-else>
            <p
                class="mt-4 text-[10px] text-muted-foreground"
                aria-hidden="true"
            >
                {{ measure === 'batches' ? 'Batch' : 'Ton estimasi tercatat' }}
            </p>
            <div class="mt-2 flex gap-2">
                <div
                    class="flex h-40 w-5 shrink-0 flex-col justify-between text-right text-[10px] text-muted-foreground"
                    aria-hidden="true"
                >
                    <span>{{ chartNumber(maximum) }}</span>
                    <span>{{ chartNumber(maximum / 2) }}</span>
                    <span>0</span>
                </div>
                <div
                    class="min-w-0 flex-1"
                    role="group"
                    aria-label="Grafik proyeksi panen per periode tiga minggu"
                >
                    <div
                        class="grid gap-1.5"
                        :style="{
                            gridTemplateColumns: `repeat(${periods.length}, minmax(44px, 1fr))`,
                        }"
                    >
                        <button
                            v-for="(week, index) in periods"
                            :key="week.start"
                            type="button"
                            class="group min-w-11 rounded-md text-center focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            :aria-pressed="selected === index"
                            :aria-label="periodLabel(index)"
                            @click="selected = index"
                        >
                            <span
                                class="relative flex h-40 items-end justify-center border-b border-border px-2"
                                aria-hidden="true"
                            >
                                <span
                                    class="absolute inset-x-0 top-0 border-t border-dashed border-border"
                                />
                                <span
                                    class="absolute inset-x-0 top-1/2 border-t border-dashed border-border"
                                />
                                <span
                                    v-if="
                                        measure === 'weight' &&
                                        week.batchCount > 0 &&
                                        week.estimatedTon === null
                                    "
                                    class="z-1 mb-2 flex size-7 items-center justify-center rounded-md border border-dashed border-foreground/40 bg-muted text-xs font-semibold text-muted-foreground"
                                    >?</span
                                >
                                <span
                                    v-else-if="valueAt(index) > 0"
                                    class="relative z-1 w-full max-w-7 rounded-t-md border border-secondary/70 bg-primary transition-opacity group-hover:opacity-80 dark:border-primary"
                                    :class="
                                        measure === 'weight' &&
                                        week.missingWeightCount > 0
                                            ? 'border border-dashed border-foreground/60 bg-primary/60'
                                            : ''
                                    "
                                    :style="{
                                        height: `${(valueAt(index) / maximum) * 100}%`,
                                        minHeight: '3px',
                                    }"
                                >
                                    <span
                                        v-if="
                                            measure === 'weight' &&
                                            week.missingWeightCount > 0
                                        "
                                        class="absolute inset-x-0 -top-5 text-xs text-foreground"
                                        >*</span
                                    >
                                </span>
                                <span
                                    v-else
                                    class="z-1 h-0.5 w-full max-w-7 bg-border"
                                />
                            </span>
                            <span
                                class="mt-2 block min-h-8 text-[10px] leading-4 text-muted-foreground"
                                :class="
                                    selected === index
                                        ? 'font-semibold text-foreground'
                                        : ''
                                "
                                >{{ week.label }}</span
                            >
                        </button>
                    </div>
                </div>
            </div>
            <p class="mt-2 text-[10px] leading-4 text-muted-foreground">
                Kolom diringkas per 3 minggu; rincian mingguan di bawah.
            </p>
            <p
                class="mt-3 min-h-10 rounded-lg bg-muted/50 px-3 py-2 text-xs leading-5 text-muted-foreground"
                aria-live="polite"
            >
                <template v-if="selectedPeriod">
                    {{ chartRange(selectedPeriod.start, selectedPeriod.end) }} ·
                    {{ selectedPeriod.batchCount }} batch,
                    {{ chartNumber(selectedPeriod.areaHa) }} ha.
                    <template v-if="measure === 'weight'">
                        {{
                            selectedPeriod.estimatedTon === null
                                ? 'Bobot belum tercatat.'
                                : `${chartNumber(selectedPeriod.estimatedTon)} ton estimasi tercatat.`
                        }}
                        <span v-if="selectedPeriod.missingWeightCount">
                            {{ selectedPeriod.missingWeightCount }} batch belum
                            memiliki estimasi bobot.
                        </span>
                    </template>
                </template>
                <template v-else>
                    Ketuk batang untuk membaca jadwal dan luas panennya.
                </template>
            </p>
            <p
                v-if="measure === 'weight' && missingWeights"
                class="mt-2 text-xs leading-5 text-muted-foreground"
            >
                * Bobot sebagian batch belum tercatat. Tanda ? berarti bobot
                belum tersedia, bukan nol.
            </p>
        </template>

        <details class="mt-1 text-xs">
            <summary
                class="min-h-11 cursor-pointer content-center rounded-md font-medium text-secondary focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
            >
                Lihat data proyeksi
            </summary>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs leading-5">
                    <caption class="sr-only">
                        Proyeksi panen mingguan berdasarkan estimasi tersimpan
                    </caption>
                    <thead class="text-muted-foreground">
                        <tr class="border-b border-border">
                            <th scope="col" class="py-2 pr-2 font-medium">
                                Minggu
                            </th>
                            <th scope="col" class="px-2 py-2 font-medium">
                                Batch
                            </th>
                            <th scope="col" class="px-2 py-2 font-medium">
                                Ha
                            </th>
                            <th scope="col" class="py-2 pl-2 font-medium">
                                Ton tercatat
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="week in forecast.weeks"
                            :key="week.start"
                            class="border-b border-border/60"
                        >
                            <th scope="row" class="py-2 pr-2 font-normal">
                                {{ chartRange(week.start, week.end) }}
                            </th>
                            <td class="px-2 py-2 tabular-nums">
                                {{ week.batchCount }}
                            </td>
                            <td class="px-2 py-2 tabular-nums">
                                {{ chartNumber(week.areaHa) }}
                            </td>
                            <td class="py-2 pl-2 tabular-nums">
                                {{
                                    week.estimatedTon === null
                                        ? 'Belum ada'
                                        : chartNumber(week.estimatedTon)
                                }}{{ week.missingWeightCount ? '*' : '' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                v-if="missingWeights"
                class="mt-2 leading-5 text-muted-foreground"
            >
                * Sebagian estimasi bobot belum tercatat. Jumlah ton hanya
                menjumlahkan bobot yang tersedia.
            </p>
        </details>
        <template #footer>
            <p>
                Estimasi jadwal dan bobot dapat berubah; ini bukan realisasi
                panen.
            </p>
            <p
                v-if="forecast.undatedCount || forecast.overdueCount"
                class="mt-1"
            >
                <span v-if="forecast.undatedCount">
                    {{ forecast.undatedCount }} batch belum memiliki tanggal
                    estimasi.
                </span>
                <span v-if="forecast.overdueCount">
                    {{ forecast.overdueCount }} batch melewati tanggal estimasi.
                </span>
            </p>
        </template>
    </OwnerChartCard>
</template>
