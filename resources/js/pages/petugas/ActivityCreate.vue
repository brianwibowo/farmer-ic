<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ChevronDown,
    ClipboardCheck,
    FileText,
    Info,
    LoaderCircle,
    MapPin,
    Save,
    SquarePen,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import TaskContext from '@/components/petugas/TaskContext.vue';
import ActivityPhotoPicker from '@/components/petugas/ActivityPhotoPicker.vue';
import { usePhotoCompression } from '@/composables/usePhotoCompression';
import ActivityWorkerCount from '@/components/petugas/ActivityWorkerCount.vue';
import ActivityDetailFields, {
    type ActivityField,
} from '@/components/petugas/ActivityDetailFields.vue';
import { getGrowthStageAtHst, resolveGrowthProfile } from '@/data/growthStages';
import type { PetugasTask } from '@/types/petugas-task';
import {
    activityDetailMaximumErrors,
    maximumError,
} from '@/lib/activityInputLimits';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        task: PetugasTask;
        fields: ActivityField[];
        requiresWorkerCount?: boolean;
        areaLimitM2: number;
        numericLimits: { workerCount: number; quantityKg: number };
        today: string;
        workDate: string;
    }>(),
    { requiresWorkerCount: true },
);
const showWorkerCount = computed(
    () =>
        props.requiresWorkerCount &&
        props.task.activity_category !== 'growth_observation',
);
const growthProfile = computed(() =>
    resolveGrowthProfile(
        props.task.subblok.commodity?.name,
        props.task.subblok.variety?.name,
    ),
);
const suggestedGrowthStage = computed(() =>
    growthProfile.value
        ? getGrowthStageAtHst(growthProfile.value, props.task.subblok.hst)
        : null,
);
const resolvedFields = computed<ActivityField[]>(() =>
    props.fields.map((field) => {
        if (field.key !== 'growth_phase') {
            return field;
        }
        const profile = growthProfile.value;
        if (!profile) {
            return {
                ...field,
                type: 'text',
                placeholder: 'Contoh: Vegetatif, Pembungaan, Siap panen',
            };
        }
        const suggested = suggestedGrowthStage.value;
        return {
            ...field,
            type: 'select',
            options: profile.stages.map((stage) => stage.label),
            optionLabels: Object.fromEntries(
                profile.stages.map((stage) => [
                    stage.label,
                    `${stage.number}. ${stage.label} (${stage.range})${stage.number === suggested?.number ? ' — Sesuai HST' : ''}`,
                ]),
            ),
            hint:
                suggested && props.task.subblok.hst !== null
                    ? `Saran sesuai umur tanaman (${props.task.subblok.hst} HST): ${suggested.label}.`
                    : undefined,
        };
    }),
);
const form = useForm({
    schema: 'taxonomy',
    work_date: props.workDate,
    worker_count: 1,
    notes: '',
    details: Object.fromEntries(
        props.fields.map((field) => [
            field.key,
            field.key === 'area_unit'
                ? 'm²'
                : field.key === 'quantity_unit' || field.key === 'weight_unit'
                  ? 'kg'
                  : '',
        ]),
    ) as Record<string, string>,
    photos: [] as File[],
});
const clientErrors = ref<Record<string, string>>({});
const { compressing, compressionStatus, fallbackNotice, preparePhotos } =
    usePhotoCompression(() => form.photos);
const numericErrors = computed<Record<string, string>>(() => {
    const result = activityDetailMaximumErrors(
        resolvedFields.value,
        form.details,
        props.areaLimitM2,
        props.numericLimits.quantityKg,
    );
    if (showWorkerCount.value) {
        const workerError = maximumError(
            form.worker_count,
            props.numericLimits.workerCount,
            'Jumlah pekerja',
        );
        if (workerError) result.worker_count = workerError;
    }
    return result;
});
const noArea = computed(
    () =>
        props.areaLimitM2 === 0 &&
        resolvedFields.value.some(
            (field) => field.key === 'area' && !field.optional,
        ),
);
const errors = computed<Record<string, string>>(() => ({
    ...form.errors,
    ...clientErrors.value,
    ...numericErrors.value,
}));
const photoErrors = computed(() =>
    Object.entries(errors.value).filter(
        ([key]) => key === 'photos' || key.startsWith('photos.'),
    ),
);
watch(
    () => ({ ...form.details }),
    (current, previous) => {
        for (const key of Object.keys(current)) {
            if (current[key] === previous[key]) continue;
            form.clearErrors(`details.${key}` as keyof typeof form.errors);
            if (key === 'area_unit')
                form.clearErrors('details.area' as keyof typeof form.errors);
            if (key === 'quantity_unit' || key === 'bag_size')
                form.clearErrors(
                    'details.quantity' as keyof typeof form.errors,
                );
        }
    },
);
watch(
    () => form.worker_count,
    () => form.clearErrors('worker_count'),
);
async function selectPhotos(event: Event): Promise<void> {
    if (compressing.value || form.processing) return;
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    clientErrors.value = {};
    if (
        form.photos.length + files.length > 5 ||
        files.some(
            (file) =>
                !['image/jpeg', 'image/png', 'image/webp'].includes(
                    file.type,
                ) || file.size > 10 * 1024 * 1024,
        )
    ) {
        clientErrors.value.photos =
            'Maksimal 5 foto JPG, PNG, atau WebP, masing-masing maksimal 10 MB.';
    } else {
        try {
            form.photos.push(...(await preparePhotos(files)));
        } catch (error) {
            clientErrors.value.photos =
                error instanceof Error
                    ? error.message
                    : 'Foto gagal diproses. Pilih ulang foto.';
        }
        for (const key of Object.keys(form.errors)) {
            if (key === 'photos' || key.startsWith('photos.'))
                form.clearErrors(key as keyof typeof form.errors);
        }
    }
    input.value = '';
}
function removePhoto(index: number): void {
    if (compressing.value || form.processing) return;
    form.photos.splice(index, 1);
    delete clientErrors.value.photos;
    for (const key of Object.keys(form.errors)) {
        if (key === 'photos' || key.startsWith('photos.'))
            form.clearErrors(key as keyof typeof form.errors);
    }
}
function dateLabel(value: string): string {
    const date = new Date(`${value.slice(0, 10)}T12:00:00`);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
              date,
          );
}
function submit(): void {
    if (
        form.processing ||
        compressing.value ||
        noArea.value ||
        Object.keys(numericErrors.value).length
    )
        return;
    clientErrors.value = {};
    if (!form.photos.length)
        clientErrors.value.photos = 'Tambahkan minimal satu foto bukti.';
    if (Object.keys(clientErrors.value).length) return;
    form.transform(({ worker_count, ...rest }) =>
        showWorkerCount.value ? { worker_count, ...rest } : rest,
    ).post(`/petugas/tasks/${props.task.id}/aktivitas`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Catat Aktivitas Tugas" />
    <div class="min-w-0 space-y-[18px] text-foreground">
        <header>
            <Link
                :href="`/petugas/tasks/${task.id}`"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl text-sm text-muted-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                ><ArrowLeft class="size-4" aria-hidden="true" />Kembali ke
                tugas</Link
            >
            <h1 class="mt-2 text-xl leading-7 font-semibold">
                Catat aktivitas
            </h1>
            <p class="mt-1 text-sm leading-5 text-muted-foreground">
                Laporkan hasil pekerjaan untuk tugas ini.
            </p>
        </header>
        <section
            class="petugas-card overflow-hidden"
            aria-label="Tugas yang dilaporkan"
        >
            <details class="group">
                <summary
                    class="flex min-h-11 cursor-pointer list-none items-center gap-3 bg-primary/5 p-4 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
                >
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <ClipboardCheck class="size-5" aria-hidden="true" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-semibold text-primary"
                            >Tugas SOP</span
                        >
                        <span
                            class="mt-0.5 block text-base leading-6 font-bold"
                        >
                            {{ task.name }}
                        </span>
                        <span
                            class="mt-1 flex items-start gap-1 text-xs leading-5 text-muted-foreground"
                        >
                            <MapPin
                                class="mt-0.5 size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            <span>
                                {{ task.subblok.farm.name }} ·
                                {{ task.subblok.block.name }} ·
                                {{
                                    task.subblok.label ??
                                    `Subblok ${task.subblok.number}`
                                }}
                            </span>
                        </span>
                    </span>
                    <ChevronDown
                        class="size-5 shrink-0 text-muted-foreground transition-transform group-open:rotate-180 motion-reduce:transition-none"
                        aria-hidden="true"
                    />
                    <span class="sr-only">Lihat detail tugas</span>
                </summary>
                <div class="pt-3">
                    <TaskContext :task="task" compact />
                </div>
            </details>
        </section>
        <form class="space-y-4" @submit.prevent="submit">
            <fieldset
                :disabled="form.processing"
                class="space-y-4 disabled:opacity-70"
            >
                <section class="petugas-card space-y-4 p-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <CalendarDays class="size-5" aria-hidden="true" />
                        </span>
                        <h2 class="text-base font-semibold">
                            Waktu pelaksanaan
                        </h2>
                    </div>
                    <label class="block space-y-2 text-sm"
                        ><span class="font-medium">Tanggal kerja *</span
                        ><input
                            v-model="form.work_date"
                            type="date"
                            required
                            :max="
                                task.reporting_window &&
                                task.reporting_window.end < today
                                    ? task.reporting_window.end
                                    : today
                            "
                            :min="
                                task.reporting_window?.start ??
                                task.subblok.planted_at ??
                                undefined
                            "
                            class="petugas-activity-field block [color-scheme:light] dark:[color-scheme:dark]"
                            :aria-invalid="!!errors.work_date"
                            :aria-describedby="
                                [
                                    task.reporting_window
                                        ? 'work-date-help'
                                        : null,
                                    errors.work_date ? 'error-work_date' : null,
                                ]
                                    .filter(Boolean)
                                    .join(' ') || undefined
                            "
                        /><span
                            v-if="errors.work_date"
                            id="error-work_date"
                            class="block text-xs text-error"
                            >{{ errors.work_date }}</span
                        ></label
                    >
                    <p
                        v-if="task.reporting_window"
                        id="work-date-help"
                        class="flex items-start gap-2 text-xs leading-5 text-muted-foreground"
                    >
                        <Info
                            class="mt-0.5 size-3.5 shrink-0 text-info"
                            aria-hidden="true"
                        />
                        <span
                            >Jendela pelaporan:
                            {{ dateLabel(task.reporting_window.start) }} –
                            {{ dateLabel(task.reporting_window.end) }}
                            (WIB).</span
                        >
                    </p>
                </section>
                <section class="petugas-card space-y-4 p-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-info/10 text-info"
                        >
                            <SquarePen class="size-5" aria-hidden="true" />
                        </span>
                        <h2 class="text-base font-semibold">Hasil pekerjaan</h2>
                    </div>
                    <ActivityWorkerCount
                        v-if="showWorkerCount"
                        v-model="form.worker_count"
                        :maximum="numericLimits.workerCount"
                        :error="errors.worker_count"
                    />
                    <ActivityDetailFields
                        v-model="form.details"
                        :fields="resolvedFields"
                        :errors="errors"
                        :area-limit-m2="areaLimitM2"
                        compact
                    />
                    <p v-if="noArea" role="status" class="text-sm text-warning">
                        Tidak ada sisa luas yang dapat dilaporkan untuk
                        aktivitas ini.
                    </p>
                    <p v-if="errors.details" class="text-sm text-error">
                        {{ errors.details }}
                    </p>
                </section>
                <section class="petugas-card space-y-4 p-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <FileText class="size-5" aria-hidden="true" />
                        </span>
                        <h2 class="text-base font-semibold">Bukti pekerjaan</h2>
                    </div>
                    <ActivityPhotoPicker
                        :busy="compressing || form.processing"
                        :status="compressionStatus"
                        :notice="fallbackNotice"
                        :photos="form.photos"
                        :invalid="photoErrors.length > 0"
                        @select="selectPhotos"
                        @remove="removePhoto"
                    />
                    <div
                        v-if="photoErrors.length"
                        id="photo-errors"
                        role="alert"
                        class="space-y-1"
                    >
                        <p
                            v-for="[key, message] in photoErrors"
                            :key="key"
                            class="text-xs text-error"
                        >
                            {{ message }}
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Foto hanya dapat diakses melalui akun berizin.
                    </p>
                    <details
                        class="group rounded-xl bg-muted/50"
                        :open="errors.notes ? true : undefined"
                    >
                        <summary
                            class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-xl px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
                        >
                            <FileText
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span class="flex-1">Tambah catatan</span>
                            <ChevronDown
                                class="size-4 text-muted-foreground transition-transform group-open:rotate-180 motion-reduce:transition-none"
                                aria-hidden="true"
                            />
                        </summary>
                        <div class="space-y-2 px-3 pb-3">
                            <label
                                for="activity-notes"
                                class="block text-sm font-medium"
                                >Catatan (opsional)</label
                            >
                            <textarea
                                id="activity-notes"
                                v-model="form.notes"
                                rows="3"
                                maxlength="5000"
                                placeholder="Metode, bahan, dosis, atau kondisi lapangan..."
                                class="petugas-activity-field block"
                                :aria-invalid="!!errors.notes"
                                :aria-describedby="
                                    errors.notes ? 'error-notes' : undefined
                                "
                            />
                            <p
                                v-if="errors.notes"
                                id="error-notes"
                                class="text-xs text-error"
                            >
                                {{ errors.notes }}
                            </p>
                        </div>
                    </details>
                </section>
            </fieldset>
            <p
                v-if="Object.keys(errors).length"
                role="alert"
                class="rounded-xl border border-error/30 bg-error/10 p-3 text-sm leading-6 text-foreground"
            >
                {{
                    errors.task ??
                    'Periksa isian yang ditandai sebelum menyimpan.'
                }}
            </p>
            <p
                class="flex items-start gap-2 rounded-xl bg-muted/60 p-3 text-xs leading-5 text-muted-foreground"
            >
                <Info
                    class="mt-0.5 size-4 shrink-0 text-info"
                    aria-hidden="true"
                />
                <span
                    >Laporan tidak otomatis menyelesaikan atau menyetujui tugas.
                    Satu tugas dapat memiliki beberapa aktivitas.</span
                >
            </p>
            <Button
                type="submit"
                class="min-h-12 w-full rounded-xl"
                :disabled="
                    form.processing ||
                    compressing ||
                    noArea ||
                    Object.keys(numericErrors).length > 0
                "
                ><LoaderCircle
                    v-if="form.processing"
                    class="size-4 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                /><Save v-else class="size-4" aria-hidden="true" />{{
                    form.processing
                        ? `Menyimpan${form.progress ? ` ${form.progress.percentage}%` : '…'}`
                        : 'Simpan aktivitas'
                }}</Button
            >
        </form>
    </div>
</template>
