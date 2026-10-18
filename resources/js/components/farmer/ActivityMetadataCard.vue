<script setup lang="ts">
import {
    Activity,
    CalendarDays,
    Clock3,
    MapPin,
    Sprout,
    UserRound,
} from '@lucide/vue';
import type { ActivityLog } from '@/data/activityLogs';

const props = defineProps<{
    activity: ActivityLog;
}>();

function activityTone(activity: ActivityLog): string {
    if (activity.report.kind === 'pelaporan-insiden')
        return 'bg-error/10 text-error';
    if (activity.report.kind === 'panen')
        return 'bg-warning/15 text-foreground';
    return 'bg-surface text-secondary';
}
</script>

<template>
    <section
        aria-labelledby="activity-metadata-heading"
        class="overflow-hidden rounded-2xl border border-border bg-card"
    >
        <div class="bg-muted/55 p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold',
                            activityTone(activity),
                        ]"
                    >
                        <Activity :size="13" aria-hidden="true" />
                        {{ activity.typeLabel }}
                    </span>
                    <h2
                        id="activity-metadata-heading"
                        class="mt-2 text-[20px] leading-6 font-semibold text-foreground"
                    >
                        {{ activity.kebunName }}
                    </h2>
                    <p
                        class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <MapPin
                            :size="14"
                            class="shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        {{ activity.city }}
                        <span aria-hidden="true">·</span>
                        <Sprout
                            :size="14"
                            class="shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        {{ activity.seedType }}
                    </p>
                </div>

                <span
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-primary/35 bg-card px-2.5 py-2 text-xs font-semibold text-secondary"
                    title="Hari setelah tanam"
                >
                    <Clock3 :size="15" :stroke-width="1.8" aria-hidden="true" />
                    HST {{ activity.daysAfterPlanting }}
                </span>
            </div>
        </div>

        <dl
            class="grid grid-cols-2 divide-x divide-border border-t border-border"
        >
            <div class="min-w-0 p-3.5">
                <dt
                    class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                >
                    <CalendarDays
                        :size="14"
                        class="text-primary"
                        aria-hidden="true"
                    />
                    Waktu aktivitas
                </dt>
                <dd class="mt-1.5 text-sm font-semibold text-foreground">
                    {{ activity.timestamp }}
                </dd>
            </div>
            <div class="min-w-0 p-3.5">
                <dt
                    class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                >
                    <UserRound
                        :size="14"
                        class="text-primary"
                        aria-hidden="true"
                    />
                    Petugas lapangan
                </dt>
                <dd
                    class="mt-1.5 truncate text-sm font-semibold text-foreground"
                >
                    {{ activity.officerName }}
                </dd>
            </div>
        </dl>

        <!-- <div class="border-t border-border px-4 py-3">
            <p class="text-xs text-muted-foreground">Konteks kebun</p>
            <p class="mt-1 text-sm font-medium text-foreground">
                {{ activity.seedType }} · dicatat oleh
                {{ activity.officerName }}
            </p>
        </div> -->
    </section>
</template>
