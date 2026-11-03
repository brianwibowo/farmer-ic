<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, ClipboardList, MapPin, Sprout } from '@lucide/vue';
import { computed } from 'vue';
import { useActivityDate } from '@/composables/useActivityDate';
import type {
    PetugasTask,
    PetugasTaskPageProps,
    PetugasTaskUrgency,
} from '@/types/petugas-task';
import {
    sopActivityCategoryLabels,
    sopActivitySubcategoryLabels,
} from '@/types/sop';

const props = defineProps<PetugasTaskPageProps>();
const activityDate = useActivityDate();

const sections: {
    key: PetugasTaskUrgency;
    label: string;
    description: string;
}[] = [
    {
        key: 'overdue',
        label: 'Terlambat',
        description: 'Perlu diselesaikan segera.',
    },
    {
        key: 'today',
        label: 'Hari ini',
        description: 'Jadwal pekerjaan hari ini.',
    },
    {
        key: 'upcoming',
        label: 'Mendatang',
        description: 'Persiapan untuk jadwal berikutnya.',
    },
    {
        key: 'completed',
        label: 'Selesai / ditutup',
        description: 'Pekerjaan selesai, dilewati, atau dibatalkan.',
    },
];

const visibleTasks = computed(() =>
    props.filters.status === 'all'
        ? props.tasks
        : props.tasks.filter((task) => task.urgency === props.filters.status),
);

function categoryLabel(task: PetugasTask): string {
    return (
        sopActivityCategoryLabels[task.activity_category] ??
        task.activity_category
    );
}

function subcategoryLabel(task: PetugasTask): string {
    return task.activity_subcategory
        ? (sopActivitySubcategoryLabels[task.activity_subcategory] ??
              task.activity_subcategory)
        : '-';
}

function tasksFor(section: PetugasTaskUrgency): PetugasTask[] {
    return visibleTasks.value.filter((task) => task.urgency === section);
}

function hstLabel(task: PetugasTask): string {
    return `H${task.offset_days >= 0 ? '+' : ''}${task.offset_days}`;
}

function dateLabel(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
              new Date(`${value.slice(0, 10)}T12:00:00`),
          )
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
            <p
                class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase"
            >
                Petugas / Tugas
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">
                Tugas lapangan
            </h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                Daftar pekerjaan dari SOP subblok yang menjadi tanggung jawab
                kebun Anda.
            </p>
            <p class="mt-2 text-sm font-medium">{{ farm.name }}</p>
            <Link
                :href="`/petugas/penanaman${activityDate}`"
                class="mt-3 inline-flex min-h-11 items-center rounded-xl border border-border px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >Catat penanaman / siapkan tugas subblok</Link
            >
        </header>

        <p
            v-if="defaultSopMissing"
            class="rounded-xl border border-border bg-muted p-3 text-sm leading-6 text-foreground"
        >
            SOP default belum diatur. Hubungi admin untuk mengaturnya. Tugas
            lama yang sudah dibuat tetap dapat dilihat.
        </p>

        <div class="grid grid-cols-3 gap-2">
            <div class="rounded-xl border border-border bg-card p-3">
                <p class="text-[11px] text-muted-foreground">Terlambat</p>
                <p class="mt-1 text-xl font-semibold">{{ summary.overdue }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-3">
                <p class="text-[11px] text-muted-foreground">Hari ini</p>
                <p class="mt-1 text-xl font-semibold">{{ summary.today }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-3">
                <p class="text-[11px] text-muted-foreground">Mendatang</p>
                <p class="mt-1 text-xl font-semibold">{{ summary.upcoming }}</p>
            </div>
        </div>

        <nav
            class="flex gap-2 overflow-x-auto pb-2"
            aria-label="Filter jadwal tugas"
        >
            <Link
                v-for="option in [{ key: 'all', label: 'Semua' }, ...sections]"
                :key="option.key"
                :href="`/petugas/tasks?status=${option.key}${activityDate ? `&${activityDate.slice(1)}` : ''}`"
                preserve-scroll
                :aria-current="
                    filters.status === option.key ? 'page' : undefined
                "
                class="inline-flex min-h-11 shrink-0 items-center rounded-xl border px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                :class="
                    filters.status === option.key
                        ? 'border-primary bg-primary/10 font-semibold text-foreground'
                        : 'border-border text-muted-foreground'
                "
                >{{ option.label }}</Link
            >
        </nav>
        <p class="text-xs text-muted-foreground" role="status">
            {{ visibleTasks.length }} tugas ditampilkan
        </p>

        <div
            v-if="!visibleTasks.length"
            class="rounded-2xl border border-dashed border-border bg-card px-5 py-12 text-center"
        >
            <ClipboardList class="mx-auto size-10 text-muted-foreground" />
            <h2 class="mt-4 font-semibold">
                {{
                    filters.status === 'all'
                        ? 'Belum ada tugas'
                        : 'Tidak ada tugas pada filter ini'
                }}
            </h2>
            <p
                class="mx-auto mt-2 max-w-xs text-sm leading-6 text-muted-foreground"
            >
                Tugas akan muncul setelah SOP diterapkan ke subblok kebun Anda.
            </p>
        </div>

        <section
            v-for="section in sections"
            :key="section.key"
            class="space-y-3"
        >
            <div
                v-if="tasksFor(section.key).length"
                class="flex items-end justify-between gap-3"
            >
                <div>
                    <h2 class="text-base font-semibold">{{ section.label }}</h2>
                    <p class="text-xs text-muted-foreground">
                        {{ section.description }}
                    </p>
                </div>
                <span
                    class="rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground"
                >
                    {{ tasksFor(section.key).length }}
                </span>
            </div>

            <article
                v-for="task in tasksFor(section.key)"
                :key="task.id"
                class="rounded-xl border border-border bg-card p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary"
                            >
                                {{ hstLabel(task) }}
                            </span>
                            <span
                                class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground"
                            >
                                {{ categoryLabel(task) }}
                            </span>
                            <span
                                v-if="task.is_required"
                                class="text-[11px] font-medium text-warning"
                                >Wajib</span
                            >
                        </div>
                        <h3 class="mt-2 leading-5 font-semibold">
                            {{ task.name }}
                        </h3>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-1 text-[11px] font-medium"
                        :class="statusClass(task.status)"
                    >
                        {{ statusLabel(task.status) }}
                    </span>
                </div>

                <p
                    v-if="task.instructions"
                    class="mt-3 line-clamp-2 text-sm leading-5 text-muted-foreground"
                >
                    {{ task.instructions }}
                </p>

                <div
                    class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1">
                        <MapPin class="size-3.5" />
                        {{ task.subblok.farm.name }} ·
                        {{ task.subblok.block.name }}
                        <template v-if="task.subblok.label">
                            · {{ task.subblok.label }}</template
                        >
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <Sprout class="size-3.5" />
                        {{ task.subblok.variety.name }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <CalendarDays class="size-3.5" />
                        {{ dateLabel(task.scheduled_date) }}
                    </span>
                </div>

                <div
                    class="mt-3 flex items-center justify-between gap-3 border-t border-border pt-3 text-xs"
                >
                    <span class="text-muted-foreground">
                        {{ categoryLabel(task) }} ·
                        {{ subcategoryLabel(task) }} ·
                        {{ task.linked_activities_count }} aktivitas terkait
                    </span>
                    <span
                        v-if="task.latest_submission?.status === 'rejected'"
                        class="font-medium text-error"
                    >
                        Perlu diperbaiki
                    </span>
                    <span
                        v-else-if="
                            task.latest_submission?.status === 'submitted'
                        "
                        class="font-medium text-info"
                    >
                        Menunggu approval
                    </span>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Link
                        :href="`/petugas/tasks/${task.id}${activityDate}`"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-border px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >Detail &amp; laporan</Link
                    >
                    <Link
                        v-if="task.can.record"
                        :href="`/petugas/tasks/${task.id}/aktivitas/buat${activityDate}`"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-primary px-3 text-sm font-semibold text-primary-foreground hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >Catat aktivitas</Link
                    >
                </div>
            </article>
        </section>
    </div>
</template>
