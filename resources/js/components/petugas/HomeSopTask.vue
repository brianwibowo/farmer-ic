<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Circle,
    Clock3,
    FileCheck2,
    MapPin,
} from '@lucide/vue';
import type { PetugasHomeTask } from '@/types/petugas-home';

defineProps<{ task: PetugasHomeTask }>();
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
        :href="`/petugas/aktivitas?task_id=${encodeURIComponent(task.id)}`"
        :aria-label="`Lihat aktivitas untuk ${task.name}`"
        class="group flex min-h-20 items-start gap-3 rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/50 hover:bg-muted/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
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
                        · Batch {{ task.batch_number }}</template
                    ><template v-if="task.subblock_label">
                        · {{ task.subblock_label }}</template
                    ></span
                >
            </span>
            <span class="mt-2 flex flex-wrap items-center gap-2">
                <span
                    class="rounded-full px-2 py-1 text-[11px] font-medium"
                    :class="status[task.status].color"
                    >{{ status[task.status].label }}</span
                >
                <span
                    v-if="task.hst !== null"
                    class="text-[11px] text-muted-foreground"
                    >{{ task.hst }} HST</span
                >
            </span>
        </span>
        <ArrowRight
            class="size-4 shrink-0 self-center text-muted-foreground group-hover:text-secondary dark:group-hover:text-primary"
            aria-hidden="true"
        />
    </Link>
</template>
