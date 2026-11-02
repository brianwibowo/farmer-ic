<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarDays, ClipboardList, MapPin, Sprout } from '@lucide/vue';
import { computed } from 'vue';
import type {
    PetugasTask,
    PetugasTaskFilters,
    PetugasTaskUrgency,
} from '@/types/petugas-task';

const props = defineProps<{
    tasks: PetugasTask[];
    filters: PetugasTaskFilters;
    isFixture: boolean;
}>();

const sections: { key: PetugasTaskUrgency; label: string; description: string }[] = [
    { key: 'overdue', label: 'Terlambat', description: 'Perlu diselesaikan segera.' },
    { key: 'today', label: 'Hari ini', description: 'Jadwal pekerjaan hari ini.' },
    { key: 'upcoming', label: 'Mendatang', description: 'Persiapan untuk jadwal berikutnya.' },
    { key: 'completed', label: 'Selesai', description: 'Pekerjaan yang sudah disetujui.' },
];

const stageLabels: Record<string, string> = {
    land_cleaning: 'Land clearing',
    soil_preparation: 'Pengolahan tanah',
    basic_fertilizer: 'Pemupukan dasar',
    planting: 'Penanaman',
    maintenance: 'Pemeliharaan',
    harvest: 'Panen',
};

const visibleTasks = computed(() =>
    props.filters.status === 'all'
        ? props.tasks
        : props.tasks.filter((task) => task.urgency === props.filters.status),
);

function tasksFor(section: PetugasTaskUrgency): PetugasTask[] {
    return visibleTasks.value.filter((task) => task.urgency === section);
}

function hstLabel(task: PetugasTask): string {
    return `H${task.offset_days >= 0 ? '+' : ''}${task.offset_days}`;
}

function dateLabel(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value))
        : 'Belum dijadwalkan';
}

function statusLabel(status: PetugasTask['status']): string {
    return {
        pending: 'Belum dimulai',
        in_progress: 'Sedang dikerjakan',
        submitted: 'Menunggu review',
        completed: 'Selesai',
        skipped: 'Dilewati',
        cancelled: 'Dibatalkan',
    }[status];
}

function statusClass(status: PetugasTask['status']): string {
    return {
        pending: 'bg-muted text-muted-foreground',
        in_progress: 'bg-warning/10 text-warning',
        submitted: 'bg-info/10 text-info',
        completed: 'bg-success/10 text-success',
        skipped: 'bg-muted text-muted-foreground',
        cancelled: 'bg-error/10 text-error',
    }[status];
}
</script>

<template>
    <Head title="Tugas" />
    <div class="space-y-5">
        <header>
            <p class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase">
                Petugas / Tugas
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">Tugas lapangan</h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                Daftar pekerjaan dari SOP batch yang menjadi tanggung jawab kebun Anda.
            </p>
        </header>

        <p
            v-if="isFixture"
            class="rounded-xl border border-warning/30 bg-warning/10 p-3 text-xs leading-5 text-warning"
        >
            Data contoh untuk pengembangan UI. Halaman ini belum terhubung ke
            <code>planting_cycle_tasks</code>.
        </p>

        <div class="grid grid-cols-3 gap-2">
            <div class="rounded-xl border border-border bg-card p-3">
                <p class="text-[11px] text-muted-foreground">Terlambat</p>
                <p class="mt-1 text-xl font-semibold">{{ tasksFor('overdue').length }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-3">
                <p class="text-[11px] text-muted-foreground">Hari ini</p>
                <p class="mt-1 text-xl font-semibold">{{ tasksFor('today').length }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-3">
                <p class="text-[11px] text-muted-foreground">Mendatang</p>
                <p class="mt-1 text-xl font-semibold">{{ tasksFor('upcoming').length }}</p>
            </div>
        </div>

        <div v-if="!visibleTasks.length" class="rounded-2xl border border-dashed border-border bg-card px-5 py-12 text-center">
            <ClipboardList class="mx-auto size-10 text-muted-foreground" />
            <h2 class="mt-4 font-semibold">Belum ada tugas</h2>
            <p class="mx-auto mt-2 max-w-xs text-sm leading-6 text-muted-foreground">
                Tugas akan muncul setelah SOP diterapkan ke batch kebun Anda.
            </p>
        </div>

        <section v-for="section in sections" :key="section.key" class="space-y-3">
            <div v-if="tasksFor(section.key).length" class="flex items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold">{{ section.label }}</h2>
                    <p class="text-xs text-muted-foreground">{{ section.description }}</p>
                </div>
                <span class="rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground">
                    {{ tasksFor(section.key).length }}
                </span>
            </div>

            <article
                v-for="task in tasksFor(section.key)"
                :key="task.id"
                class="rounded-2xl border border-border bg-card p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">
                                {{ hstLabel(task) }}
                            </span>
                            <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">
                                {{ stageLabels[task.stage_code] ?? task.stage_code }}
                            </span>
                            <span v-if="task.is_required" class="text-[11px] font-medium text-warning">Wajib</span>
                        </div>
                        <h3 class="mt-2 font-semibold leading-5">{{ task.name }}</h3>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-1 text-[11px] font-medium" :class="statusClass(task.status)">
                        {{ statusLabel(task.status) }}
                    </span>
                </div>

                <p v-if="task.instructions" class="mt-3 line-clamp-2 text-sm leading-5 text-muted-foreground">
                    {{ task.instructions }}
                </p>

                <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    <span class="inline-flex items-center gap-1">
                        <MapPin class="size-3.5" />
                        {{ task.batch.farm.name }} · {{ task.batch.block.name }}
                        <template v-if="task.batch.subblock_label"> · {{ task.batch.subblock_label }}</template>
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <Sprout class="size-3.5" />
                        {{ task.batch.variety.name }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <CalendarDays class="size-3.5" />
                        {{ dateLabel(task.scheduled_date) }}
                    </span>
                </div>

                <div class="mt-3 flex items-center justify-between gap-3 border-t border-border pt-3 text-xs">
                    <span class="text-muted-foreground">
                        {{ task.activity_type.name }} · {{ task.linked_activities_count }} aktivitas terkait
                    </span>
                    <span v-if="task.latest_submission?.status === 'rejected'" class="font-medium text-error">
                        Perlu diperbaiki
                    </span>
                    <span v-else-if="task.latest_submission?.status === 'submitted'" class="font-medium text-info">
                        Menunggu approval
                    </span>
                </div>
            </article>
        </section>
    </div>
</template>
