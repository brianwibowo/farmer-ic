<script setup lang="ts">
import type {
    PetugasTaskStatus,
    PetugasTaskUrgency,
} from '@/types/petugas-task';

defineProps<{
    status: PetugasTaskStatus;
    urgency: PetugasTaskUrgency;
    closed?: boolean;
}>();

const statusLabels: Record<PetugasTaskStatus, string> = {
    pending: 'Terjadwal',
    in_progress: 'Dikerjakan',
    completed: 'Selesai',
    skipped: 'Dilewati',
};
const urgencyLabels: Record<
    Exclude<PetugasTaskUrgency, 'completed'>,
    string
> = {
    overdue: 'Terlambat',
    today: 'Hari ini',
    upcoming: 'Mendatang',
};
</script>

<template>
    <span class="flex flex-wrap items-center gap-1.5">
        <span
            v-if="!closed && urgency !== 'completed'"
            class="rounded-full px-2.5 py-1 text-[11px] leading-4 font-semibold"
            :class="
                urgency === 'overdue'
                    ? 'bg-warning/15 text-foreground'
                    : urgency === 'today'
                      ? 'bg-info/10 text-info'
                      : 'bg-muted text-muted-foreground'
            "
        >
            {{ urgencyLabels[urgency] }}
        </span>
        <span
            class="rounded-full px-2.5 py-1 text-[11px] leading-4 font-semibold"
            :class="
                status === 'completed'
                    ? 'bg-success/10 text-secondary dark:text-primary'
                    : 'bg-muted text-muted-foreground'
            "
        >
            {{ statusLabels[status] }}
        </span>
    </span>
</template>
