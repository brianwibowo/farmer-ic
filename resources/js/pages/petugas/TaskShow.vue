<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, ClipboardPlus } from '@lucide/vue';
import { computed } from 'vue';
import TaskContext from '@/components/petugas/TaskContext.vue';
import { Button } from '@/components/ui/button';
import { useActivityDate } from '@/composables/useActivityDate';
import type {
    PetugasTask,
    TaskActivityField,
    TaskActivityReport,
} from '@/types/petugas-task';
const props = defineProps<{
    task: PetugasTask;
    reports: TaskActivityReport[];
    fields: TaskActivityField[];
    canRecord: boolean;
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
function completedAtLabel(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '';
}
</script>

<template>
    <Head title="Detail Tugas" />
    <div class="min-w-0 space-y-5 text-foreground">
        <Link
            :href="`/petugas/aktivitas${activityDate}`"
            class="inline-flex min-h-11 items-center gap-2 rounded-xl text-sm text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            ><ArrowLeft class="size-4" aria-hidden="true" />Kembali ke
            to-do</Link
        >
        <h1 class="text-xl leading-7 font-semibold">{{ task.name }}</h1>
        <TaskContext :task="task" />
        <Button v-if="canRecord" as-child class="min-h-12 w-full rounded-xl"
            ><Link
                :href="`/petugas/tasks/${task.id}/aktivitas/buat${activityDate}`"
                ><ClipboardPlus class="size-4" aria-hidden="true" />Catat
                aktivitas tugas ini</Link
            ></Button
        >
        <p
            v-else
            class="rounded-xl border border-border bg-muted p-3 text-sm leading-6"
        >
            {{
                task.subblok.planted_at === null
                    ? 'Pencatatan tersedia setelah tanggal tanam aktual dicatat.'
                    : task.status === 'completed'
                      ? 'Tugas ini sudah selesai. Laporan baru tidak dapat ditambahkan.'
                      : task.status === 'skipped'
                        ? 'Tugas ini ditandai tidak dikerjakan.'
                        : 'Tugas sudah ditutup. Pencatatan baru tidak tersedia.'
            }}
        </p>
        <section
            v-if="task.can.complete"
            class="space-y-3 rounded-xl border border-border bg-card p-4"
            aria-labelledby="complete-heading"
        >
            <h2 id="complete-heading" class="text-base font-semibold">
                Selesaikan tugas
            </h2>
            <p class="text-sm leading-6 text-muted-foreground">
                Tandai selesai setelah pekerjaan tuntas. Setelah selesai,
                laporan baru tidak dapat ditambahkan.
            </p>
            <form class="space-y-3" @submit.prevent="complete">
                <label class="block space-y-2 text-sm"
                    ><span>Catatan penyelesaian (opsional)</span
                    ><textarea
                        v-model="completionForm.note"
                        rows="3"
                        maxlength="2000"
                        class="block min-h-24 w-full rounded-xl border border-border bg-background px-3 py-2 text-base text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-error"
                        :aria-invalid="!!completionForm.errors.note"
                    /><span
                        v-if="completionForm.errors.note"
                        class="block text-xs text-error"
                        >{{ completionForm.errors.note }}</span
                    ></label
                >
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
                    <CheckCircle2 class="size-4" aria-hidden="true" />Selesaikan
                    tugas
                </Button>
            </form>
        </section>
        <section
            v-else-if="task.status === 'completed'"
            class="space-y-2 rounded-xl border border-success/30 bg-success/10 p-4"
            aria-labelledby="completed-heading"
        >
            <h2 id="completed-heading" class="text-base font-semibold">
                Tugas selesai
            </h2>
            <p v-if="task.completed_at" class="text-xs text-muted-foreground">
                Dicatat {{ completedAtLabel(task.completed_at) }}
            </p>
            <p
                v-if="task.completion_note"
                class="text-sm leading-6 whitespace-pre-line"
            >
                {{ task.completion_note }}
            </p>
        </section>
        <section class="space-y-3" aria-labelledby="reports-heading">
            <h2 id="reports-heading" class="text-lg font-semibold">
                Aktivitas terkait · {{ reports.length }}
            </h2>
            <p
                v-if="!reports.length"
                class="rounded-xl border border-dashed border-border p-5 text-sm leading-6 text-muted-foreground"
            >
                Belum ada laporan untuk tugas ini. Catat hasil pekerjaan setelah
                pelaksanaan di lapangan.
            </p>
            <article
                v-for="report in reports"
                :key="report.id"
                class="space-y-3 rounded-xl border border-border bg-card p-4"
            >
                <h3 class="text-base font-semibold">{{ report.work_date }}</h3>
                <p class="text-xs text-muted-foreground">
                    Dicatat oleh {{ report.performer_name ?? 'Petugas kebun' }}
                    · {{ report.worker_count }} pekerja
                </p>
                <Link
                    :href="`/petugas/laporan/${report.id}`"
                    class="inline-flex min-h-11 items-center text-sm text-primary underline focus-visible:outline-2 focus-visible:outline-ring"
                    >Lihat detail laporan</Link
                >
                <dl class="space-y-2 text-sm">
                    <div
                        v-for="field in fields.filter(
                            (field) =>
                                report.details[field.key] !== null &&
                                report.details[field.key] !== undefined &&
                                report.details[field.key] !== '',
                        )"
                        :key="field.key"
                    >
                        <dt class="text-xs text-muted-foreground">
                            {{ field.label }}
                        </dt>
                        <dd class="mt-1 break-words">
                            {{ report.details[field.key] }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="report.notes"
                    class="text-sm leading-6 whitespace-pre-line"
                >
                    {{ report.notes }}
                </p>
                <div class="grid grid-cols-2 gap-2">
                    <a
                        v-for="(photo, index) in report.photos"
                        :key="photo.id"
                        :href="photo.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        ><img
                            :src="photo.url"
                            :alt="`Bukti aktivitas ${report.work_date}, foto ${index + 1}`"
                            class="aspect-square w-full rounded-xl object-cover"
                            loading="lazy"
                    /></a>
                </div>
            </article>
        </section>
        <p class="text-xs leading-5 text-muted-foreground">
            Menyimpan laporan tidak otomatis menyelesaikan tugas. Tandai selesai
            setelah seluruh pekerjaan tuntas.
        </p>
    </div>
</template>
