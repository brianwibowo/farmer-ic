<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    ClipboardPlus,
    FileText,
    Info,
    MapPin,
    Sprout,
    Wheat,
} from '@lucide/vue';
import { computed } from 'vue';
import TaskReportCard from '@/components/petugas/TaskReportCard.vue';
import TaskStatusBadges from '@/components/petugas/TaskStatusBadges.vue';
import { Button } from '@/components/ui/button';
import { useActivityDate } from '@/composables/useActivityDate';
import { formatReportDate } from '@/lib/petugasReport';
import type {
    PetugasTask,
    TaskActivityField,
    TaskActivityReport,
} from '@/types/petugas-task';
import { sopTaskCategoryLabel } from '@/types/sop';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    task: PetugasTask;
    reports: TaskActivityReport[];
    fields: TaskActivityField[];
    canRecord: boolean;
    blockedReason: string | null;
    completionGuidance: string;
}>();
const activityDate = useActivityDate();
const completionForm = useForm({ note: '' });
const completionStatusError = computed(
    () => (completionForm.errors as Record<string, string | undefined>).status,
);
function complete(): void {
    completionForm.post(`/petugas/tasks/${props.task.id}/selesai`, {
        preserveScroll: true,
    });
}
function completedAtLabel(value: string): string {
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(date);
}
</script>

<template>
    <Head title="Detail Tugas" />
    <div class="min-w-0 space-y-4 pb-8 text-foreground">
        <header class="flex items-center gap-2.5">
            <Link
                :href="`/petugas/aktivitas${activityDate}`"
                aria-label="Kembali ke daftar tugas"
                class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-foreground hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <ArrowLeft class="size-5" aria-hidden="true" />
            </Link>
            <h1>Detail tugas</h1>
        </header>

        <section
            class="petugas-card overflow-hidden"
            aria-label="Informasi tugas"
        >
            <div class="bg-primary/5 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary"
                    >
                        <component
                            :is="
                                task.activity_category === 'harvest'
                                    ? Wheat
                                    : Sprout
                            "
                            class="size-3.5"
                            aria-hidden="true"
                        />
                        {{ sopTaskCategoryLabel(task) }}
                    </span>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span
                            class="rounded-xl border border-primary/25 bg-card px-2.5 py-1.5 text-xs font-semibold text-primary"
                            >SOP</span
                        >
                        <span
                            class="rounded-xl bg-card px-2.5 py-1.5 text-xs font-semibold text-muted-foreground"
                        >
                            {{ task.is_required ? 'Wajib' : 'Opsional' }}
                        </span>
                    </div>
                </div>
                <h2 class="mt-3 text-xl leading-6 font-semibold break-words">
                    {{ task.name }}
                </h2>
                <p
                    class="mt-2 flex items-start gap-1.5 text-xs leading-5 text-muted-foreground"
                >
                    <MapPin
                        class="mt-0.5 size-3.5 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 break-words">
                        {{ task.subblok.farm.name }} ·
                        {{ task.subblok.block.name }} ·
                        {{
                            task.subblok.label ??
                            `Subblok ${task.subblok.number}`
                        }}
                    </span>
                </p>
                <TaskStatusBadges
                    class="mt-3"
                    :status="task.status"
                    :urgency="task.urgency"
                />
            </div>
            <dl
                class="grid grid-cols-2 divide-x divide-border border-t border-border"
            >
                <div class="min-w-0 p-3.5">
                    <dt
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <CalendarDays
                            class="size-3.5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        Jadwal tugas
                    </dt>
                    <dd class="mt-1.5 text-sm font-semibold">
                        {{
                            task.scheduled_date
                                ? formatReportDate(task.scheduled_date)
                                : 'Belum dijadwalkan'
                        }}
                    </dd>
                </div>
                <div class="min-w-0 p-3.5">
                    <dt
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <Sprout
                            class="size-3.5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        Target tugas
                    </dt>
                    <dd class="mt-1.5 text-sm font-semibold">
                        HST {{ task.offset_days }}
                    </dd>
                </div>
            </dl>
            <dl
                class="grid grid-cols-2 divide-x divide-border border-t border-border"
            >
                <div class="min-w-0 p-3.5">
                    <dt class="text-xs text-muted-foreground">Varietas</dt>
                    <dd class="mt-1.5 text-sm font-semibold break-words">
                        {{ task.subblok.variety.name }}
                    </dd>
                </div>
                <div class="min-w-0 p-3.5">
                    <dt class="text-xs text-muted-foreground">Umur tanaman</dt>
                    <dd class="mt-1.5 text-sm font-semibold">
                        {{
                            task.subblok.hst === null
                                ? task.subblok.planted_at
                                    ? 'HST belum dimulai'
                                    : 'Belum ditanam'
                                : `${task.subblok.hst} HST`
                        }}
                    </dd>
                </div>
            </dl>
            <div
                v-if="task.reporting_window"
                class="border-t border-border px-4 py-3"
            >
                <p class="text-xs font-semibold text-muted-foreground">
                    Jendela pelaporan
                </p>
                <p class="mt-1 text-sm leading-5">
                    {{ formatReportDate(task.reporting_window.start) }} –
                    {{ formatReportDate(task.reporting_window.end) }} (WIB)
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Termasuk kedua tanggal.
                </p>
            </div>
        </section>

        <section
            class="petugas-card p-4"
            aria-labelledby="instructions-heading"
        >
            <div class="flex items-center gap-2.5 border-b border-border pb-3">
                <span
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <FileText class="size-5" aria-hidden="true" />
                </span>
                <h2 id="instructions-heading" class="text-base font-semibold">
                    Instruksi SOP
                </h2>
            </div>
            <p
                v-if="task.instructions"
                class="pt-3 text-sm leading-6 break-words whitespace-pre-line"
            >
                {{ task.instructions }}
            </p>
            <p v-else class="pt-3 text-sm text-muted-foreground">
                Tidak ada instruksi tambahan.
            </p>
        </section>

        <section class="petugas-card p-4" aria-labelledby="actions-heading">
            <div class="flex items-center gap-2.5">
                <span
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <ClipboardPlus class="size-5" aria-hidden="true" />
                </span>
                <h2 id="actions-heading" class="text-base font-semibold">
                    Pelaksanaan tugas
                </h2>
            </div>
            <p class="mt-3 text-sm leading-6 text-muted-foreground">
                {{ completionGuidance }}
            </p>
            <Button
                v-if="canRecord"
                as-child
                class="mt-4 min-h-12 w-full rounded-xl"
            >
                <Link
                    :href="`/petugas/tasks/${task.id}/aktivitas/buat${activityDate}`"
                >
                    <ClipboardPlus class="size-4" aria-hidden="true" />Catat
                    aktivitas tugas ini
                </Link>
            </Button>
            <p
                v-else
                class="mt-4 flex items-start gap-2 rounded-xl bg-muted/60 p-3 text-sm leading-6"
            >
                <Info
                    class="mt-1 size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span>
                    {{
                        blockedReason ??
                        (task.subblok.planted_at === null
                            ? 'Pencatatan tersedia setelah tanggal tanam aktual dicatat.'
                            : task.status === 'completed'
                              ? 'Tugas ini sudah selesai. Laporan baru tidak dapat ditambahkan.'
                              : task.status === 'skipped'
                                ? 'Tugas ini ditandai tidak dikerjakan.'
                                : 'Tugas sudah ditutup. Pencatatan baru tidak tersedia.')
                    }}
                </span>
            </p>
        </section>

        <section
            v-if="task.can.complete"
            class="petugas-card p-4"
            aria-labelledby="complete-heading"
        >
            <div class="flex items-center gap-2.5 border-b border-border pb-3">
                <span
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-success/10 text-secondary dark:text-primary"
                >
                    <CheckCircle2 class="size-5" aria-hidden="true" />
                </span>
                <h2 id="complete-heading" class="text-base font-semibold">
                    Selesaikan tugas
                </h2>
            </div>
            <p class="mt-3 text-sm leading-6 text-muted-foreground">
                Tandai selesai setelah pekerjaan tuntas. Setelah selesai,
                laporan baru tidak dapat ditambahkan.
            </p>
            <form class="mt-3 space-y-3" @submit.prevent="complete">
                <label class="block space-y-2 text-sm">
                    <span>Catatan penyelesaian (opsional)</span>
                    <textarea
                        v-model="completionForm.note"
                        rows="3"
                        maxlength="2000"
                        class="block min-h-24 w-full rounded-xl border border-border bg-background px-3 py-2 text-base text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-error"
                        :aria-invalid="!!completionForm.errors.note"
                    />
                    <span
                        v-if="completionForm.errors.note"
                        role="alert"
                        class="block text-xs text-error"
                        >{{ completionForm.errors.note }}</span
                    >
                </label>
                <p
                    v-if="completionStatusError"
                    role="alert"
                    class="text-sm text-error"
                >
                    {{ completionStatusError }}
                </p>
                <Button
                    type="submit"
                    class="min-h-12 w-full rounded-xl"
                    :disabled="completionForm.processing"
                >
                    <CheckCircle2 class="size-4" aria-hidden="true" />
                    {{
                        completionForm.processing
                            ? 'Menyimpan…'
                            : 'Selesaikan tugas'
                    }}
                </Button>
                <p
                    v-if="completionForm.processing"
                    role="status"
                    class="text-xs text-muted-foreground"
                >
                    Menyimpan penyelesaian tugas…
                </p>
            </form>
        </section>
        <section
            v-else-if="task.status === 'completed'"
            class="petugas-card border border-success/30 p-4"
            aria-labelledby="completed-heading"
        >
            <div class="flex items-center gap-2.5">
                <span
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-success/10 text-secondary dark:text-primary"
                >
                    <CheckCircle2 class="size-5" aria-hidden="true" />
                </span>
                <div>
                    <h2 id="completed-heading" class="text-base font-semibold">
                        Tugas selesai
                    </h2>
                    <p
                        v-if="task.completed_at"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Dicatat {{ completedAtLabel(task.completed_at) }}
                    </p>
                </div>
            </div>
            <p
                v-if="task.completion_note"
                class="mt-3 rounded-xl bg-muted/50 p-3 text-sm leading-6 break-words whitespace-pre-line"
            >
                {{ task.completion_note }}
            </p>
        </section>

        <section class="space-y-3" aria-labelledby="reports-heading">
            <div class="flex items-center justify-between gap-3">
                <h2 id="reports-heading" class="text-base font-semibold">
                    Aktivitas terkait
                </h2>
                <span
                    class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold text-muted-foreground"
                    >{{ reports.length }} laporan</span
                >
            </div>
            <div
                v-if="!reports.length"
                class="petugas-card petugas-empty bg-muted/40 px-5 py-8 text-center"
            >
                <ClipboardList
                    class="mx-auto size-8 text-primary"
                    aria-hidden="true"
                />
                <h3 class="mt-3 text-sm font-semibold">
                    Belum ada laporan untuk tugas ini
                </h3>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Catat hasil pekerjaan setelah pelaksanaan di lapangan.
                </p>
            </div>
            <TaskReportCard
                v-for="report in reports"
                :key="report.id"
                :report="report"
                :fields="fields"
            />
        </section>
    </div>
</template>
