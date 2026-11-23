<script setup lang="ts">
import {
    BookOpen,
    CalendarCheck,
    ClipboardList,
    TriangleAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import type { LogbookSummary } from '@/types/logbook';

const props = defineProps<{ summary: LogbookSummary; directory?: boolean }>();

const number = new Intl.NumberFormat('id-ID');

const primaryHint = computed(() =>
    props.directory
        ? 'Aktivitas lapangan dalam cakupan akses'
        : 'Total kegiatan pada kebun ini',
);

const maxComparisonBar = computed(() =>
    Math.max(
        1,
        props.summary.reports,
        props.summary.today,
        props.summary.incidents,
    ),
);

const incidentRatio = computed(() =>
    props.summary.activities > 0
        ? Math.min(1, props.summary.incidents / props.summary.activities)
        : 0,
);
const incidentPercent = computed(() => Math.round(incidentRatio.value * 100));
</script>

<template>
    <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4"
        aria-label="Ringkasan logbook"
    >
        <!-- 1. Aktivitas tercatat -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Aktivitas tercatat</h2>
                <ClipboardList
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(summary.activities) }}
                </p>
                <span
                    v-if="directory"
                    class="shrink-0 rounded-full bg-accent px-2.5 py-1 text-xs font-semibold text-accent-foreground tabular-nums"
                >
                    {{ number.format(summary.farms) }} kebun
                </span>
            </div>

            <p class="text-xs text-muted-foreground">
                {{ primaryHint }}
            </p>
        </article>

        <!-- 2. Laporan harian + mini comparison bars -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Laporan harian</h2>
                <BookOpen
                    class="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(summary.reports) }}
                </p>
                <div
                    class="flex h-9 w-[54px] shrink-0 items-end gap-1.5"
                    aria-hidden="true"
                >
                    <span
                        class="w-3.5 rounded-t bg-primary transition-[height]"
                        :style="{
                            height: `${Math.max(5, (summary.reports / maxComparisonBar) * 36)}px`,
                        }"
                    />
                    <span
                        class="w-3.5 rounded-t bg-[var(--admin-chart-secondary)] transition-[height]"
                        :style="{
                            height: `${Math.max(5, (summary.today / maxComparisonBar) * 36)}px`,
                        }"
                    />
                    <span
                        class="w-3.5 rounded-t bg-[var(--admin-chart-tertiary)] transition-[height]"
                        :style="{
                            height: `${Math.max(5, (summary.incidents / maxComparisonBar) * 36)}px`,
                        }"
                    />
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                Logbook kerja harian tersimpan
            </p>
        </article>

        <!-- 3. Aktivitas hari ini + status pill -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Aktivitas hari ini</h2>
                <CalendarCheck
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(summary.today) }}
                </p>
                <span
                    class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="
                        summary.today > 0
                            ? 'bg-accent text-accent-foreground'
                            : 'bg-muted text-muted-foreground'
                    "
                >
                    {{ summary.today > 0 ? 'Sudah ada' : 'Belum ada' }}
                </span>
            </div>

            <p class="text-xs text-muted-foreground">
                Pencatatan hari ini (WIB)
            </p>
        </article>

        <!-- 4. Insiden dilaporkan -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Insiden dilaporkan</h2>
                <TriangleAlert
                    class="size-4 shrink-0"
                    :class="
                        summary.incidents > 0
                            ? 'text-warning'
                            : 'text-muted-foreground'
                    "
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p
                    class="admin-metric-value tracking-tight"
                    :class="{ 'text-warning': summary.incidents > 0 }"
                >
                    {{ number.format(summary.incidents) }}
                </p>
                <span
                    class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold tabular-nums"
                    :class="
                        summary.incidents > 0
                            ? 'bg-warning/15 text-warning'
                            : 'bg-success/10 text-success'
                    "
                >
                    {{
                        summary.incidents > 0
                            ? `${incidentPercent}% dari aktivitas`
                            : 'Aman'
                    }}
                </span>
            </div>

            <p class="text-xs text-muted-foreground">
                Laporan insiden lapangan tercatat
            </p>
        </article>
    </section>
</template>
