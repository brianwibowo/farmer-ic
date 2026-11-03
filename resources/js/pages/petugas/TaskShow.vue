<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardPlus } from '@lucide/vue';
import TaskContext from '@/components/petugas/TaskContext.vue';
import { Button } from '@/components/ui/button';
import { useActivityDate } from '@/composables/useActivityDate';
import type {
    PetugasTask,
    TaskActivityField,
    TaskActivityReport,
} from '@/types/petugas-task';
defineProps<{
    task: PetugasTask;
    reports: TaskActivityReport[];
    fields: TaskActivityField[];
    canRecord: boolean;
}>();
const activityDate = useActivityDate();
</script>

<template>
    <Head title="Detail Tugas" />
    <div class="min-w-0 space-y-5 text-foreground">
        <Link
            :href="`/petugas/tasks${activityDate}`"
            class="inline-flex min-h-11 items-center gap-2 rounded-xl text-sm text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            ><ArrowLeft class="size-4" aria-hidden="true" />Kembali ke
            to-do</Link
        >
        <h1 class="text-2xl leading-8 font-semibold">Detail tugas</h1>
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
                    : 'Tugas sedang direview atau sudah ditutup. Pencatatan baru tidak tersedia.'
            }}
        </p>
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
                </p>
                <Link
                    :href="`/petugas/aktivitas?work_date=${report.work_date}`"
                    class="inline-flex min-h-11 items-center text-sm text-primary underline focus-visible:outline-2 focus-visible:outline-ring"
                    >Lihat laporan harian bersama</Link
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
            Laporan adalah bukti pelaksanaan, bukan persetujuan penyelesaian
            tugas. Alur pengajuan dan review selesai belum tersedia.
        </p>
    </div>
</template>
