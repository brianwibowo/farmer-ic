<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ChevronDown,
    FileText,
    LoaderCircle,
    MapPin,
    Save,
    Sprout,
    SquarePen,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import ActivityPhotoPicker from '@/components/petugas/ActivityPhotoPicker.vue';
import { usePhotoCompression } from '@/composables/usePhotoCompression';
import ActivityWorkerCount from '@/components/petugas/ActivityWorkerCount.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { useErrorDisclosure } from '@/composables/useErrorDisclosure';
import type { PlantingCycleContext, PlantingReport } from '@/types/planting';
import {
    areaMaximum,
    formatActivityNumber,
    maximumError,
} from '@/lib/activityInputLimits';

defineOptions({ inheritAttrs: false });

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
    blockedReason: string | null;
    areaLimitM2: number;
    numericLimits: {
        workerCount: number;
        quantityKg: number;
        area: number;
        quantity: number;
        bagSize: number;
    };
}>();
const form = useForm({
    schema: 'taxonomy',
    work_date: props.workDate,
    worker_count: 1,
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
    notes: '',
    photos: [] as File[],
});
type FieldKey = 'area' | 'seed_name' | 'quantity';
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
]);
const clientErrors = ref<Record<string, string>>({});
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
const maximumArea = computed(() =>
    areaMaximum(props.numericLimits.area, props.areaLimitM2, form.area_unit),
);
const numericErrors = computed<Record<string, string>>(() => {
    const result: Record<string, string> = {};
    const checks: [string, string | undefined][] = [
        [
            'worker_count',
            maximumError(
                form.worker_count,
                props.numericLimits.workerCount,
                'Jumlah pekerja',
            ),
        ],
        [
            'area',
            maximumError(
                form.area,
                maximumArea.value,
                'Luas tambahan',
                form.area_unit,
            ),
        ],
        [
            'quantity',
            maximumError(
                form.quantity,
                props.numericLimits.quantity,
                'Jumlah benih',
            ),
        ],
        [
            'bag_size',
            form.quantity_unit === 'sak'
                ? maximumError(
                      form.bag_size,
                      props.numericLimits.bagSize,
                      'Berat per sak',
                      'kg',
                  )
                : undefined,
        ],
    ];
    for (const [key, error] of checks) if (error) result[key] = error;
    if (
        form.quantity_unit === 'sak' &&
        form.quantity &&
        form.bag_size &&
        Number(form.quantity) * Number(form.bag_size) >
            props.numericLimits.quantityKg
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
const photoErrors = computed(() =>
    Object.entries(errors.value).filter(
        ([key]) => key === 'photos' || key.startsWith('photos.'),
    ),
);
const { open: notesOpen, onToggle: syncNotesOpen } = useErrorDisclosure(
    () => !!errors.value.notes,
);
watch(
    () => form.notes,
    () => form.clearErrors('notes'),
);
watch(
    () => [
        form.area,
        form.area_unit,
        form.worker_count,
        form.quantity,
        form.quantity_unit,
        form.bag_size,
    ],
    (current, previous) => {
        const keys = [
            'area',
            'area',
            'worker_count',
            'quantity',
            'quantity',
            'bag_size',
        ] as const;
        current.forEach((value, index) => {
            if (value !== previous[index]) form.clearErrors(keys[index]);
        });
        if (current[5] !== previous[5]) form.clearErrors('quantity');
    },
);
const fieldClass =
    'block min-h-12 w-full rounded-xl border border-border bg-background px-3 py-3 text-base text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-error disabled:bg-muted [color-scheme:light] dark:[color-scheme:dark]';
function clearPhotoErrors(): void {
    delete clientErrors.value.photos;
    for (const key of Object.keys(form.errors)) {
        if (key === 'photos' || key.startsWith('photos.'))
            form.clearErrors(key as keyof typeof form.errors);
    }
}
const { compressing, compressionStatus, fallbackNotice, preparePhotos } =
    usePhotoCompression(() => form.photos);
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
                ) || file.size > 15 * 1024 * 1024,
        )
    ) {
        clientErrors.value.photos =
            'Maksimal 5 foto JPG, PNG, atau WebP, masing-masing maksimal 15 MB.';
    } else {
        try {
            form.photos.push(...(await preparePhotos(files)));
            clearPhotoErrors();
        } catch (error) {
            clientErrors.value.photos =
                error instanceof Error
                    ? error.message
                    : 'Foto gagal diproses. Pilih ulang foto.';
        }
    }
    input.value = '';
}
function removePhoto(index: number): void {
    if (compressing.value || form.processing) return;
    form.photos.splice(index, 1);
    clearPhotoErrors();
}
function submit(): void {
    if (
        form.processing ||
        compressing.value ||
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
    if (Object.keys(clientErrors.value).length) return;
    form.post(`/petugas/subbloks/${props.cycle.id}/penanaman`, {
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
    <div class="min-w-0 space-y-[18px] text-foreground">
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
        <section class="petugas-card overflow-hidden">
            <div class="flex items-center gap-3 bg-primary/5 p-4">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <Sprout class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-primary">Penanaman</p>
                    <h2 class="mt-0.5 text-base leading-6 font-bold">
                        {{ cycle.block.name }} · {{ cycle.subblock_label }}
                    </h2>
                    <p
                        class="mt-1 flex items-start gap-1 text-xs leading-5 text-muted-foreground"
                    >
                        <MapPin
                            class="mt-0.5 size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        <span
                            >{{ farm.name }} · Batch {{ cycle.batch_number
                            }}<template v-if="cycle.farm_cycle_number">
                                · Siklus {{ cycle.farm_cycle_number }}</template
                            ></span
                        >
                    </p>
                </div>
            </div>
            <div class="space-y-2 px-4 py-3">
                <p class="text-xs leading-5 text-muted-foreground">
                    Rencana:
                    {{ cycle.planned_planting_at ?? 'Belum ditentukan' }} · Luas
                    tertanam {{ cycle.planted_area_ha }} ha
                </p>
                <p v-if="cycle.planted_at" class="text-sm font-semibold">
                    Acuan HST: {{ cycle.planted_at }} · Total ditanam
                    {{ cycle.planted_area_ha }} ha ·
                    {{ cycle.variety?.name ?? 'Varietas tidak tersedia' }}
                </p>
            </div>
        </section>
        <p
            v-if="blockedReason"
            role="status"
            class="rounded-xl border border-warning/40 bg-warning/10 p-3 text-sm"
        >
            {{ blockedReason }}
        </p>
        <form v-if="canRecord" class="space-y-4" @submit.prevent="submit">
            <!-- <p
                class="rounded-xl border border-border bg-muted/50 p-4 text-sm leading-6"
            >
                Penanaman dapat dicatat dalam tiga hari kalender sejak tanam
                pertama. Isi luas tambahan untuk pekerjaan ini. Acuan H+0 HST
                selalu ditetapkan satu hari setelah tanggal tanam pertama pada
                subblok; jadwal SOP pending menyesuaikan otomatis bila ada
                laporan tanggal lebih awal.
            </p> -->
            <p
                v-if="!commodityConfigured"
                role="status"
                class="text-sm leading-6 text-muted-foreground"
            >
                Komoditas blok belum diatur. Hubungi admin untuk mengaturnya.
            </p>
            <p
                v-else-if="varietyMismatch"
                role="status"
                class="rounded-xl border border-warning/40 bg-warning/10 p-3 text-sm leading-6"
            >
                Varietas yang sudah tercatat tidak sesuai dengan komoditas
                penanaman. Hubungi admin untuk meninjau data sebelum menambah
                laporan penanaman.
            </p>
            <p
                v-else-if="!varieties.length"
                role="status"
                class="text-sm leading-6 text-muted-foreground"
            >
                Belum ada varietas untuk komoditas blok ini. Hubungi admin untuk
                menyiapkan data varietas.
            </p>
            <fieldset
                :disabled="
                    form.processing || !varieties.length || varietyMismatch
                "
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
                    <label class="block space-y-2 text-sm">
                        <span class="font-medium">Tanggal tanam aktual *</span>
                        <input
                            v-model="form.work_date"
                            type="date"
                            required
                            :max="today"
                            :class="fieldClass"
                            :aria-invalid="!!errors.work_date"
                            :aria-describedby="
                                errors.work_date
                                    ? 'planting-date-error'
                                    : undefined
                            "
                        />
                        <span
                            v-if="errors.work_date"
                            id="planting-date-error"
                            class="block text-xs text-error"
                            >{{ errors.work_date }}</span
                        >
                    </label>
                </section>
                <section class="petugas-card space-y-4 p-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-info/10 text-info"
                        >
                            <SquarePen class="size-5" aria-hidden="true" />
                        </span>
                        <h2 class="text-base font-semibold">Hasil penanaman</h2>
                    </div>
                    <ActivityWorkerCount
                        v-model="form.worker_count"
                        :maximum="numericLimits.workerCount"
                        :error="errors.worker_count"
                    />
                    <div class="space-y-2">
                        <label
                            for="planting-variety"
                            class="text-sm font-medium"
                        >
                            Varietas *
                        </label>
                        <AppSelect
                            id="planting-variety"
                            v-model="form.variety_id"
                            required
                            aria-label="Varietas"
                            placeholder="Pilih varietas"
                            :disabled="
                                form.processing ||
                                !varieties.length ||
                                varietyMismatch
                            "
                            :class="fieldClass"
                            :aria-invalid="!!errors.variety_id"
                            :aria-describedby="
                                errors.variety_id
                                    ? 'planting-variety-error'
                                    : undefined
                            "
                        >
                            <AppSelectOption
                                v-for="variety in varieties"
                                :key="variety.id"
                                :value="variety.id"
                            >
                                {{ variety.name }}
                            </AppSelectOption>
                        </AppSelect>
                        <p
                            v-if="errors.variety_id"
                            id="planting-variety-error"
                            class="text-xs text-error"
                        >
                            {{ errors.variety_id }}
                        </p>
                    </div>
                    <div
                        v-for="field in fields"
                        :key="field.key"
                        class="space-y-3"
                    >
                        <div
                            :class="
                                field.key === 'area' || field.key === 'quantity'
                                    ? 'grid grid-cols-[minmax(0,1fr)_minmax(5.75rem,0.55fr)] gap-3'
                                    : ''
                            "
                        >
                            <label class="block min-w-0 space-y-2 text-sm">
                                <span
                                    class="font-medium"
                                    :class="{
                                        'block min-h-10':
                                            field.key === 'area' ||
                                            field.key === 'quantity',
                                    }"
                                    >{{ field.label
                                    }}{{ field.optional ? '' : ' *' }}</span
                                >
                                <input
                                    v-model="form[field.key]"
                                    :type="field.type"
                                    :required="!field.optional"
                                    :min="
                                        field.key === 'area'
                                            ? form.area_unit === 'm²'
                                                ? 1
                                                : 0.0001
                                            : field.min
                                    "
                                    :max="
                                        field.key === 'area'
                                            ? maximumArea
                                            : field.max
                                    "
                                    :step="
                                        field.key === 'area'
                                            ? form.area_unit === 'm²'
                                                ? 1
                                                : 0.0001
                                            : field.step
                                    "
                                    :maxlength="
                                        field.type === 'text' ? 150 : undefined
                                    "
                                    :inputmode="
                                        field.type === 'number'
                                            ? 'decimal'
                                            : undefined
                                    "
                                    :class="fieldClass"
                                    :aria-invalid="!!errors[field.key]"
                                    :aria-describedby="
                                        [
                                            field.key === 'area' || field.max
                                                ? `planting-help-${field.key}`
                                                : null,
                                            errors[field.key]
                                                ? `planting-error-${field.key}`
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' ') || undefined
                                    "
                                />
                                <span
                                    v-if="field.key === 'area'"
                                    :id="`planting-help-${field.key}`"
                                    class="block text-xs text-muted-foreground"
                                    >Maksimal
                                    {{ formatActivityNumber(maximumArea ?? 0) }}
                                    {{ form.area_unit }} untuk laporan
                                    ini.</span
                                >
                                <span
                                    v-else-if="field.max"
                                    :id="`planting-help-${field.key}`"
                                    class="block text-xs text-muted-foreground"
                                    >Maksimal
                                    {{
                                        formatActivityNumber(Number(field.max))
                                    }}.</span
                                >
                                <span
                                    v-if="errors[field.key]"
                                    :id="`planting-error-${field.key}`"
                                    class="block text-xs text-error"
                                    aria-live="polite"
                                    >{{ errors[field.key] }}</span
                                >
                            </label>
                            <div
                                v-if="field.key === 'area'"
                                class="min-w-0 space-y-2"
                            >
                                <label
                                    for="planting-area-unit"
                                    class="block min-h-10 text-sm font-medium"
                                    >Satuan luas *</label
                                >
                                <AppSelect
                                    id="planting-area-unit"
                                    v-model="form.area_unit"
                                    aria-label="Satuan luas"
                                    :class="fieldClass"
                                    :aria-invalid="!!errors.area_unit"
                                    :aria-describedby="
                                        errors.area_unit
                                            ? 'planting-area-unit-error'
                                            : undefined
                                    "
                                >
                                    <AppSelectOption value="m²"
                                        >m²</AppSelectOption
                                    >
                                    <AppSelectOption value="Ha"
                                        >Ha</AppSelectOption
                                    >
                                </AppSelect>
                                <p
                                    v-if="errors.area_unit"
                                    id="planting-area-unit-error"
                                    class="text-xs text-error"
                                >
                                    {{ errors.area_unit }}
                                </p>
                            </div>
                            <div
                                v-if="field.key === 'quantity'"
                                class="min-w-0 space-y-2"
                            >
                                <label
                                    for="planting-quantity-unit"
                                    class="block min-h-10 text-sm font-medium"
                                    >Satuan benih *</label
                                >
                                <AppSelect
                                    id="planting-quantity-unit"
                                    v-model="form.quantity_unit"
                                    aria-label="Satuan benih"
                                    :class="fieldClass"
                                    :aria-invalid="!!errors.quantity_unit"
                                    :aria-describedby="
                                        errors.quantity_unit
                                            ? 'planting-quantity-unit-error'
                                            : undefined
                                    "
                                >
                                    <AppSelectOption value="kg"
                                        >kg</AppSelectOption
                                    >
                                    <AppSelectOption value="sak"
                                        >sak</AppSelectOption
                                    >
                                    <AppSelectOption value="butir"
                                        >butir</AppSelectOption
                                    >
                                </AppSelect>
                                <p
                                    v-if="errors.quantity_unit"
                                    id="planting-quantity-unit-error"
                                    class="text-xs text-error"
                                >
                                    {{ errors.quantity_unit }}
                                </p>
                            </div>
                        </div>
                        <label
                            v-if="
                                field.key === 'quantity' &&
                                form.quantity_unit === 'sak'
                            "
                            class="block space-y-2 text-sm"
                        >
                            <span class="font-medium"
                                >Berat per sak (kg) *</span
                            >
                            <input
                                v-model="form.bag_size"
                                type="number"
                                required
                                min="0.001"
                                step="0.001"
                                inputmode="decimal"
                                :max="numericLimits.bagSize"
                                :class="fieldClass"
                                :aria-invalid="!!errors.bag_size"
                                :aria-describedby="
                                    errors.bag_size
                                        ? 'planting-bag-help planting-error-bag_size'
                                        : 'planting-bag-help'
                                "
                            />
                            <span
                                id="planting-bag-help"
                                class="block text-xs text-muted-foreground"
                            >
                                Maksimal
                                {{
                                    formatActivityNumber(numericLimits.bagSize)
                                }}
                                kg per sak.
                            </span>
                            <span
                                v-if="errors.bag_size"
                                id="planting-error-bag_size"
                                class="block text-xs text-error"
                                aria-live="polite"
                                >{{ errors.bag_size }}</span
                            >
                        </label>
                    </div>
                </section>
                <section class="petugas-card space-y-4 p-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <FileText class="size-5" aria-hidden="true" />
                        </span>
                        <h2 class="text-base font-semibold">Bukti penanaman</h2>
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
                        Pelapor diambil dari akun yang sedang masuk.
                    </p>
                    <details
                        class="group rounded-xl bg-muted/50"
                        :open="notesOpen"
                        @toggle="syncNotesOpen"
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
                                for="planting-notes"
                                class="block text-sm font-medium"
                                >Catatan (opsional)</label
                            >
                            <textarea
                                id="planting-notes"
                                v-model="form.notes"
                                rows="3"
                                maxlength="5000"
                                placeholder="Pola tanam, kedalaman, jarak tanam, atau kondisi benih/lahan..."
                                :class="fieldClass"
                                :aria-invalid="!!errors.notes"
                                :aria-describedby="
                                    errors.notes
                                        ? 'planting-notes-error'
                                        : undefined
                                "
                            />
                            <p
                                v-if="errors.notes"
                                id="planting-notes-error"
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
                class="rounded-xl border border-error/30 bg-error/10 p-3 text-sm leading-6"
            >
                {{
                    errors.cycle ??
                    'Periksa isian yang ditandai sebelum menyimpan.'
                }}
            </p>
            <p
                v-if="areaLimitM2 === 0"
                role="status"
                class="text-sm text-warning"
            >
                Tidak ada sisa luas untuk penanaman tambahan pada subblok ini.
            </p>
            <Button
                type="submit"
                class="min-h-12 w-full rounded-xl"
                :disabled="
                    form.processing ||
                    compressing ||
                    !varieties.length ||
                    varietyMismatch ||
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
                    · {{ report.detail.area_ha }} ha
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
