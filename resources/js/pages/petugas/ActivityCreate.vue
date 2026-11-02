<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Camera,
    CheckCircle2,
    ChevronDown,
    Clock3,
    FileImage,
    Info,
    MapPin,
    Save,
    Users,
    X,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import AreaField from '@/components/petugas/activity-form/AreaField.vue';
import SelectField from '@/components/petugas/activity-form/SelectField.vue';
import TextAreaField from '@/components/petugas/activity-form/TextAreaField.vue';
import TextField from '@/components/petugas/activity-form/TextField.vue';

type ActivityCategory =
    | 'land_cleaning'
    | 'soil_preparation'
    | 'planting'
    | 'maintenance'
    | 'harvest'
    | 'incident'
    | 'other';

type DetailValues = Record<string, string>;

const page = usePage();
const today = new Date().toISOString().slice(0, 10);
const errors = reactive<Record<string, string>>({});
const submitted = ref(false);
const form = reactive({
    workDate: today,
    startTime: '07:00',
    endTime: '16:00',
    category: 'land_cleaning' as ActivityCategory,
    subcategory: '',
    blockLabel: '',
    workerCount: 1,
    photos: [] as File[],
    notes: '',
    details: {} as DetailValues,
});

const categoryOptions: Array<{ value: ActivityCategory; label: string }> = [
    { value: 'land_cleaning', label: 'Pembersihan lahan' },
    { value: 'soil_preparation', label: 'Pengolahan tanah' },
    { value: 'planting', label: 'Penanaman' },
    { value: 'maintenance', label: 'Perawatan' },
    { value: 'harvest', label: 'Panen' },
    { value: 'incident', label: 'Pelaporan insiden' },
    { value: 'other', label: 'Lainnya' },
];

const subcategoryOptions = computed(() => {
    if (form.category === 'soil_preparation') {
        return [
            ['loosening', 'Penggemburan'],
            ['beds', 'Pembuatan bedengan'],
            ['liming', 'Penebaran dolomit'],
            ['base_fertilization', 'Pemupukan dasar'],
        ];
    }

    if (form.category === 'maintenance') {
        return [
            ['irrigation', 'Pengairan'],
            ['weeding', 'Pendangiran'],
            ['follow_up_fertilization', 'Pemupukan susulan'],
            ['growth_check', 'Pengecekan pertumbuhan'],
            ['pest_control', 'Pengendalian hama dan penyakit'],
        ];
    }

    return [] as Array<[string, string]>;
});

const categoryLabel = computed(
    () =>
        categoryOptions.find((option) => option.value === form.category)
            ?.label ?? 'Aktivitas',
);
const reporterName = computed(() => page.props.auth.user?.name ?? 'Petugas');
const needsSubcategory = computed(
    () =>
        form.category === 'soil_preparation' || form.category === 'maintenance',
);

function changeCategory(): void {
    form.subcategory = '';
    form.details = {};
    submitted.value = false;
}

function clearError(field: string): void {
    delete errors[field];
}

function onPhotosSelected(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.photos.push(...Array.from(target.files ?? []));
    target.value = '';
    clearError('photos');
}

function removePhoto(index: number): void {
    form.photos.splice(index, 1);
}

function validate(): boolean {
    Object.keys(errors).forEach((key) => delete errors[key]);
    submitted.value = false;

    if (!form.workDate || form.workDate > today) {
        errors.workDate =
            'Tanggal kerja wajib diisi dan tidak boleh di masa depan.';
    }
    if (!form.startTime || !form.endTime || form.endTime <= form.startTime) {
        errors.time = 'Waktu selesai harus setelah waktu mulai.';
    }
    if (!needsSubcategory.value && !form.category) {
        errors.category = 'Pilih kategori aktivitas.';
    }
    if (needsSubcategory.value && !form.subcategory) {
        errors.subcategory = 'Pilih subkategori aktivitas.';
    }
    if (
        !Number.isInteger(Number(form.workerCount)) ||
        Number(form.workerCount) < 1
    ) {
        errors.workerCount = 'Jumlah pekerja minimal satu orang.';
    }
    if (!form.photos.length) {
        errors.photos = 'Minimal satu foto aktivitas diperlukan.';
    }
    if (form.category === 'incident' && !form.details.description?.trim()) {
        errors.description = 'Deskripsi kejadian wajib diisi untuk insiden.';
    }
    if (form.category === 'other' && !form.details.description?.trim()) {
        errors.description = 'Deskripsi aktivitas wajib diisi.';
    }

    return Object.keys(errors).length === 0;
}

function submit(): void {
    if (!validate()) return;

    // Deliberately local: persistence/API wiring is outside this UI-only slice.
    submitted.value = true;
}
</script>

<template>
    <Head title="Catat Aktivitas" />
    <header class="flex items-start gap-3">
        <Link
            href="/petugas/aktivitas"
            class="mt-0.5 grid size-10 shrink-0 place-items-center rounded-xl border border-border bg-card text-foreground transition-colors hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            aria-label="Kembali ke aktivitas"
        >
            <ArrowLeft :size="19" aria-hidden="true" />
        </Link>
        <div>
            <p class="text-xs font-medium tracking-wide text-muted-foreground">
                Aktivitas lapangan
            </p>
            <h1 class="mt-1 text-2xl leading-8 font-semibold text-foreground">
                Catat aktivitas
            </h1>
            <p class="mt-1 text-sm leading-6 text-muted-foreground">
                Satu laporan untuk satu kegiatan di kebun.
            </p>
        </div>
    </header>

    <section
        class="mt-5 rounded-2xl border border-primary/20 bg-primary/10 p-4"
    >
        <div class="flex gap-3">
            <Info
                :size="19"
                class="mt-0.5 shrink-0 text-secondary"
                aria-hidden="true"
            />
            <p class="text-sm leading-6 text-foreground">
                Ini adalah rancangan form. Isian dapat diperiksa di perangkat,
                tetapi belum disimpan ke logbook atau database.
            </p>
        </div>
    </section>

    <form class="mt-5 space-y-4 pb-2" novalidate @submit.prevent="submit">
        <section class="rounded-2xl border border-border bg-card p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground"
                    >
                        1. Informasi kerja
                    </p>
                    <h2 class="mt-1 text-base font-semibold text-foreground">
                        Waktu dan pelapor
                    </h2>
                </div>
                <CalendarDays
                    :size="21"
                    class="text-primary"
                    aria-hidden="true"
                />
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <label class="col-span-2 space-y-1.5">
                    <span class="petugas-label">Tanggal kerja <em>*</em></span>
                    <input
                        v-model="form.workDate"
                        :max="today"
                        type="date"
                        class="petugas-field"
                        @input="clearError('workDate')"
                    />
                    <p v-if="errors.workDate" class="petugas-error">
                        {{ errors.workDate }}
                    </p>
                </label>
                <label class="space-y-1.5">
                    <span class="petugas-label">Waktu mulai <em>*</em></span>
                    <input
                        v-model="form.startTime"
                        type="time"
                        class="petugas-field"
                        @input="clearError('time')"
                    />
                </label>
                <label class="space-y-1.5">
                    <span class="petugas-label">Waktu selesai <em>*</em></span>
                    <input
                        v-model="form.endTime"
                        type="time"
                        class="petugas-field"
                        @input="clearError('time')"
                    />
                </label>
            </div>
            <p v-if="errors.time" class="petugas-error mt-2">
                {{ errors.time }}
            </p>
            <div
                class="mt-4 flex items-center gap-3 rounded-xl bg-muted/60 p-3"
            >
                <span
                    class="grid size-9 place-items-center rounded-lg bg-card text-primary"
                    ><Users :size="17" aria-hidden="true"
                /></span>
                <div>
                    <p class="text-xs text-muted-foreground">Pelapor</p>
                    <p class="text-sm font-semibold text-foreground">
                        {{ reporterName }}
                    </p>
                </div>
                <span class="ml-auto text-xs text-muted-foreground"
                    >Diisi sistem</span
                >
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground"
                    >
                        2. Jenis kegiatan
                    </p>
                    <h2 class="mt-1 text-base font-semibold text-foreground">
                        Apa yang dikerjakan?
                    </h2>
                </div>
                <MapPin :size="21" class="text-primary" aria-hidden="true" />
            </div>

            <label class="mt-4 block space-y-1.5">
                <span class="petugas-label">Kategori <em>*</em></span>
                <span class="relative block">
                    <select
                        v-model="form.category"
                        class="petugas-field appearance-none pr-10"
                        @change="changeCategory"
                    >
                        <option
                            v-for="option in categoryOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <ChevronDown
                        :size="18"
                        class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                </span>
                <p v-if="errors.category" class="petugas-error">
                    {{ errors.category }}
                </p>
            </label>

            <label v-if="needsSubcategory" class="mt-4 block space-y-1.5">
                <span class="petugas-label">Subkategori <em>*</em></span>
                <span class="relative block">
                    <select
                        v-model="form.subcategory"
                        class="petugas-field appearance-none pr-10"
                        @change="clearError('subcategory')"
                    >
                        <option value="" disabled>Pilih subkategori</option>
                        <option
                            v-for="[value, label] in subcategoryOptions"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </option>
                    </select>
                    <ChevronDown
                        :size="18"
                        class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                </span>
                <p v-if="errors.subcategory" class="petugas-error">
                    {{ errors.subcategory }}
                </p>
            </label>

            <label class="mt-4 block space-y-1.5">
                <span class="petugas-label"
                    >Blok / sub-blok <span>(opsional untuk Lainnya)</span></span
                >
                <input
                    v-model="form.blockLabel"
                    type="text"
                    class="petugas-field"
                    placeholder="Contoh: Blok A1"
                />
                <p class="text-xs leading-5 text-muted-foreground">
                    Sementara berupa label. Nantinya pilihan akan berasal dari
                    master blok/sub-blok.
                </p>
            </label>
        </section>

        <section class="rounded-2xl border border-border bg-card p-4 shadow-sm">
            <div>
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground"
                >
                    3. Detail {{ categoryLabel.toLowerCase() }}
                </p>
                <h2 class="mt-1 text-base font-semibold text-foreground">
                    Isian lapangan
                </h2>
            </div>

            <div
                v-if="form.category === 'land_cleaning'"
                class="mt-4 space-y-3"
            >
                <AreaField
                    v-model:value="form.details.area"
                    v-model:unit="form.details.areaUnit"
                />
                <SelectField
                    v-model="form.details.method"
                    label="Metode"
                    :options="[
                        ['manual', 'Manual'],
                        ['herbicide', 'Herbisida'],
                        ['other', 'Lainnya'],
                    ]"
                />
                <div
                    v-if="form.details.method === 'herbicide'"
                    class="grid grid-cols-2 gap-3 rounded-xl bg-muted/40 p-3"
                >
                    <TextField
                        v-model="form.details.product"
                        label="Nama produk"
                    />
                    <TextField
                        v-model="form.details.dosage"
                        label="Dosis per tangki (ml/L)"
                        inputmode="decimal"
                    />
                    <TextField
                        v-model="form.details.waterVolume"
                        label="Volume air (liter)"
                        inputmode="decimal"
                    />
                    <TextField
                        v-model="form.details.tankCount"
                        label="Jumlah tangki"
                        inputmode="numeric"
                    />
                </div>
            </div>

            <div
                v-else-if="form.category === 'soil_preparation'"
                class="mt-4 space-y-3"
            >
                <p
                    v-if="!form.subcategory"
                    class="rounded-xl bg-muted/60 p-3 text-sm leading-6 text-muted-foreground"
                >
                    Pilih subkategori terlebih dahulu untuk menampilkan field
                    yang sesuai.
                </p>
                <template v-else-if="form.subcategory === 'loosening'">
                    <AreaField
                        v-model:value="form.details.area"
                        v-model:unit="form.details.areaUnit"
                    />
                    <div class="grid grid-cols-2 gap-3">
                        <SelectField
                            v-model="form.details.method"
                            label="Metode"
                            :options="[
                                ['manual', 'Manual'],
                                ['cultivator', 'Cultivator'],
                                ['other', 'Lainnya'],
                            ]"
                        /><TextField
                            v-model="form.details.depth"
                            label="Kedalaman (cm)"
                            inputmode="decimal"
                        />
                    </div>
                </template>
                <template v-else-if="form.subcategory === 'beds'">
                    <div class="grid grid-cols-2 gap-3">
                        <TextField
                            v-model="form.details.bedCount"
                            label="Jumlah bedeng"
                            inputmode="numeric"
                        /><TextField
                            v-model="form.details.length"
                            label="Panjang (m)"
                            inputmode="decimal"
                        /><TextField
                            v-model="form.details.width"
                            label="Lebar (m)"
                            inputmode="decimal"
                        /><TextField
                            v-model="form.details.height"
                            label="Tinggi (cm)"
                            optional
                            inputmode="decimal"
                        />
                    </div>
                    <TextField
                        v-model="form.details.spacing"
                        label="Jarak antar bedeng (cm)"
                        optional
                        inputmode="decimal"
                    />
                </template>
                <template v-else>
                    <AreaField
                        v-model:value="form.details.area"
                        v-model:unit="form.details.areaUnit"
                    />
                    <div class="grid grid-cols-2 gap-3">
                        <SelectField
                            v-model="form.details.material"
                            :label="
                                form.subcategory === 'liming'
                                    ? 'Jenis material'
                                    : 'Jenis pupuk'
                            "
                            :options="
                                form.subcategory === 'liming'
                                    ? [
                                          ['dolomite', 'Dolomit'],
                                          [
                                              'agricultural_lime',
                                              'Kapur pertanian',
                                          ],
                                          ['other', 'Lainnya'],
                                      ]
                                    : [
                                          ['manure', 'Kandang'],
                                          ['compost', 'Kompos'],
                                          ['other', 'Lainnya'],
                                      ]
                            "
                        /><TextField
                            v-model="form.details.brand"
                            label="Merek"
                            optional
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <TextField
                            v-model="form.details.amount"
                            label="Jumlah"
                            inputmode="decimal"
                        /><SelectField
                            v-model="form.details.amountUnit"
                            label="Satuan"
                            :options="[
                                ['kg', 'kg'],
                                ['sack', 'sak'],
                                ['ton', 'ton'],
                            ]"
                        />
                    </div>
                    <TextField
                        v-if="form.details.amountUnit === 'sack'"
                        v-model="form.details.sackSize"
                        label="Ukuran sak (kg)"
                        inputmode="decimal"
                    />
                    <SelectField
                        v-model="form.details.applicationMethod"
                        label="Metode"
                        :options="
                            form.subcategory === 'liming'
                                ? [
                                      ['spread', 'Tabur'],
                                      ['cover', 'Cover'],
                                      ['other', 'Lainnya'],
                                  ]
                                : [
                                      ['spread', 'Tabur'],
                                      ['incorporate', 'Benam'],
                                      ['other', 'Lainnya'],
                                  ]
                        "
                    />
                </template>
            </div>

            <div
                v-else-if="form.category === 'planting'"
                class="mt-4 space-y-3"
            >
                <TextField v-model="form.details.variety" label="Varietas" />
                <div class="grid grid-cols-2 gap-3">
                    <TextField
                        v-model="form.details.seedAmount"
                        label="Jumlah benih"
                        inputmode="decimal"
                    /><SelectField
                        v-model="form.details.seedUnit"
                        label="Satuan"
                        :options="[
                            ['kg', 'kg'],
                            ['sack', 'sak'],
                            ['seed', 'butir'],
                        ]"
                    />
                </div>
                <TextField
                    v-if="form.details.seedUnit === 'sack'"
                    v-model="form.details.sackSize"
                    label="Ukuran sak (kg)"
                    inputmode="decimal"
                />
                <TextField
                    v-model="form.details.pattern"
                    label="Pola tanam"
                    optional
                    placeholder="Akan terisi dari pemetaan blok"
                />
                <div class="grid grid-cols-2 gap-3">
                    <AreaField
                        v-model:value="form.details.area"
                        v-model:unit="form.details.areaUnit"
                    /><TextField
                        v-model="form.details.depth"
                        label="Kedalaman tanam (cm)"
                        optional
                        inputmode="decimal"
                    />
                </div>
            </div>

            <div
                v-else-if="form.category === 'maintenance'"
                class="mt-4 space-y-3"
            >
                <p
                    v-if="!form.subcategory"
                    class="rounded-xl bg-muted/60 p-3 text-sm leading-6 text-muted-foreground"
                >
                    Pilih subkategori terlebih dahulu untuk menampilkan field
                    yang sesuai.
                </p>
                <template v-else-if="form.subcategory === 'irrigation'">
                    <AreaField
                        v-model:value="form.details.area"
                        v-model:unit="form.details.areaUnit"
                    />
                    <div class="grid grid-cols-2 gap-3">
                        <SelectField
                            v-model="form.details.waterSource"
                            label="Sumber air"
                            :options="[
                                ['well', 'Sumur bor'],
                                ['irrigation', 'Irigasi'],
                                ['reservoir', 'Embung'],
                                ['other', 'Lainnya'],
                            ]"
                        /><SelectField
                            v-model="form.details.method"
                            label="Metode"
                            :options="[
                                ['ditch', 'Irigasi parit'],
                                ['sprinkler', 'Sprinkler'],
                                ['pump', 'Pompa'],
                                ['other', 'Lainnya'],
                            ]"
                        />
                    </div>
                    <TextField
                        v-if="form.details.method === 'pump'"
                        v-model="form.details.pump"
                        label="Mesin pompa"
                    /><TextField
                        v-model="form.details.duration"
                        label="Durasi (jam)"
                        inputmode="decimal"
                    />
                </template>
                <template v-else-if="form.subcategory === 'weeding'">
                    <AreaField
                        v-model:value="form.details.area"
                        v-model:unit="form.details.areaUnit"
                    />
                    <div class="grid grid-cols-2 gap-3">
                        <TextField
                            v-model="form.details.depth"
                            label="Kedalaman (cm)"
                            optional
                            inputmode="decimal"
                        /><SelectField
                            v-model="form.details.method"
                            label="Metode"
                            :options="[
                                ['manual', 'Manual'],
                                ['hoe', 'Cangkul'],
                                ['machine', 'Mesin'],
                                ['other', 'Lainnya'],
                            ]"
                        />
                    </div>
                </template>
                <template
                    v-else-if="form.subcategory === 'follow_up_fertilization'"
                >
                    <AreaField
                        v-model:value="form.details.area"
                        v-model:unit="form.details.areaUnit"
                    />
                    <div class="grid grid-cols-2 gap-3">
                        <SelectField
                            v-model="form.details.fertilizer"
                            label="Jenis pupuk"
                            :options="[
                                ['urea', 'Urea'],
                                ['npk', 'NPK'],
                                ['kcl', 'KCl'],
                                ['other', 'Lainnya'],
                            ]"
                        /><TextField
                            v-model="form.details.amount"
                            label="Jumlah"
                            inputmode="decimal"
                        />
                    </div>
                    <SelectField
                        v-model="form.details.method"
                        label="Metode"
                        :options="[
                            ['spread', 'Tabur'],
                            ['incorporate', 'Benam'],
                            ['drench', 'Kocor'],
                            ['other', 'Lainnya'],
                        ]"
                    />
                </template>
                <template v-else-if="form.subcategory === 'growth_check'">
                    <SelectField
                        v-model="form.details.phase"
                        label="Fase pertumbuhan"
                        :options="[
                            ['vegetative', 'Vegetatif'],
                            ['flowering', 'Pembungaan'],
                            ['filling', 'Pengisian'],
                            ['ripening', 'Pemasakan'],
                            ['other', 'Lainnya'],
                        ]"
                    />
                    <div class="grid grid-cols-2 gap-3">
                        <TextField
                            v-model="form.details.height"
                            label="Tinggi rata-rata (cm)"
                            inputmode="decimal"
                        /><TextField
                            v-model="form.details.sampleCount"
                            label="Jumlah sampel"
                            optional
                            inputmode="numeric"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <SelectField
                            v-model="form.details.pest"
                            label="Indikasi hama"
                            :options="[
                                ['safe', 'Aman'],
                                ['light', 'Ringan'],
                                ['medium', 'Sedang'],
                                ['heavy', 'Berat'],
                            ]"
                        /><SelectField
                            v-model="form.details.disease"
                            label="Indikasi penyakit"
                            :options="[
                                ['safe', 'Aman'],
                                ['light', 'Ringan'],
                                ['medium', 'Sedang'],
                                ['heavy', 'Berat'],
                            ]"
                        />
                    </div>
                </template>
                <template v-else>
                    <AreaField
                        v-model:value="form.details.area"
                        v-model:unit="form.details.areaUnit"
                    />
                    <div class="grid grid-cols-2 gap-3">
                        <SelectField
                            v-model="form.details.controlType"
                            label="Jenis"
                            :options="[
                                ['insecticide', 'Insektisida'],
                                ['fungicide', 'Fungisida'],
                                ['pesticide', 'Pestisida'],
                                ['other', 'Lainnya'],
                            ]"
                        /><TextField
                            v-model="form.details.target"
                            label="Target"
                        />
                    </div>
                    <TextField
                        v-model="form.details.product"
                        label="Nama produk"
                    />
                    <div class="grid grid-cols-2 gap-3">
                        <TextField
                            v-model="form.details.dosage"
                            label="Dosis per tangki (ml/L)"
                            inputmode="decimal"
                        /><TextField
                            v-model="form.details.waterVolume"
                            label="Volume air (liter)"
                            inputmode="decimal"
                        /><TextField
                            v-model="form.details.tankCount"
                            label="Jumlah tangki"
                            inputmode="numeric"
                        /><SelectField
                            v-model="form.details.method"
                            label="Metode"
                            :options="[
                                ['even', 'Merata'],
                                ['spot', 'Spot'],
                                ['other', 'Lainnya'],
                            ]"
                        />
                    </div>
                </template>
            </div>

            <div v-else-if="form.category === 'harvest'" class="mt-4 space-y-3">
                <AreaField
                    v-model:value="form.details.area"
                    v-model:unit="form.details.areaUnit"
                />
                <div class="grid grid-cols-2 gap-3">
                    <TextField
                        v-model="form.details.weight"
                        label="Berat hasil panen"
                        inputmode="decimal"
                    /><SelectField
                        v-model="form.details.weightUnit"
                        label="Satuan"
                        :options="[
                            ['kg', 'kg'],
                            ['ton', 'ton'],
                        ]"
                    />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <TextField
                        v-model="form.details.moisture"
                        label="Kadar air (%)"
                        optional
                        inputmode="decimal"
                    /><SelectField
                        v-model="form.details.method"
                        label="Metode panen"
                        optional
                        :options="[
                            ['manual', 'Manual'],
                            ['machine', 'Mesin'],
                            ['other', 'Lainnya'],
                        ]"
                    />
                </div>
            </div>

            <div
                v-else-if="form.category === 'incident'"
                class="mt-4 space-y-3"
            >
                <SelectField
                    v-model="form.details.incidentCategory"
                    label="Kategori insiden"
                    :options="[
                        ['pest', 'Hama'],
                        ['disease', 'Penyakit'],
                        ['weather', 'Cuaca'],
                        ['flood', 'Banjir'],
                        ['equipment', 'Alat rusak'],
                        ['labor', 'Tenaga kerja'],
                        ['other', 'Lainnya'],
                    ]"
                /><TextAreaField
                    v-model="form.details.description"
                    label="Deskripsi kejadian"
                    required
                />
                <p v-if="errors.description" class="petugas-error">
                    {{ errors.description }}
                </p>
                <AreaField
                    v-model:value="form.details.area"
                    v-model:unit="form.details.areaUnit"
                />
                <div class="grid grid-cols-2 gap-3">
                    <SelectField
                        v-model="form.details.severity"
                        label="Tingkat kedaruratan"
                        :options="[
                            ['light', 'Ringan'],
                            ['medium', 'Sedang'],
                            ['heavy', 'Berat'],
                            ['emergency', 'Darurat'],
                        ]"
                    /><SelectField
                        v-model="form.details.status"
                        label="Status"
                        optional
                        :options="[
                            ['open', 'Open'],
                            ['progress', 'In Progress'],
                            ['resolved', 'Resolved'],
                        ]"
                    />
                </div>
                <TextAreaField
                    v-model="form.details.immediateAction"
                    label="Tindakan segera"
                    optional
                />
            </div>

            <div v-else-if="form.category === 'other'" class="mt-4 space-y-3">
                <TextAreaField
                    v-model="form.details.description"
                    label="Deskripsi aktivitas"
                    required
                />
                <p v-if="errors.description" class="petugas-error">
                    {{ errors.description }}
                </p>
                <AreaField
                    v-model:value="form.details.area"
                    v-model:unit="form.details.areaUnit"
                    optional
                />
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card p-4 shadow-sm">
            <div>
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground"
                >
                    4. Bukti dan tim
                </p>
                <h2 class="mt-1 text-base font-semibold text-foreground">
                    Dokumentasi aktivitas
                </h2>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <label class="space-y-1.5"
                    ><span class="petugas-label">Jumlah pekerja <em>*</em></span
                    ><input
                        v-model.number="form.workerCount"
                        type="number"
                        min="1"
                        class="petugas-field"
                        @input="clearError('workerCount')"
                    />
                    <p v-if="errors.workerCount" class="petugas-error">
                        {{ errors.workerCount }}
                    </p></label
                >
                <div
                    class="rounded-xl bg-muted/60 p-3 text-xs leading-5 text-muted-foreground"
                >
                    Dicatat per aktivitas agar jumlah tim dapat berbeda pada
                    pekerjaan berikutnya.
                </div>
            </div>
            <div class="mt-4">
                <span class="petugas-label">Foto aktivitas <em>*</em></span
                ><label
                    class="mt-2 flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-border bg-muted/50 px-4 text-center shadow-sm transition-colors focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 hover:border-primary hover:bg-primary/5"
                    ><Camera
                        :size="24"
                        class="text-primary"
                        aria-hidden="true" /><span
                        class="mt-2 text-sm font-semibold text-foreground"
                        >Tambahkan foto</span
                    ><span class="mt-1 text-xs leading-5 text-muted-foreground"
                        >Minimal satu foto. JPG, PNG, atau WebP.</span
                    ><input
                        class="sr-only"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        @change="onPhotosSelected"
                /></label>
                <p v-if="errors.photos" class="petugas-error mt-2">
                    {{ errors.photos }}
                </p>
                <ul
                    v-if="form.photos.length"
                    class="mt-3 space-y-2"
                    aria-label="Foto yang dipilih"
                >
                    <li
                        v-for="(photo, index) in form.photos"
                        :key="`${photo.name}-${index}`"
                        class="flex items-center gap-2 rounded-lg bg-muted/60 px-3 py-2"
                    >
                        <FileImage
                            :size="17"
                            class="shrink-0 text-primary"
                            aria-hidden="true"
                        /><span
                            class="min-w-0 flex-1 truncate text-xs font-medium text-foreground"
                            >{{ photo.name }}</span
                        ><button
                            type="button"
                            class="rounded p-1 text-muted-foreground hover:bg-card hover:text-error focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            :aria-label="`Hapus ${photo.name}`"
                            @click="removePhoto(index)"
                        >
                            <X :size="16" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
            </div>
            <label class="mt-4 block space-y-1.5"
                ><span class="petugas-label"
                    >Catatan aktivitas <span>(opsional)</span></span
                ><textarea
                    v-model="form.notes"
                    rows="3"
                    class="petugas-field resize-y"
                    placeholder="Catatan tambahan yang belum tercakup di field di atas."
                />
            </label>
        </section>

        <section
            v-if="submitted"
            class="rounded-2xl border border-success/30 bg-success/10 p-4"
            role="status"
        >
            <div class="flex gap-3">
                <CheckCircle2
                    :size="21"
                    class="mt-0.5 shrink-0 text-success"
                    aria-hidden="true"
                />
                <div>
                    <h2 class="text-sm font-semibold text-foreground">
                        Isian siap untuk integrasi
                    </h2>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground">
                        Validasi UI berhasil. Pada tahap berikutnya, data ini
                        akan dikirim ke endpoint logbook yang disesuaikan dengan
                        kategori aktivitas.
                    </p>
                </div>
            </div>
        </section>
        <p
            v-if="Object.keys(errors).length"
            class="rounded-xl border border-error/30 bg-error/10 p-3 text-sm leading-6 text-error"
            role="alert"
        >
            Periksa kembali field wajib yang ditandai sebelum melanjutkan.
        </p>
        <button
            type="submit"
            class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <Save :size="18" aria-hidden="true" />Validasi aktivitas
        </button>
    </form>
</template>

<style>
.petugas-field,
input.petugas-field,
select.petugas-field,
textarea.petugas-field {
    width: 100%;
    min-height: 44px;
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    background: var(--background);
    padding: 0.625rem 0.75rem;
    color: var(--foreground);
    font-size: 0.875rem;
    line-height: 1.25rem;
    outline: none;
    transition:
        border-color 150ms ease,
        box-shadow 150ms ease,
        background-color 150ms ease;
}

.petugas-field:hover:not(:disabled) {
    border-color: var(--primary);
    background: var(--card);
}

.petugas-field:focus-visible {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 30%, transparent);
}

.petugas-field:disabled {
    cursor: not-allowed;
    border-color: var(--border);
    background: var(--disable);
    color: var(--muted-foreground);
}

.petugas-label {
    display: block;
    color: var(--foreground);
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1rem;
}

.petugas-label span {
    color: var(--muted-foreground);
    font-weight: 400;
}

.petugas-label em {
    color: var(--error);
    font-style: normal;
}

.petugas-error {
    color: var(--error);
    font-size: 0.75rem;
    line-height: 1rem;
}
</style>
