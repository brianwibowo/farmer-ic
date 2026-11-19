<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronRight,
    ClipboardList,
    MapPin,
    Sprout,
    Wheat,
} from '@lucide/vue';
import TaskStatusBadges from '@/components/petugas/TaskStatusBadges.vue';
import { formatReportDate } from '@/lib/petugasReport';
import type { PetugasTask } from '@/types/petugas-task';
import { sopTaskCategoryLabel } from '@/types/sop';

defineProps<{ task: PetugasTask }>();
</script>

<template>
    <article class="petugas-card overflow-hidden">
        <Link
            :href="`/petugas/tasks/${task.id}`"
            class="group block rounded-[inherit] p-4 transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
        >
            <div class="flex items-start gap-3">
                <span
                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <component
                        :is="
                            task.activity_category === 'harvest'
                                ? Wheat
                                : Sprout
                        "
                        class="size-5"
                        aria-hidden="true"
                    />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-primary">
                                {{ sopTaskCategoryLabel(task) }}
                            </p>
                            <h3
                                class="mt-1 text-base leading-5 font-semibold break-words"
                            >
                                {{ task.name }}
                            </h3>
                        </div>
                        <ChevronRight
                            class="mt-1 size-5 shrink-0 text-muted-foreground group-hover:text-primary"
                            aria-hidden="true"
                        />
                    </div>
                    <p
                        class="mt-2 flex items-start gap-1.5 text-xs leading-5 text-muted-foreground"
                    >
                        <MapPin
                            class="mt-0.5 size-3.5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 break-words">
                            {{ task.subblok.block.name }} ·
                            {{
                                task.subblok.label ??
                                `Subblok ${task.subblok.number}`
                            }}
                        </span>
                    </p>
                    <p
                        class="mt-1 flex items-start gap-1.5 text-xs leading-5 text-muted-foreground"
                    >
                        <CalendarDays
                            class="mt-0.5 size-3.5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span>
                            {{
                                task.scheduled_date
                                    ? formatReportDate(task.scheduled_date)
                                    : 'Belum dijadwalkan'
                            }}
                            · Target HST {{ task.offset_days }}
                        </span>
                    </p>
                    <TaskStatusBadges
                        class="mt-3"
                        :status="task.status"
                        :urgency="task.urgency"
                        :closed="
                            ['harvested', 'cancelled'].includes(
                                task.subblok.status ?? '',
                            )
                        "
                    />
                    <p
                        v-if="
                            ['harvested', 'cancelled'].includes(
                                task.subblok.status ?? '',
                            )
                        "
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Subblok ditutup · hanya dapat dilihat
                    </p>
                </div>
            </div>
            <p
                v-if="task.linked_activities_count"
                class="mt-3 flex items-center gap-1.5 border-t border-border pt-3 text-xs font-medium text-muted-foreground"
            >
                <ClipboardList
                    class="size-3.5 text-primary"
                    aria-hidden="true"
                />
                {{ task.linked_activities_count }} laporan terkait
            </p>
        </Link>
    </article>
</template>
