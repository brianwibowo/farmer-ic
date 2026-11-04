<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, CalendarRange } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { OwnerPlantingTimeline } from '@/types/owner-dashboard';
import { ownerStatus } from '@/types/owner';
import OwnerChartCard from './OwnerChartCard.vue';
import { chartDate, chartNumber } from './chartFormat';

const props = defineProps<{ timeline: OwnerPlantingTimeline; asOf: string }>();
const selectedId = ref(props.timeline.rows[0]?.id ?? '');
const expandedRows = ref(false);
const visibleRows = computed(() =>
    expandedRows.value ? props.timeline.rows : props.timeline.rows.slice(0, 3),
);
function toggleRows(): void {
    expandedRows.value = !expandedRows.value;
    if (!visibleRows.value.some((row) => row.id === selectedId.value))
        selectedId.value = visibleRows.value[0]?.id ?? '';
}
watch(
    () => props.timeline,
    (timeline) => {
        expandedRows.value = false;
        if (!timeline.rows.some((row) => row.id === selectedId.value)) {
            selectedId.value = timeline.rows[0]?.id ?? '';
        }
    },
);
const selected = computed(() =>
    props.timeline.rows.find((row) => row.id === selectedId.value),
);
const timestamp = (value: string): number =>
    Date.parse(`${value.slice(0, 10)}T00:00:00Z`);
const duration = computed(() =>
    Math.max(
        86400000,
        timestamp(props.timeline.end) - timestamp(props.timeline.start),
    ),
);
const position = (value: string): number =>
    Math.max(
        0,
        Math.min(
            100,
            ((timestamp(value) - timestamp(props.timeline.start)) /
                duration.value) *
                100,
        ),
    );
const todayPosition = computed(() => {
    const today = timestamp(props.asOf);
    return today >= timestamp(props.timeline.start) &&
        today <= timestamp(props.timeline.end)
        ? position(props.asOf)
        : null;
});
const ticks = computed(() =>
    [0, 1, 2, 3, 4].map((step) =>
        new Date(timestamp(props.timeline.start) + (duration.value * step) / 4)
            .toISOString()
            .slice(0, 10),
    ),
);
</script>

<template>
    <OwnerChartCard
        id="owner-timeline"
        eyebrow="Jadwal & arah panen"
        title="Perjalanan setiap batch"
        description="Posisi tanam dan estimasi panen pada satu garis waktu."
    >
        <div
            v-if="!timeline.rows.length"
            class="rounded-xl bg-muted p-4 text-sm leading-6 text-muted-foreground"
            role="status"
        >
            <CalendarRange class="mb-2 size-6" aria-hidden="true" />Belum ada
            batch dengan tanggal mulai dan estimasi panen yang lengkap.
        </div>
        <template v-else>
            <div
                class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground"
                aria-label="Keterangan jadwal"
            >
                <span class="inline-flex items-center gap-1.5"
                    ><span
                        class="h-2.5 w-5 rounded-sm bg-secondary dark:bg-primary"
                        aria-hidden="true"
                    />Mulai aktual</span
                >
                <span class="inline-flex items-center gap-1.5"
                    ><span
                        class="h-2.5 w-5 rounded-sm border border-dashed border-foreground bg-muted"
                        aria-hidden="true"
                    />Mulai rencana</span
                >
                <span class="inline-flex items-center gap-1.5"
                    ><span
                        class="h-3 border-l-2 border-foreground"
                        aria-hidden="true"
                    />Hari ini</span
                >
            </div>
            <div
                class="mt-5 flex justify-between gap-1 text-[10px] text-muted-foreground min-[390px]:text-xs"
                aria-hidden="true"
            >
                <span v-for="(tick, index) in ticks" :key="index">{{
                    chartDate(tick)
                }}</span>
            </div>
            <ul class="mt-2 space-y-1" aria-label="Jadwal batch tanam">
                <li v-for="row in visibleRows" :key="row.id">
                    <button
                        type="button"
                        class="group w-full rounded-xl px-2 py-3 text-left transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        :class="selectedId === row.id ? 'bg-muted/70' : ''"
                        :aria-pressed="selectedId === row.id"
                        :aria-label="`${row.label}, ${row.farm}. ${row.startKind === 'actual' ? 'Mulai aktual' : 'Mulai rencana'} ${chartDate(row.startDate, true)}, estimasi panen ${chartDate(row.endDate, true)}. ${ownerStatus[row.status] ?? row.status}. Pilih untuk rincian.`"
                        @click="selectedId = row.id"
                    >
                        <span
                            class="flex flex-wrap items-start justify-between gap-1.5 text-xs"
                            ><span class="min-w-0 font-semibold"
                                >{{ row.label
                                }}<span
                                    v-if="row.block"
                                    class="font-normal text-muted-foreground"
                                >
                                    · {{ row.block }}</span
                                ></span
                            ><span class="text-muted-foreground">{{
                                ownerStatus[row.status] ?? row.status
                            }}</span></span
                        >
                        <span class="mt-1 block text-xs text-muted-foreground"
                            >{{ row.farm
                            }}<template v-if="row.commodity">
                                · {{ row.commodity }}</template
                            ></span
                        >
                        <span
                            class="owner-timeline-track relative mt-2 block h-8 rounded-md bg-card"
                            aria-hidden="true"
                        >
                            <span
                                class="absolute top-2 h-4 min-w-1 rounded-sm border-r-2 border-dashed border-foreground/60"
                                :class="
                                    row.startKind === 'actual'
                                        ? 'bg-secondary dark:bg-primary'
                                        : 'border border-dashed border-foreground bg-muted-foreground/25'
                                "
                                :style="{
                                    left: `${position(row.startDate)}%`,
                                    width: `${Math.max(0.5, position(row.endDate) - position(row.startDate))}%`,
                                }"
                            />
                            <span
                                v-if="todayPosition !== null"
                                class="absolute inset-y-0 border-l-2 border-foreground"
                                :style="{ left: `${todayPosition}%` }"
                            />
                        </span>
                        <span
                            class="mt-1 flex flex-wrap justify-between gap-x-3 gap-y-1 text-[11px] text-muted-foreground"
                            ><span
                                >{{
                                    row.startKind === 'actual'
                                        ? 'Tanam'
                                        : 'Rencana'
                                }}
                                {{ chartDate(row.startDate) }}</span
                            ><span
                                >Estimasi {{ chartDate(row.endDate) }}</span
                            ></span
                        >
                    </button>
                </li>
            </ul>
            <button
                v-if="timeline.rows.length > 3"
                type="button"
                class="mt-2 min-h-11 w-full rounded-lg border border-border px-3 text-xs font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                :aria-expanded="expandedRows"
                @click="toggleRows()"
            >
                {{
                    expandedRows
                        ? 'Ringkas jadwal'
                        : `Lihat ${timeline.rows.length - 3} jadwal lainnya`
                }}
            </button>
            <div
                v-if="selected"
                class="mt-4 rounded-xl border border-border p-3"
                aria-live="polite"
            >
                <p class="text-sm font-semibold">
                    {{ selected.label }} ·
                    {{ chartNumber(selected.areaHa, 2) }} ha
                </p>
                <details class="mt-1">
                    <summary
                        class="min-h-11 cursor-pointer content-center rounded-md text-xs font-medium text-secondary focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                    >
                        Lihat tahapan batch
                    </summary>
                    <ul
                        v-if="selected.stages.length"
                        class="mt-2 grid grid-cols-2 gap-x-3 gap-y-2 text-xs"
                    >
                        <li
                            v-for="stage in selected.stages"
                            :key="stage.code"
                            class="min-w-0"
                        >
                            <span class="block font-medium">{{
                                stage.label
                            }}</span
                            ><span class="text-muted-foreground">{{
                                ownerStatus[stage.status] ?? stage.status
                            }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-xs text-muted-foreground">
                        Tahap pekerjaan belum dicatat.
                    </p>
                </details>
                <Link
                    :href="selected.href"
                    class="mt-2 inline-flex min-h-11 items-center gap-1.5 rounded-md text-xs font-semibold text-secondary focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                    >Buka daftar kebun<ArrowUpRight
                        class="size-4"
                        aria-hidden="true"
                /></Link>
            </div>
        </template>
        <template #footer>
            <p class="text-xs leading-5 text-muted-foreground">
                Ujung kanan adalah estimasi panen, bukan waktu selesai aktual.
                Panjang batang menunjukkan rentang waktu, bukan persentase
                pekerjaan selesai.
            </p>
            <p
                v-if="timeline.undatedCount"
                class="mt-2 text-xs leading-5 text-muted-foreground"
            >
                {{ timeline.undatedCount }} batch belum memiliki rentang tanggal
                yang lengkap.
            </p>
            <p
                v-if="timeline.hasMore"
                class="mt-2 text-xs leading-5 text-muted-foreground"
            >
                Menampilkan {{ visibleRows.length }} dari
                {{ timeline.total }} batch pada pratinjau ini.
            </p>
        </template>
    </OwnerChartCard>
</template>

<style scoped>
.owner-timeline-track {
    background-image: linear-gradient(
        to right,
        var(--border) 1px,
        transparent 1px
    );
    background-size: 25% 100%;
}
</style>
