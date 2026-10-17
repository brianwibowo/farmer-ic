<script setup lang="ts">
import { CalendarClock, MapPin, Sprout, Timer, User } from '@lucide/vue';
import type { ActivityLog } from '@/data/activityLogs';

defineProps<{
    activity: ActivityLog;
}>();
</script>

<template>
    <section
        aria-labelledby="activity-metadata-heading"
        class="rounded-xl border border-border bg-muted/50 p-4"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <span
                    class="inline-flex items-center rounded-md bg-surface px-2.5 py-0.5 text-xs font-semibold text-surface-foreground"
                >
                    {{ activity.typeLabel }}
                </span>
                <h2
                    id="activity-metadata-heading"
                    class="mt-1.5 truncate text-[16px] leading-5 font-semibold text-foreground"
                >
                    {{ activity.kebunName }}
                </h2>
            </div>

            <span
                class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-primary/40 bg-card px-2.5 py-1 text-xs font-semibold text-secondary dark:text-primary"
                title="Hari sejak tanam (otomatis)"
            >
                <Timer :size="14" :stroke-width="1.8" aria-hidden="true" />
                <span>HST {{ activity.daysAfterPlanting }} Hari</span>
            </span>
        </div>

        <div
            class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
        >
            <span class="inline-flex items-center gap-1">
                <MapPin
                    :size="14"
                    :stroke-width="1.8"
                    class="shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span>{{ activity.city }}</span>
            </span>
            <span class="inline-flex items-center gap-1">
                <Sprout
                    :size="14"
                    :stroke-width="1.8"
                    class="shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span>Komoditas {{ activity.seedType }}</span>
            </span>
        </div>

        <dl
            class="mt-3.5 grid grid-cols-1 gap-2.5 border-t border-border pt-3 text-[14px] sm:grid-cols-2"
        >
            <div class="flex items-center gap-2.5">
                <CalendarClock
                    :size="18"
                    :stroke-width="1.8"
                    class="shrink-0 text-primary"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <dt class="sr-only">Waktu &amp; Hari Tanam</dt>
                    <dd class="font-medium text-foreground">
                        {{ activity.timestamp }}
                    </dd>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <User
                    :size="18"
                    :stroke-width="1.8"
                    class="shrink-0 text-primary"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <dt class="sr-only">Nama Petugas</dt>
                    <dd class="font-medium text-foreground">
                        {{ activity.officerName }}
                    </dd>
                </div>
            </div>
        </dl>
    </section>
</template>
