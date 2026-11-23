<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    Check,
    Circle,
    Clock3,
    FileCheck2,
    MapPin,
} from '@lucide/vue';
import { computed } from 'vue';
import TaskStatusBadges from '@/components/petugas/TaskStatusBadges.vue';
import { withPetugasBack } from '@/lib/petugasBackLink';
import { formatReportDate } from '@/lib/petugasReport';
import type { PetugasHomeTask } from '@/types/petugas-home';

const props = defineProps<{ task: PetugasHomeTask }>();
const urgency = computed(
    () =>
        props.task.urgency ??
        (props.task.status === 'completed' ? 'completed' : 'today'),
);
const status = {
    pending: {
        label: 'Terjadwal',
        icon: Circle,
        color: 'bg-muted text-muted-foreground',
    },
    in_progress: {
        label: 'Dikerjakan',
        icon: Clock3,
        color: 'bg-warning/20 text-foreground',
    },
    submitted: {
        label: 'Dilaporkan',
        icon: FileCheck2,
        color: 'bg-info/20 text-foreground',
    },
    completed: {
        label: 'Selesai',
        icon: Check,
        color: 'bg-success/10 text-secondary dark:text-primary',
    },
    skipped: {
        label: 'Dilewati',
        icon: Circle,
        color: 'bg-muted text-muted-foreground',
    },
    cancelled: {
        label: 'Dibatalkan',
        icon: Circle,
        color: 'bg-muted text-muted-foreground',
    },
};
</script>

<template>
    <Link
        :href="
            withPetugasBack(
                `/petugas/tasks/${encodeURIComponent(task.id)}`,
                '/petugas',
            )
        "
        :aria-label="`Lihat aktivitas untuk ${task.name}`"
        class="petugas-card group flex min-h-20 items-start gap-3 p-4 transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
    >
        <span
            class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full"
            :class="status[task.status].color"
        >
            <component
                :is="status[task.status].icon"
                class="size-4"
                aria-hidden="true"
            />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm leading-5 font-semibold break-words">{{
                task.name
            }}</span>
            <span
                class="mt-1.5 flex items-start gap-1 text-xs leading-5 text-muted-foreground"
            >
                <MapPin class="mt-1 size-3 shrink-0" aria-hidden="true" />
                <span
                    >{{ task.block_name ?? 'Blok tidak tersedia'
                    }}<template v-if="task.batch_number !== null">
                        · Subblok {{ task.batch_number }}</template
                    ><template v-if="task.subblock_label">
                        · {{ task.subblock_label }}</template
                    ></span
                >
            </span>
            <span
                v-if="task.scheduled_date || task.hst !== null"
                class="mt-1 flex items-center gap-1 text-xs leading-5 text-muted-foreground"
            >
                <CalendarDays class="size-3 shrink-0" aria-hidden="true" />
                <span>
                    <template v-if="task.scheduled_date">{{
                        formatReportDate(task.scheduled_date)
                    }}</template>
                    <template v-if="task.scheduled_date && task.hst !== null">
                        ·
                    </template>
                    <template v-if="task.hst !== null"
                        >{{ task.hst }} HST</template
                    >
                </span>
            </span>
            <span class="mt-2 flex flex-wrap items-center gap-2">
                <TaskStatusBadges :status="task.status" :urgency="urgency" />
            </span>
        </span>
        <ArrowRight
            class="size-4 shrink-0 self-center text-muted-foreground group-hover:text-secondary dark:group-hover:text-primary"
            aria-hidden="true"
        />
    </Link>
</template>
