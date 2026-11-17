<script setup lang="ts">
import {
    BookOpen,
    CalendarCheck,
    ClipboardList,
    LandPlot,
    TriangleAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import type { LogbookSummary } from '@/types/logbook';

const props = defineProps<{ summary: LogbookSummary; directory?: boolean }>();

const number = new Intl.NumberFormat('id-ID');

const primaryMetric = computed(() =>
    props.directory
        ? {
              label: 'Kebun',
              value: props.summary.farms,
              hint: 'Kebun dalam cakupan akses',
              icon: LandPlot,
          }
        : {
              label: 'Kegiatan tercatat',
              value: props.summary.activities,
              hint: 'Total kegiatan pada kebun ini',
              icon: ClipboardList,
          },
);

const maxReportBar = computed(() =>
    Math.max(
        1,
        props.summary.reports,
        props.summary.today,
        props.summary.obstacles,
    ),
);

const obstacleRatio = computed(() =>
    props.summary.reports > 0
        ? Math.min(1, props.summary.obstacles / props.summary.reports)
        : 0,
);
const obstaclePercent = computed(() => Math.round(obstacleRatio.value * 100));
</script>

<template>
    <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4"
        aria-label="Ringkasan logbook"
    >
        <!-- 1. Kebun / Kegiatan tercatat -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">{{ primaryMetric.label }}</h2>
                <component
                    :is="primaryMetric.icon"
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(primaryMetric.value) }}
                </p>
                <span
                    v-if="directory && summary.activities > 0"
                    class="shrink-0 rounded-full bg-accent px-2.5 py-1 text-xs font-semibold tabular-nums text-accent-foreground"
                >
                    {{ number.format(summary.activities) }} kegiatan
                </span>
            </div>

            <p class="text-xs text-muted-foreground">
                {{ primaryMetric.hint }}
            </p>
        </article>

        <!-- 2. Total logbook + mini comparison bars -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Total logbook</h2>
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
                            height: `${Math.max(5, (summary.reports / maxReportBar) * 36)}px`,
                        }"
                    />
                    <span
                        class="w-3.5 rounded-t bg-[var(--admin-chart-secondary)] transition-[height]"
                        :style="{
                            height: `${Math.max(5, (summary.today / maxReportBar) * 36)}px`,
                        }"
                    />
                    <span
                        class="w-3.5 rounded-t bg-[var(--admin-chart-tertiary)] transition-[height]"
                        :style="{
                            height: `${Math.max(5, (summary.obstacles / maxReportBar) * 36)}px`,
                        }"
                    />
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                Laporan kerja harian tersimpan
            </p>
        </article>

        <!-- 3. Logbook hari ini + status pill -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Logbook hari ini</h2>
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
                    {{ summary.today > 0 ? 'Sudah terisi' : 'Belum ada' }}
                </span>
            </div>

            <p class="text-xs text-muted-foreground">
                Pencatatan hari ini (WIB)
            </p>
        </article>

        <!-- 4. Kendala tercatat + warning highlight & ratio -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Kendala tercatat</h2>
                <TriangleAlert
                    class="size-4 shrink-0"
                    :class="
                        summary.obstacles > 0
                            ? 'text-warning'
                            : 'text-muted-foreground'
                    "
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p
                    class="admin-metric-value tracking-tight"
                    :class="{ 'text-warning': summary.obstacles > 0 }"
                >
                    {{ number.format(summary.obstacles) }}
                </p>
                <span
                    class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold tabular-nums"
                    :class="
                        summary.obstacles > 0
                            ? 'bg-warning/15 text-warning'
                            : 'bg-success/10 text-success'
                    "
                >
                    {{
                        summary.obstacles > 0
                            ? `${obstaclePercent}% dari logbook`
                            : 'Aman'
                    }}
                </span>
            </div>

            <p class="text-xs text-muted-foreground">
                Kendala lapangan dilaporkan
            </p>
        </article>
    </section>
</template>
