<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Camera, LoaderCircle, Save, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import TaskContext from '@/components/petugas/TaskContext.vue';
import DailyHeaderContext from '@/components/petugas/DailyHeaderContext.vue';
import ActivityDetailFields, {
    type ActivityField,
} from '@/components/petugas/ActivityDetailFields.vue';
import type { PetugasTask } from '@/types/petugas-task';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    task: PetugasTask;
    fields: ActivityField[];
    today: string;
    workDate: string;
}>();
const form = useForm({
    schema: 'taxonomy',
    work_date: props.workDate,
    start_time: '07:00',
    end_time: '',
    worker_count: 1,
    daily_notes: '',
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
const errors = computed<Record<string, string>>(() => ({
    ...form.errors,
    ...clientErrors.value,
}));
const headerReady = ref(false);
const existingHeader = ref(false);
function selectPhotos(event: Event): void {
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
        form.photos.push(...files);
        for (const key of Object.keys(form.errors)) {
            if (key === 'photos' || key.startsWith('photos.'))
                form.clearErrors(key as keyof typeof form.errors);
        }
    }
    input.value = '';
}
function submit(): void {
    if (form.processing || !headerReady.value) return;
    clientErrors.value = {};
    if (!form.photos.length)
        clientErrors.value.photos = 'Tambahkan minimal satu foto bukti.';
    if (
        !existingHeader.value &&
        form.end_time &&
        form.end_time <= form.start_time
    )
        clientErrors.value.end_time =
            'Waktu selesai harus setelah waktu mulai.';
    if (Object.keys(clientErrors.value).length) return;
    form.transform((data) =>
        existingHeader.value
            ? {
                  ...data,
                  start_time: undefined,
                  end_time: undefined,
                  daily_notes: undefined,
              }
            : data,
    ).post(`/petugas/tasks/${props.task.id}/aktivitas`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Catat Aktivitas Tugas" />
    <div class="min-w-0 space-y-5 text-foreground">
        <header>
            <Link
                :href="`/petugas/tasks/${task.id}`"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl text-sm text-muted-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                ><ArrowLeft class="size-4" aria-hidden="true" />Kembali ke
                tugas</Link
            >
            <h1 class="mt-3 text-xl leading-7 font-semibold">
                Catat aktivitas
            </h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                Laporkan hasil pekerjaan untuk tugas ini. Jenis pekerjaan dan
                lokasi ditentukan dari to-do, bukan dipilih ulang.
            </p>
        </header>
        <details class="petugas-card p-3">
            <summary class="cursor-pointer text-sm font-medium">
                {{ task.name }} · {{ task.subblok.label }}
            </summary>
            <TaskContext :task="task" />
        </details>
        <form class="space-y-4" @submit.prevent="submit">
            <fieldset
                :disabled="form.processing"
                class="space-y-4 disabled:opacity-70"
            >
                <section class="petugas-card space-y-4 p-4">
                    <h2 class="text-base font-semibold">Tanggal pelaksanaan</h2>
                    <label class="block space-y-2 text-sm"
                        ><span>Tanggal kerja</span
                        ><input
                            v-model="form.work_date"
                            type="date"
                            required
                            :max="today"
                            :min="task.subblok.planted_at ?? undefined"
                            class="task-activity-field [color-scheme:light] dark:[color-scheme:dark]"
                            :aria-invalid="!!errors.work_date"
                            aria-describedby="error-work_date"
                        /><span
                            v-if="errors.work_date"
                            id="error-work_date"
                            class="block text-xs text-error"
                            >{{ errors.work_date }}</span
                        ></label
                    >
                </section>
                <DailyHeaderContext
                    v-model:start="form.start_time"
                    v-model:end="form.end_time"
                    v-model:notes="form.daily_notes"
                    :farm-id="task.subblok.farm.id"
                    :date="form.work_date"
                    :errors="errors"
                    @ready="headerReady = $event"
                    @existing="existingHeader = $event"
                />
                <section class="petugas-card space-y-4 p-4">
                    <h2 class="text-base font-semibold">Hasil pelaksanaan</h2>
                    <label class="block space-y-2 text-sm">
                        <span>Jumlah pekerja untuk aktivitas ini</span>
                        <input
                            v-model.number="form.worker_count"
                            type="number"
                            min="1"
                            max="32767"
                            step="1"
                            required
                            class="task-activity-field"
                            :aria-invalid="!!errors.worker_count"
                            aria-describedby="error-worker_count"
                        />
                        <span
                            v-if="errors.worker_count"
                            id="error-worker_count"
                            class="block text-xs text-error"
                            >{{ errors.worker_count }}</span
                        >
                    </label>
                    <ActivityDetailFields
                        v-model="form.details"
                        :fields="fields"
                        :errors="errors"
                    />
                    <p v-if="errors.details" class="text-sm text-error">
                        {{ errors.details }}
                    </p>
                </section>
                <section class="petugas-card space-y-4 p-4">
                    <h2 class="text-base font-semibold">Bukti pekerjaan</h2>
                    <label class="block space-y-2 text-sm"
                        ><span>Foto aktivitas · minimal satu</span
                        ><input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            class="task-activity-field"
                            :aria-invalid="!!errors.photos"
                            aria-describedby="photo-help"
                            @change="selectPhotos"
                    /></label>
                    <p
                        id="photo-help"
                        class="text-xs leading-5 text-muted-foreground"
                    >
                        Maksimal 5 foto JPG, PNG, atau WebP. Maksimal 10 MB per
                        foto. Foto hanya dapat diakses melalui akun berizin.
                    </p>
                    <ul
                        v-if="form.photos.length"
                        class="space-y-2"
                        aria-label="Foto dipilih"
                    >
                        <li
                            v-for="(photo, index) in form.photos"
                            :key="`${photo.name}-${index}`"
                            class="flex items-center gap-2 rounded-xl bg-muted p-2"
                        >
                            <Camera
                                class="size-4 shrink-0"
                                aria-hidden="true"
                            /><span class="min-w-0 flex-1 truncate text-xs">{{
                                photo.name
                            }}</span
                            ><Button
                                type="button"
                                variant="ghost"
                                class="size-11 rounded-xl"
                                :aria-label="`Hapus ${photo.name}`"
                                @click="form.photos.splice(index, 1)"
                                ><X class="size-4" aria-hidden="true"
                            /></Button>
                        </li>
                    </ul>
                    <p
                        v-for="(message, key) in Object.fromEntries(
                            Object.entries(errors).filter(
                                ([key]) =>
                                    key === 'photos' ||
                                    key.startsWith('photos.'),
                            ),
                        )"
                        :key="key"
                        class="text-xs text-error"
                        role="alert"
                    >
                        {{ message }}
                    </p>
                    <label class="block space-y-2 text-sm"
                        ><span>Catatan (opsional)</span
                        ><textarea
                            v-model="form.notes"
                            rows="3"
                            maxlength="5000"
                            class="task-activity-field"
                            :aria-invalid="!!errors.notes"
                            aria-describedby="error-notes"
                        /><span
                            v-if="errors.notes"
                            id="error-notes"
                            class="block text-xs text-error"
                            >{{ errors.notes }}</span
                        ></label
                    >
                </section>
            </fieldset>
            <p
                v-if="Object.keys(errors).length"
                role="alert"
                class="rounded-xl border border-error/30 bg-error/10 p-3 text-sm leading-6 text-foreground"
            >
                {{
                    errors.task ??
                    'Aktivitas belum tersimpan. Periksa isian yang ditandai.'
                }}
            </p>
            <p class="text-xs leading-5 text-muted-foreground">
                Menyimpan laporan tidak otomatis menyelesaikan atau menyetujui
                tugas. Anda dapat mencatat beberapa aktivitas untuk satu tugas.
            </p>
            <Button
                type="submit"
                class="min-h-12 w-full rounded-xl"
                :disabled="form.processing || !headerReady"
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

<style>
.task-activity-field {
    display: block;
    width: 100%;
    min-height: 48px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--background);
    padding: 0.75rem;
    color: var(--foreground);
    font-size: 1rem;
}
.task-activity-field:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}
.task-activity-field[aria-invalid='true'] {
    border-color: var(--error);
}
.task-activity-field:disabled {
    background: var(--muted);
    color: var(--muted-foreground);
}
</style>
