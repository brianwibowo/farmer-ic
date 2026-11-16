<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, LoaderCircle, Save, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import DailyHeaderContext from '@/components/petugas/DailyHeaderContext.vue';
import type { PlantingCycleContext, PlantingReport } from '@/types/planting';
import {
    areaMaximum,
    formatActivityNumber,
    maximumError,
} from '@/lib/activityInputLimits';

const props = defineProps<{
    farm: { id: string; name: string };
    cycle: PlantingCycleContext;
    varieties: { id: string; name: string }[];
    commodityConfigured: boolean;
    varietyMismatch: boolean;
    reports: PlantingReport[];
    canRecord: boolean;
    today: string;
    workDate: string;
    plantingPattern: string;
    blockedReason: string | null;
    areaLimitM2: number;
    numericLimits: {
        workerCount: number;
        quantityKg: number;
        area: number;
        quantity: number;
        bagSize: number;
        depth: number;
    };
}>();
const form = useForm({
    schema: 'taxonomy',
    work_date: props.workDate,
    start_time: '07:00',
    end_time: '',
    worker_count: '1',
    daily_notes: '',
    variety_id: props.varieties.some(
        (variety) => variety.id === props.cycle.variety?.id,
    )
        ? (props.cycle.variety?.id ?? '')
        : '',
    area: String(props.areaLimitM2),
    area_unit: 'm²',
    seed_name: '',
    quantity: '',
    quantity_unit: 'kg',
    bag_size: '',
    depth_cm: '',
    planting_pattern: props.plantingPattern,
    notes: '',
    photos: [] as File[],
});
type FieldKey =
    | 'work_date'
    | 'start_time'
    | 'end_time'
    | 'worker_count'
    | 'area'
    | 'seed_name'
    | 'quantity'
    | 'bag_size'
    | 'depth_cm'
    | 'planting_pattern';
const fields = computed<
    {
        key: FieldKey;
        label: string;
        type: string;
        min?: string;
        max?: string;
        step?: string;
        optional?: boolean;
    }[]
>(() => [
    {
        key: 'area',
        label: 'Luas tambahan yang ditanam',
        type: 'number',
        min: '0.0001',
        step: '0.0001',
    },
    { key: 'seed_name', label: 'Nama benih / produk', type: 'text' },
    {
        key: 'quantity',
        label: 'Jumlah benih',
        type: 'number',
        min: '0.001',
        max: String(props.numericLimits.quantity),
        step: '0.001',
    },
    {
        key: 'depth_cm',
        label: 'Kedalaman tanam (cm, opsional)',
        type: 'number',
        min: '0.01',
        max: String(props.numericLimits.depth),
        step: '0.01',
        optional: true,
    },
    {
        key: 'planting_pattern',
        label: 'Pola tanam (opsional)',
        type: 'text',
        optional: true,
    },
]);
const clientErrors = ref<Record<string, string>>({});
const headerReady = ref(false);
watch(
    () => form.area_unit,
    (unit, previous) => {
        if (unit === previous || !form.area) return;
        form.area = String(
            Number(
                (Number(form.area) * (unit === 'Ha' ? 0.0001 : 10000)).toFixed(
                    4,
                ),
            ),
        );
    },
);
const existingHeader = ref(false);
const maximumArea = computed(() =>
    areaMaximum(props.numericLimits.area, props.areaLimitM2, form.area_unit),
);
const numericErrors = computed<Record<string, string>>(() => {
    const result: Record<string, string> = {};
    const checks: [string, string | undefined][] = [
        ['worker_count', maximumError(form.worker_count, props.numericLimits.workerCount, 'Jumlah pekerja')],
        ['area', maximumError(form.area, maximumArea.value, 'Luas tambahan', form.area_unit)],
        ['quantity', maximumError(form.quantity, props.numericLimits.quantity, 'Jumlah benih')],
        ['depth_cm', maximumError(form.depth_cm, props.numericLimits.depth, 'Kedalaman tanam', 'cm')],
        [
            'bag_size',
            form.quantity_unit === 'sak'
                ? maximumError(form.bag_size, props.numericLimits.bagSize, 'Berat per sak', 'kg')
                : undefined,
        ],
    ];
    for (const [key, error] of checks) if (error) result[key] = error;
    if (
        form.quantity_unit === 'sak' &&
        form.quantity &&
        form.bag_size &&
        Number(form.quantity) * Number(form.bag_size) > props.numericLimits.quantityKg
    ) {
        result.quantity = `Jumlah benih setelah konversi maksimal ${formatActivityNumber(props.numericLimits.quantityKg)} kg.`;
    }
    return result;
});
const errors = computed<Record<string, string>>(() => ({
    ...form.errors,
    ...clientErrors.value,
    ...numericErrors.value,
}));
watch(
    () => [form.area, form.area_unit, form.worker_count, form.quantity, form.quantity_unit, form.bag_size, form.depth_cm],
    (current, previous) => {
        const keys = ['area', 'area', 'worker_count', 'quantity', 'quantity', 'bag_size', 'depth_cm'] as const;
        current.forEach((value, index) => {
            if (value !== previous[index]) form.clearErrors(keys[index]);
        });
        if (current[5] !== previous[5]) form.clearErrors('quantity');
    },
);
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
    if (
        form.processing ||
        props.areaLimitM2 === 0 ||
        Object.keys(numericErrors.value).length > 0 ||
        !props.commodityConfigured ||
        props.varietyMismatch ||
        !props.varieties.length
    )
        return;
    clientErrors.value = {};
    if (!form.photos.length)
        clientErrors.value.photos = 'Tambahkan minimal satu foto penanaman.';
    if (
        !existingHeader.value &&
        form.end_time &&
        form.end_time <= form.start_time
    )
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
                  daily_notes: undefined,
              }
            : data,
    ).post(`/petugas/subbloks/${props.cycle.id}/penanaman`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.photos = [];
            form.area = '';
            form.quantity = '';
            form.notes = '';
        },
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
            <h1 class="mt-3 text-xl leading-7 font-semibold">
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
                Acuan HST: {{ cycle.planted_at }} · Total ditanam
                {{ cycle.planted_area_ha }} ha ·
                {{ cycle.variety?.name ?? 'Varietas tidak tersedia' }}
            </p>
        </section>
        <p
            v-if="blockedReason"
            role="status"
            class="rounded-xl border border-warning/40 bg-warning/10 p-3 text-sm"
        >
            {{ blockedReason }}
        </p>
        <form v-if="canRecord" class="space-y-4" @submit.prevent="submit">
            <p
                class="rounded-xl border border-border bg-muted/50 p-4 text-sm leading-6"
            >
                Penanaman dapat dicatat dalam tiga hari kalender sejak tanam
                pertama. Isi luas tambahan untuk pekerjaan ini. HST mengikuti
                hari penanaman aktual kedua jika ada; jadwal SOP pending
                menyesuaikan otomatis.
            </p>
            <p
                v-if="!commodityConfigured"
                role="status"
                class="text-sm leading-6 text-muted-foreground"
            >
                Komoditas kebun belum diatur. Hubungi admin untuk mengaturnya.
            </p>
            <p
                v-else-if="varietyMismatch"
                role="status"
                class="rounded-xl border border-warning/40 bg-warning/10 p-3 text-sm leading-6"
            >
                Varietas yang sudah tercatat tidak sesuai dengan komoditas
                kebun. Hubungi admin untuk meninjau data sebelum menambah
                laporan penanaman.
            </p>
            <p
                v-else-if="!varieties.length"
                role="status"
                class="text-sm leading-6 text-muted-foreground"
            >
                Belum ada varietas untuk komoditas kebun ini. Hubungi admin
                untuk menyiapkan data varietas.
            </p>
            <fieldset
                :disabled="form.processing || !varieties.length || varietyMismatch"
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
                    v-model:notes="form.daily_notes"
                    :farm-id="farm.id"
                    :date="form.work_date"
                    :errors="errors"
                    @ready="headerReady = $event"
                    @existing="existingHeader = $event"
                />
                <label class="block space-y-2 text-sm"
                    ><span>Jumlah pekerja untuk aktivitas ini</span
                    ><input
                        v-model="form.worker_count"
                        type="number"
                        min="1"
                        :max="numericLimits.workerCount"
                        step="1"
                        required
                        :class="fieldClass"
                        :aria-invalid="!!errors.worker_count"
                        :aria-describedby="errors.worker_count ? 'planting-worker-help planting-worker-error' : 'planting-worker-help'"
                    /><span
                        id="planting-worker-help"
                        class="block text-xs text-muted-foreground"
                        >Maksimal {{ formatActivityNumber(numericLimits.workerCount) }} pekerja.</span
                    ><span
                        v-if="errors.worker_count"
                        id="planting-worker-error"
                        class="block text-xs text-error"
                        aria-live="polite"
                        >{{ errors.worker_count }}</span
                    ></label
                >
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
                <div class="grid grid-cols-2 gap-3">
                    <label class="space-y-2 text-sm"
                        ><span>Satuan luas</span
                        ><AppSelect
                            v-model="form.area_unit"
                            aria-label="Satuan luas"
                            :class="fieldClass"
                            ><AppSelectOption value="m²">m²</AppSelectOption
                            ><AppSelectOption value="Ha"
                                >Ha</AppSelectOption
                            ></AppSelect
                        ></label
                    ><label class="space-y-2 text-sm"
                        ><span>Satuan benih</span
                        ><AppSelect
                            v-model="form.quantity_unit"
                            aria-label="Satuan benih"
                            :class="fieldClass"
                            ><AppSelectOption value="kg">kg</AppSelectOption
                            ><AppSelectOption value="sak">sak</AppSelectOption
                            ><AppSelectOption value="butir"
                                >butir</AppSelectOption
                            ></AppSelect
                        ></label
                    >
                </div>
                <label
                    v-if="form.quantity_unit === 'sak'"
                    class="block space-y-2 text-sm"
                    ><span>Berat per sak (kg) *</span
                    ><input
                        v-model="form.bag_size"
                        type="number"
                        required
                        min="0.001"
                        step="0.001"
                        :max="numericLimits.bagSize"
                        :class="fieldClass"
                        :aria-invalid="!!errors.bag_size"
                        :aria-describedby="errors.bag_size ? 'planting-bag-help planting-error-bag_size' : 'planting-bag-help'"
                    /><span
                        id="planting-bag-help"
                        class="block text-xs text-muted-foreground"
                        >Maksimal {{ formatActivityNumber(numericLimits.bagSize) }} kg per sak.</span
                    ><span
                        v-if="errors.bag_size"
                        id="planting-error-bag_size"
                        class="block text-xs text-error"
                        aria-live="polite"
                        >{{ errors.bag_size }}</span
                    ></label
                >
                <p v-if="plantingPattern" class="text-xs text-muted-foreground">
                    Pola tanam terisi dari pemetaan. Koreksi di bawah jika
                    pelaksanaan berbeda.
                </p>
                <label
                    v-for="field in fields"
                    :key="field.key"
                    class="block space-y-2 text-sm"
                    ><span>{{ field.label }}</span
                    ><input
                        v-model="form[field.key]"
                        :type="field.type"
                        :required="!field.optional"
                        :min="field.key === 'area'
                            ? (form.area_unit === 'm²' ? 1 : 0.0001)
                            : field.min"
                        :max="field.key === 'area' ? maximumArea : field.max"
                        :step="field.key === 'area'
                            ? (form.area_unit === 'm²' ? 1 : 0.0001)
                            : field.step"
                        :maxlength="field.type === 'text' ? 150 : undefined"
                        :class="fieldClass"
                        :aria-invalid="!!errors[field.key]"
                        :aria-describedby="[
                            field.key === 'area' || field.max ? `planting-help-${field.key}` : null,
                            errors[field.key] ? `planting-error-${field.key}` : null,
                        ].filter(Boolean).join(' ') || undefined"
                    /><span
                        v-if="field.key === 'area'"
                        :id="`planting-help-${field.key}`"
                        class="block text-xs text-muted-foreground"
                        >Maksimal {{ formatActivityNumber(maximumArea ?? 0) }} {{ form.area_unit }} untuk laporan ini.</span
                    ><span
                        v-else-if="field.max"
                        :id="`planting-help-${field.key}`"
                        class="block text-xs text-muted-foreground"
                        >Maksimal {{ formatActivityNumber(Number(field.max)) }}.</span
                    ><span
                        v-if="errors[field.key]"
                        :id="`planting-error-${field.key}`"
                        class="block text-xs text-error"
                        aria-live="polite"
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
                    'Periksa isian yang ditandai sebelum menyimpan.'
                }}
            </p>
            <p v-if="areaLimitM2 === 0" role="status" class="text-sm text-warning">Tidak ada sisa luas untuk penanaman tambahan pada subblok ini.</p>
            <Button
                type="submit"
                class="min-h-12 w-full rounded-xl"
                :disabled="
                    form.processing ||
                    !varieties.length ||
                    varietyMismatch ||
                    !headerReady ||
                    areaLimitM2 === 0 ||
                    Object.keys(numericErrors).length > 0 ||
                    !!blockedReason
                "
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
                    :href="`/petugas/laporan/${report.id}`"
                    class="inline-flex min-h-11 items-center text-sm text-primary underline focus-visible:outline-2 focus-visible:outline-ring"
                    >Lihat detail laporan</Link
                >
                <p v-if="report.detail" class="text-sm leading-6">
                    {{ report.detail.seed_name }} ·
                    {{
                        report.input
                            ? `${report.input.quantity} ${report.input.quantity_unit}`
                            : `${report.detail.quantity_kg ?? '—'} kg`
                    }}
                    · {{ report.detail.area_ha }} ha<template
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
