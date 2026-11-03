<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, LoaderCircle, Save, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import DailyHeaderContext from '@/components/petugas/DailyHeaderContext.vue';
import type { PlantingCycleContext, PlantingReport } from '@/types/planting';

const props = defineProps<{
    farm: { id: string; name: string };
    cycle: PlantingCycleContext;
    varieties: { id: string; name: string }[];
    reports: PlantingReport[];
    canRecord: boolean;
    today: string;
    workDate: string;
}>();
const form = useForm({
    work_date: props.workDate,
    start_time: '07:00',
    end_time: '16:00',
    worker_count: '1',
    daily_notes: '',
    variety_id: '',
    area_ha: props.cycle.reserved_area_ha,
    seed_name: '',
    quantity_kg: '',
    planting_pattern: '',
    notes: '',
    photos: [] as File[],
});
type FieldKey =
    | 'work_date'
    | 'start_time'
    | 'end_time'
    | 'worker_count'
    | 'area_ha'
    | 'seed_name'
    | 'quantity_kg'
    | 'planting_pattern';
const fields: {
    key: FieldKey;
    label: string;
    type: string;
    min?: string;
    max?: string;
    step?: string;
    optional?: boolean;
}[] = [
    {
        key: 'area_ha',
        label: 'Luas tanam aktual (ha)',
        type: 'number',
        min: '0.0001',
        max: props.cycle.reserved_area_ha,
        step: '0.0001',
    },
    { key: 'seed_name', label: 'Nama benih / produk', type: 'text' },
    {
        key: 'quantity_kg',
        label: 'Jumlah benih (kg)',
        type: 'number',
        min: '0.001',
        max: '999999999.999',
        step: '0.001',
    },
    {
        key: 'planting_pattern',
        label: 'Pola tanam (opsional)',
        type: 'text',
        optional: true,
    },
];
const clientErrors = ref<Record<string, string>>({});
const headerReady = ref(false);
const existingHeader = ref(false);
const errors = computed<Record<string, string>>(() => ({
    ...form.errors,
    ...clientErrors.value,
}));
const fieldClass =
    'block min-h-12 w-full rounded-xl border border-border bg-background px-3 py-3 text-base text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-error disabled:bg-muted [color-scheme:light] dark:[color-scheme:dark]';
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
        form.clearErrors('photos');
    }
    input.value = '';
}
function submit(): void {
    if (form.processing) return;
    clientErrors.value = {};
    if (!form.photos.length)
        clientErrors.value.photos = 'Tambahkan minimal satu foto penanaman.';
    if (!existingHeader.value && form.end_time <= form.start_time)
        clientErrors.value.end_time =
            'Waktu selesai harus setelah waktu mulai.';
    if (Object.keys(clientErrors.value).length) return;
    if (!headerReady.value) return;
    form.transform((data) =>
        existingHeader.value
            ? {
                  ...data,
                  start_time: undefined,
                  end_time: undefined,
                  worker_count: undefined,
                  daily_notes: undefined,
              }
            : data,
    ).post(`/petugas/subbloks/${props.cycle.id}/penanaman`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Realisasi Penanaman" />
    <div class="min-w-0 space-y-5 text-foreground">
        <header>
            <Link
                href="/petugas/penanaman"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl text-sm text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                ><ArrowLeft class="size-4" aria-hidden="true" />Daftar
                subblok</Link
            >
            <h1 class="mt-3 text-2xl leading-8 font-semibold">
                {{
                    canRecord ? 'Catat penanaman aktual' : 'Realisasi penanaman'
                }}
            </h1>
        </header>
        <section
            class="space-y-2 rounded-xl border border-primary/30 bg-primary/10 p-4"
        >
            <h2 class="text-lg font-semibold">
                {{ cycle.block.name }} · {{ cycle.subblock_label }}
            </h2>
            <p class="text-sm">
                {{ farm.name }} · Batch {{ cycle.batch_number }}
            </p>
            <p class="text-xs leading-5">
                Rencana: {{ cycle.planned_planting_at ?? 'Belum ditentukan' }} ·
                Alokasi {{ cycle.reserved_area_ha }} ha
            </p>
            <p v-if="cycle.planted_at" class="text-sm font-semibold">
                Tanam aktual: {{ cycle.planted_at }} ·
                {{ cycle.planted_area_ha }} ha ·
                {{ cycle.variety?.name ?? 'Varietas tidak tersedia' }}
            </p>
        </section>
        <form v-if="canRecord" class="space-y-4" @submit.prevent="submit">
            <p
                class="rounded-xl border border-border bg-muted/50 p-4 text-sm leading-6"
            >
                Satu realisasi awal per subblok. Tanggal pelaksanaan menjadi
                acuan HST, bukan tanggal pengisian laporan. Data yang sudah
                tersimpan tidak ditimpa melalui form ini.
            </p>
            <p
                v-if="!varieties.length"
                role="status"
                class="text-sm leading-6 text-muted-foreground"
            >
                Belum ada varietas yang dapat dipilih. Hubungi admin untuk
                menyiapkan data varietas.
            </p>
            <fieldset
                :disabled="form.processing || !varieties.length"
                class="space-y-4 rounded-xl border border-border bg-card p-4 disabled:opacity-70"
            >
                <h2 class="text-base font-semibold">Pelaksanaan penanaman</h2>
                <label class="block space-y-2 text-sm"
                    ><span>Tanggal tanam aktual</span
                    ><input
                        v-model="form.work_date"
                        type="date"
                        required
                        :max="today"
                        :class="fieldClass"
                        :aria-invalid="!!errors.work_date"
                    /><span
                        v-if="errors.work_date"
                        class="block text-xs text-error"
                        >{{ errors.work_date }}</span
                    ></label
                >
                <DailyHeaderContext
                    v-model:start="form.start_time"
                    v-model:end="form.end_time"
                    v-model:workers="form.worker_count"
                    v-model:notes="form.daily_notes"
                    :farm-id="farm.id"
                    :date="form.work_date"
                    :errors="errors"
                    @ready="headerReady = $event"
                    @existing="existingHeader = $event"
                />
                <label class="block space-y-2 text-sm"
                    ><span>Varietas</span
                    ><AppSelect
                        v-model="form.variety_id"
                        required
                        aria-label="Varietas"
                        placeholder="Pilih varietas"
                        :disabled="form.processing || !varieties.length"
                        :class="fieldClass"
                        :aria-invalid="!!errors.variety_id"
                        aria-describedby="planting-variety-error"
                    >
                        <AppSelectOption
                            v-for="variety in varieties"
                            :key="variety.id"
                            :value="variety.id"
                        >
                            {{ variety.name }}
                        </AppSelectOption></AppSelect
                    ><span
                        v-if="errors.variety_id"
                        id="planting-variety-error"
                        class="block text-xs text-error"
                        >{{ errors.variety_id }}</span
                    ></label
                >
                <label
                    v-for="field in fields"
                    :key="field.key"
                    class="block space-y-2 text-sm"
                    ><span>{{ field.label }}</span
                    ><input
                        v-model="form[field.key]"
                        :type="field.type"
                        :required="!field.optional"
                        :min="field.min"
                        :max="field.max"
                        :step="field.step"
                        :maxlength="field.type === 'text' ? 150 : undefined"
                        :class="fieldClass"
                        :aria-invalid="!!errors[field.key]"
                        :aria-describedby="`planting-error-${field.key}`"
                    /><span
                        v-if="errors[field.key]"
                        :id="`planting-error-${field.key}`"
                        class="block text-xs text-error"
                        >{{ errors[field.key] }}</span
                    ></label
                >
                <h2 class="pt-2 text-base font-semibold">Bukti penanaman</h2>
                <label class="block space-y-2 text-sm"
                    ><span>Foto · minimal satu</span
                    ><input
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        :class="fieldClass"
                        :aria-invalid="!!errors.photos"
                        aria-describedby="planting-photo-help"
                        @change="selectPhotos"
                /></label>
                <p
                    id="planting-photo-help"
                    class="text-xs leading-5 text-muted-foreground"
                >
                    JPG, PNG, atau WebP. Maksimal 5 foto, masing-masing 10 MB.
                    Pelapor diambil dari akun yang sedang masuk.
                </p>
                <ul v-if="form.photos.length" class="space-y-2">
                    <li
                        v-for="(photo, index) in form.photos"
                        :key="`${photo.name}-${index}`"
                        class="flex items-center gap-2 rounded-xl bg-muted px-3 py-1"
                    >
                        <span class="min-w-0 flex-1 truncate text-xs">{{
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
                                key === 'photos' || key.startsWith('photos.'),
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
                        :class="fieldClass"
                        :aria-invalid="!!errors.notes"
                    /><span
                        v-if="errors.notes"
                        class="block text-xs text-error"
                        >{{ errors.notes }}</span
                    ></label
                >
            </fieldset>
            <p
                v-if="Object.keys(errors).length"
                role="alert"
                class="rounded-xl border border-error/30 bg-error/10 p-3 text-sm leading-6"
            >
                {{
                    errors.cycle ??
                    'Penanaman belum tersimpan. Periksa isian yang ditandai.'
                }}
            </p>
            <Button
                type="submit"
                class="min-h-12 w-full rounded-xl"
                :disabled="form.processing || !varieties.length || !headerReady"
                ><LoaderCircle
                    v-if="form.processing"
                    class="size-4 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                /><Save v-else class="size-4" aria-hidden="true" />{{
                    form.processing
                        ? 'Menyimpan…'
                        : 'Simpan realisasi penanaman'
                }}</Button
            >
        </form>
        <p
            v-else
            class="rounded-xl border border-border bg-muted/50 p-4 text-sm leading-6"
        >
            {{
                cycle.planted_at
                    ? 'Realisasi awal sudah tersimpan. Form ini tidak menimpa tanggal, varietas, atau luas yang sudah dicatat.'
                    : 'Pencatatan belum tersedia untuk subblok atau akun ini.'
            }}
        </p>
        <section class="space-y-3">
            <h2 class="text-lg font-semibold">
                Laporan penanaman · {{ reports.length }}
            </h2>
            <p
                v-if="!reports.length"
                class="rounded-xl border border-dashed border-border p-4 text-sm text-muted-foreground"
            >
                Belum ada laporan penanaman tersimpan.
            </p>
            <article
                v-for="report in reports"
                :key="report.id"
                class="space-y-3 rounded-xl border border-border bg-card p-4"
            >
                <h3 class="font-semibold">{{ report.date }}</h3>
                <Link
                    :href="`/petugas/aktivitas?work_date=${report.date}`"
                    class="inline-flex min-h-11 items-center text-sm text-primary underline focus-visible:outline-2 focus-visible:outline-ring"
                    >Lihat laporan harian bersama</Link
                >
                <p v-if="report.detail" class="text-sm leading-6">
                    {{ report.detail.seed_name }} ·
                    {{ report.detail.quantity_kg }} kg ·
                    {{ report.detail.area_ha }} ha<template
                        v-if="report.detail.planting_pattern"
                    >
                        · {{ report.detail.planting_pattern }}</template
                    >
                </p>
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
                            :alt="`Bukti penanaman ${report.date}, foto ${index + 1}`"
                            class="aspect-square w-full rounded-xl object-cover"
                            loading="lazy"
                    /></a>
                </div>
            </article>
        </section>
        <Button as-child variant="outline" class="min-h-11 w-full rounded-xl"
            ><Link href="/petugas/tasks"
                >Lihat to-do pekerjaan lanjutan</Link
            ></Button
        >
    </div>
</template>
