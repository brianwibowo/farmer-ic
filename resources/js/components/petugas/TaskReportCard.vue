<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    Camera,
    ChevronRight,
    ImageOff,
    UserRound,
    UsersRound,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { formatReportDate } from '@/lib/petugasReport';
import type {
    TaskActivityField,
    TaskActivityReport,
} from '@/types/petugas-task';

const props = defineProps<{
    report: TaskActivityReport;
    fields: TaskActivityField[];
}>();
const failedIds = ref<string[]>([]);
const visibleFields = computed(() =>
    props.fields.filter((field) => {
        const value = props.report.details?.[field.key];
        return value !== null && value !== undefined && value !== '';
    }),
);
watch(
    () => props.report.photos,
    () => (failedIds.value = []),
);

function available(photo: TaskActivityReport['photos'][number]): boolean {
    return photo.available && !failedIds.value.includes(photo.id);
}
function markFailed(id: string): void {
    if (!failedIds.value.includes(id)) failedIds.value.push(id);
}
</script>

<template>
    <article class="petugas-card overflow-hidden">
        <div class="bg-primary/5 p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-primary">
                        Laporan tugas
                    </p>
                    <h3
                        class="mt-1 flex items-start gap-2 text-base font-semibold"
                    >
                        <CalendarDays
                            class="mt-0.5 size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        {{
                            report.work_date
                                ? formatReportDate(report.work_date)
                                : 'Tanggal kerja tidak tercatat'
                        }}
                    </h3>
                </div>
                <span
                    class="shrink-0 rounded-full bg-card px-2.5 py-1 text-xs font-semibold text-primary"
                    >SOP</span
                >
            </div>
            <div
                class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
            >
                <p class="flex items-center gap-1.5">
                    <UserRound
                        class="size-3.5 text-primary"
                        aria-hidden="true"
                    />
                    {{ report.performer_name ?? 'Petugas kebun' }}
                </p>
                <p
                    v-if="report.worker_count !== null"
                    class="flex items-center gap-1.5"
                >
                    <UsersRound
                        class="size-3.5 text-primary"
                        aria-hidden="true"
                    />
                    {{ report.worker_count }} pekerja
                </p>
            </div>
        </div>
        <div class="space-y-3 p-4">
            <dl v-if="visibleFields.length" class="divide-y divide-border">
                <div
                    v-for="field in visibleFields"
                    :key="field.key"
                    class="flex items-start justify-between gap-4 py-2"
                >
                    <dt class="min-w-0 text-xs text-muted-foreground">
                        {{ field.label }}
                    </dt>
                    <dd
                        class="max-w-[60%] min-w-0 text-right text-sm font-medium break-words whitespace-pre-line"
                    >
                        {{ report.details?.[field.key] }}
                    </dd>
                </div>
            </dl>
            <div v-if="report.notes" class="rounded-xl bg-muted/50 p-3">
                <p class="text-xs font-semibold text-muted-foreground">
                    Catatan
                </p>
                <p
                    class="mt-1 text-sm leading-6 break-words whitespace-pre-line"
                >
                    {{ report.notes }}
                </p>
            </div>
            <div v-if="report.photos.length">
                <p
                    class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                >
                    <Camera class="size-4 text-primary" aria-hidden="true" />
                    {{ report.photos.length }} foto dokumentasi
                </p>
                <div class="grid grid-cols-3 gap-2">
                    <template
                        v-for="(photo, index) in report.photos"
                        :key="photo.id"
                    >
                        <a
                            v-if="available(photo)"
                            :href="photo.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            :aria-label="`Buka foto ${index + 1} laporan tugas`"
                            class="aspect-square overflow-hidden rounded-xl bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            <img
                                :src="photo.url"
                                :alt="`Dokumentasi laporan tugas, foto ${index + 1}`"
                                class="size-full object-cover"
                                loading="lazy"
                                @error="markFailed(photo.id)"
                            />
                        </a>
                        <div
                            v-else
                            class="flex aspect-square flex-col items-center justify-center gap-1 rounded-xl bg-muted p-1 text-center text-muted-foreground"
                        >
                            <ImageOff class="size-5" aria-hidden="true" />
                            <span class="text-[10px] leading-4"
                                >Foto {{ index + 1 }} tidak tersedia</span
                            >
                        </div>
                    </template>
                </div>
            </div>
            <Link
                :href="`/petugas/laporan/${report.id}`"
                class="flex min-h-11 items-center justify-between gap-2 border-t border-border pt-2 text-sm font-semibold text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                Lihat detail laporan
                <ChevronRight class="size-4" aria-hidden="true" />
            </Link>
        </div>
    </article>
</template>
