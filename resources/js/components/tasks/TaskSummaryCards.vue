<script setup lang="ts">
import { AlertTriangle, CheckCircle2, CircleDot, ListChecks } from '@lucide/vue';
import { computed } from 'vue';
import type { AdminTaskSummary } from '@/types/tasks';

const props = defineProps<{
    summary: AdminTaskSummary;
}>();

const number = new Intl.NumberFormat('id-ID');

const completionRatio = computed(() =>
    props.summary.total > 0
        ? Math.min(1, props.summary.completed / props.summary.total)
        : 0,
);

const completionPercent = computed(() =>
    Math.round(completionRatio.value * 100),
);

const gaugeStyle = computed(() => ({
    background: `conic-gradient(from -90deg at 50% 100%, var(--primary) 0 ${completionRatio.value * 180}deg, var(--admin-chart-tertiary) ${completionRatio.value * 180}deg 180deg, transparent 180deg)`,
}));

const maxBar = computed(() =>
    Math.max(1, props.summary.total, props.summary.open, props.summary.completed),
);

const openShare = computed(() => {
    if (props.summary.open <= 0) {
        return { overdue: 0, today: 0, scheduled: 0 };
    }
    const overdue = Math.min(100, (props.summary.overdue / props.summary.open) * 100);
    const today = Math.min(
        100 - overdue,
        (props.summary.today / props.summary.open) * 100,
    );
    const scheduled = Math.max(0, 100 - overdue - today);
    return { overdue, today, scheduled };
});
</script>

<template>
    <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4"
        aria-label="Ringkasan tugas"
    >
        <!-- 1. Total tugas + mini comparison bars -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Total tugas</h2>
                <ListChecks
                    class="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(summary.total) }}
                </p>
                <div
                    class="flex h-9 w-[54px] shrink-0 items-end gap-1.5"
                    aria-hidden="true"
                >
                    <span
                        class="w-3.5 rounded-t bg-primary transition-[height]"
                        :style="{
                            height: `${Math.max(5, (summary.total / maxBar) * 36)}px`,
                        }"
                    />
                    <span
                        class="w-3.5 rounded-t bg-[var(--admin-chart-secondary)] transition-[height]"
                        :style="{
                            height: `${Math.max(5, (summary.open / maxBar) * 36)}px`,
                        }"
                    />
                    <span
                        class="w-3.5 rounded-t bg-[var(--admin-chart-tertiary)] transition-[height]"
                        :style="{
                            height: `${Math.max(5, (summary.completed / maxBar) * 36)}px`,
                        }"
                    />
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                {{ number.format(summary.open) }} terbuka ·
                {{ number.format(summary.completed) }} ditutup
            </p>
        </article>

        <!-- 2. Tugas terbuka + breakdown terlambat & hari ini -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Tugas terbuka</h2>
                <CircleDot
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(summary.open) }}
                </p>
                <div class="flex flex-wrap items-center justify-end gap-1.5">
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-error/10 px-2.5 py-1 text-xs font-semibold tabular-nums text-error"
                    >
                        <AlertTriangle class="size-3" aria-hidden="true" />
                        {{ number.format(summary.overdue) }} terlambat
                    </span>
                    <span
                        class="inline-flex items-center rounded-full bg-warning/15 px-2.5 py-1 text-xs font-semibold tabular-nums text-warning"
                    >
                        {{ number.format(summary.today) }} hari ini
                    </span>
                </div>
            </div>

            <div class="space-y-1.5">
                <div
                    class="flex h-1.5 w-full overflow-hidden rounded-full bg-muted"
                    aria-hidden="true"
                >
                    <span
                        v-if="openShare.overdue > 0"
                        class="bg-error"
                        :style="{ width: `${openShare.overdue}%` }"
                    />
                    <span
                        v-if="openShare.today > 0"
                        class="bg-warning"
                        :style="{ width: `${openShare.today}%` }"
                    />
                    <span
                        v-if="openShare.scheduled > 0"
                        class="bg-primary/60"
                        :style="{ width: `${openShare.scheduled}%` }"
                    />
                </div>
                <p class="text-xs text-muted-foreground">
                    Proporsi urgensi tugas yang masih berjalan
                </p>
            </div>
        </article>

        <!-- 3. Selesai / tidak dikerjakan + completion gauge -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Selesai / tidak dikerjakan</h2>
                <CheckCircle2
                    class="size-4 shrink-0 text-success"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(summary.completed) }}
                </p>
                <div
                    class="relative h-[34px] w-[62px] shrink-0 rounded-t-full"
                    :style="gaugeStyle"
                    aria-hidden="true"
                >
                    <span
                        class="absolute inset-x-[5px] top-[5px] bottom-0 rounded-t-full bg-card"
                    />
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                <strong class="font-semibold text-primary"
                    >{{ completionPercent }}%</strong
                >
                dari total tugas telah ditutup
            </p>
        </article>
    </section>
</template>
