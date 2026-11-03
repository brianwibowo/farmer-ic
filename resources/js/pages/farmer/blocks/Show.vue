<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, LoaderCircle, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BatchTimeline from '@/components/blocks/BatchTimeline.vue';
import MetricInfoTooltip from '@/components/MetricInfoTooltip.vue';
import FarmField from '@/components/farms/FarmField.vue';
import FormErrorSummary from '@/components/farms/FormErrorSummary.vue';
import {
    focusFormErrors,
    useUnsavedChanges,
} from '@/composables/useFormRecovery';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import BlockLayout from '@/layouts/BlockLayout.vue';
import { areaValue, squareMetres, type AreaUnit } from '@/lib/landArea';
import {
    blockDate,
    stageLabels,
    type BatchCapacity,
    type BlockFarm,
    type FarmBlock,
    type PreparationBatch,
} from '@/types/blocks';
import { hectares } from '@/types/farms';

defineOptions({ layout: BlockLayout, inheritAttrs: false });
const props = defineProps<{
    farm: BlockFarm;
    block: FarmBlock;
    rounds: { id: string; round_number: number; status: string }[];
    batches: PreparationBatch[];
    capacity: BatchCapacity;
    deletionBlocked: boolean;
    can: { update: boolean; delete: boolean; createBatch: boolean };
}>();
const page = usePage();
const base = `/kebun/${props.farm.id}/blocks/${props.block.id}`;
const batchForm = useForm({
    area_value: '',
    area_unit: 'ha' as AreaUnit,
    subblock_label: '',
    planned_planting_at: '',
});
useUnsavedChanges(() => batchForm.isDirty && !batchForm.processing);
const availableMetres = computed(
    () => squareMetres(props.capacity.available_ha) ?? 0,
);
const enteredMetres = computed(() =>
    squareMetres(batchForm.area_value, batchForm.area_unit),
);
const formatMetres = (value: number) =>
    new Intl.NumberFormat('id-ID').format(value);
const areaError = computed(() => {
    if (!batchForm.area_value.trim()) return null;
    if (enteredMetres.value === null)
        return batchForm.area_unit === 'm2'
            ? 'Masukkan m² bulat tanpa pemisah ribuan, misalnya 50000.'
            : 'Masukkan luas ha dengan maksimal 4 desimal, misalnya 0,5.';
    if (enteredMetres.value < 1) return 'Luas minimal 1 m² atau 0,0001 ha.';
    if (enteredMetres.value > availableMetres.value)
        return `Luas ini melebihi batas batch baru ${hectares(props.capacity.available_ha)} ha (${formatMetres(availableMetres.value)} m²). Periksa sisa blok dan kebun di atas.`;
    return null;
});
const serverAreaError = computed(
    () =>
        batchForm.errors.area_value ??
        (batchForm.errors as Record<string, string>).reserved_area_ha,
);
watch(
    () => batchForm.area_unit,
    (unit, previous) => {
        const metres = squareMetres(batchForm.area_value, previous);
        if (metres !== null) batchForm.area_value = areaValue(metres, unit);
        batchForm.clearErrors('area_value', 'area_unit');
    },
);
watch(
    () => batchForm.area_value,
    () => {
        batchForm.clearErrors('area_value');
        delete (batchForm.errors as Record<string, string>).reserved_area_ha;
    },
);
const deleteForm = useForm({});
const deleteError = computed(
    () => (deleteForm.errors as Record<string, string | undefined>).delete,
);
const deleting = ref(false);
function createBatch(): void {
    if (areaError.value || enteredMetres.value === null) {
        batchForm.setError(
            'area_value',
            areaError.value ?? 'Isi luas yang akan dialokasikan untuk batch.',
        );
        void focusFormErrors();
        return;
    }
    batchForm.post(`${base}/batches`, {
        preserveScroll: true,
        onSuccess: () => batchForm.reset(),
        onError: focusFormErrors,
    });
}
function deleteBlock(): void {
    deleteForm.delete(base, {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = false;
        },
    });
}
const fieldClass = 'admin-field block-field';
</script>

<template>
    <Head :title="`${block.name} · ${farm.name}`" />
    <Link
        :href="`/kebun/${farm.id}/blocks`"
        class="inline-flex items-center gap-2 text-sm text-muted-foreground"
        ><ArrowLeft class="size-4" />Daftar blok</Link
    >
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1
                class="admin-page-title mt-1 text-2xl font-semibold sm:text-3xl"
            >
                {{ block.name }}
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ farm.name }} / {{ block.code }} ·
                {{ hectares(block.area_ha) }} ha ·
                {{ block.is_active ? 'Aktif' : 'Nonaktif' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <Button
                v-if="can.update"
                as-child
                variant="outline"
                class="block-action h-10"
                ><Link :href="`${base}/edit`"><Pencil />Ubah blok</Link></Button
            ><Button
                v-if="can.delete"
                variant="destructive-outline"
                class="block-action h-10"
                :disabled="deletionBlocked"
                @click="deleting = true"
                ><Trash2 />Hapus blok</Button
            >
        </div>
    </header>
    <p
        v-if="can.delete && deletionBlocked"
        class="rounded-xl bg-muted p-4 text-sm text-muted-foreground"
    >
        Blok memiliki histori periode/batch atau pemetaan, sehingga tidak dapat
        dihapus. Nonaktifkan melalui Ubah blok setelah semua batch berjalan
        selesai.
    </p>
    <section class="rounded-xl border border-border bg-card p-5">
        <h2 class="admin-section-title text-lg font-semibold">
            Land Clearing
            {{ block.land_clearing_completed_at ? 'selesai' : 'berlangsung' }}
        </h2>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">Mulai</dt>
                <dd class="mt-1">
                    {{ blockDate(block.land_clearing_started_at) }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Selesai</dt>
                <dd class="mt-1">
                    {{
                        block.land_clearing_completed_at
                            ? blockDate(block.land_clearing_completed_at)
                            : 'Otomatis saat batch pertama dibuat'
                    }}
                </dd>
            </div>
        </dl>
        <p
            v-if="block.description"
            class="mt-4 text-sm whitespace-pre-line text-muted-foreground"
        >
            {{ block.description }}
        </p>
    </section>
    <section
        v-if="can.createBatch && block.is_active"
        class="grid gap-6 rounded-xl border border-border bg-card p-5 sm:p-6 xl:grid-cols-2"
    >
        <header class="xl:col-span-2">
            <h2 class="admin-section-title text-lg font-semibold">
                Buat subblok / batch
            </h2>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                Alokasikan bagian blok untuk satu batch. Pengolahan tanah
                dimulai langsung tanpa persetujuan Admin. Ini belum merupakan
                laporan realisasi tanam.
            </p>
        </header>
        <div class="space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                <div class="rounded-xl border border-border p-4">
                    <div
                        class="flex items-center justify-between gap-2 text-muted-foreground"
                    >
                        <p class="text-sm">Sisa kapasitas batch di blok ini</p>
                        <MetricInfoTooltip
                            label="Sisa kapasitas batch di blok ini"
                            description="Luas blok dikurangi reservasi batch berstatus direncanakan atau aktif dalam blok ini. Membuat blok belum memesan lahan untuk batch."
                            example="Blok 10 ha dengan reservasi batch 4 ha masih dapat menerima batch baru sampai 6 ha."
                            :content-class="
                                page.props.navigation?.farms
                                    ? 'admin-dashboard-theme'
                                    : 'farm-block-theme'
                            "
                            touch-target
                        />
                    </div>
                    <p
                        class="admin-metric-value mt-1 text-2xl font-semibold tabular-nums"
                    >
                        {{ hectares(capacity.block_remaining_ha) }}
                        <span class="text-base font-normal">ha</span>
                    </p>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Luas blok {{ hectares(capacity.block_total_ha) }} ha,
                        sudah dialokasikan untuk batch berjalan
                        {{ hectares(capacity.block_batches_ha) }} ha.
                    </p>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div
                        class="flex items-center justify-between gap-2 text-muted-foreground"
                    >
                        <p class="text-sm">
                            Sisa kapasitas batch seluruh kebun
                        </p>
                        <MetricInfoTooltip
                            label="Sisa kapasitas batch seluruh kebun"
                            description="Luas kebun dikurangi reservasi semua batch berstatus direncanakan atau aktif, serta tanam aktif di luar batch. Luas blok tidak dikurangkan lagi."
                            example="Kebun 20 ha dibagi menjadi dua blok 10 ha. Jika total reservasi batch 10 ha, sisanya 10 ha. Jika belum ada batch atau tanam aktif, sisanya tetap 20 ha."
                            :content-class="
                                page.props.navigation?.farms
                                    ? 'admin-dashboard-theme'
                                    : 'farm-block-theme'
                            "
                            touch-target
                        />
                    </div>
                    <p
                        class="admin-metric-value mt-1 text-2xl font-semibold tabular-nums"
                    >
                        {{ hectares(capacity.farm_remaining_ha) }}
                        <span class="text-base font-normal">ha</span>
                    </p>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Luas kebun {{ hectares(capacity.farm_total_ha) }} ha,
                        sudah dipesan untuk batch berjalan
                        {{ hectares(capacity.farm_batches_ha) }} ha.
                    </p>
                    <p
                        v-if="Number(capacity.legacy_active_ha) > 0"
                        class="mt-1 text-sm leading-6 text-muted-foreground"
                    >
                        Tanam aktif di luar batch masih memakai
                        {{ hectares(capacity.legacy_active_ha) }} ha dan tetap
                        dihitung sebagai lahan terpakai.
                    </p>
                </div>
            </div>
            <div class="rounded-xl bg-muted p-4 text-sm leading-6">
                <p class="font-semibold">
                    Maksimal batch baru:
                    {{ hectares(capacity.available_ha) }} ha ({{
                        formatMetres(availableMetres)
                    }}
                    m²)
                </p>
                <p class="mt-1 text-muted-foreground">
                    Batch harus muat di blok ini dan di sisa lahan seluruh
                    kebun. Luas blok bukan tambahan lahan kebun.
                </p>
                <p v-if="availableMetres === 0" class="mt-2 font-medium">
                    Kapasitas sudah terpakai. Batch baru dapat dibuat setelah
                    alokasi batch atau tanam aktif berakhir.
                </p>
            </div>
        </div>
        <form class="space-y-4" @submit.prevent="createBatch">
            <FormErrorSummary
                :errors="batchForm.errors"
                :fields="{
                    area_value: { id: 'batch-area', label: 'Luas batch' },
                    reserved_area_ha: { id: 'batch-area', label: 'Luas batch' },
                    area_unit: { id: 'batch-area-unit', label: 'Satuan luas' },
                    subblock_label: {
                        id: 'batch-label',
                        label: 'Label subblok',
                    },
                }"
            />
            <fieldset
                :disabled="batchForm.processing"
                class="grid gap-4 sm:grid-cols-2"
            >
                <FarmField
                    id="batch-label"
                    class="sm:col-span-2"
                    label="Label subblok (opsional)"
                    hint="Kosongkan untuk label otomatis A, B, C, dan seterusnya."
                    :error="batchForm.errors.subblock_label"
                    v-slot="field"
                    ><input
                        :id="field.id"
                        v-model="batchForm.subblock_label"
                        :class="fieldClass"
                        maxlength="30"
                        pattern="[A-Za-z0-9][A-Za-z0-9_\-]*"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description" /></FarmField
                ><FarmField
                    id="batch-area"
                    label="Luas batch"
                    required
                    :hint="
                        batchForm.area_unit === 'm2'
                            ? 'Isi m² bulat tanpa pemisah ribuan, misalnya 50000.'
                            : 'Maksimal 4 desimal. Gunakan koma atau titik, misalnya 0,5.'
                    "
                    :error="areaError ?? serverAreaError"
                    v-slot="field"
                    ><input
                        :id="field.id"
                        v-model="batchForm.area_value"
                        :class="fieldClass"
                        type="text"
                        inputmode="decimal"
                        required
                        maxlength="20"
                        :placeholder="
                            batchForm.area_unit === 'm2'
                                ? 'Contoh: 50000'
                                : 'Contoh: 5'
                        "
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                /></FarmField>
                <FarmField
                    id="batch-area-unit"
                    label="Satuan luas"
                    required
                    :error="batchForm.errors.area_unit"
                    v-slot="field"
                >
                    <AppSelect
                        :id="field.id"
                        v-model="batchForm.area_unit"
                        :class="fieldClass"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                    >
                        <AppSelectOption value="ha"
                            >Hektare (ha)</AppSelectOption
                        >
                        <AppSelectOption value="m2"
                            >Meter persegi (m²)</AppSelectOption
                        >
                    </AppSelect>
                </FarmField>
                <FarmField
                    id="batch-planned-planting"
                    label="Rencana tanggal tanam (opsional)"
                    hint="Menjadi acuan HST untuk jadwal tugas SOP pada batch ini."
                    :error="batchForm.errors.planned_planting_at"
                    v-slot="field"
                    ><input
                        :id="field.id"
                        v-model="batchForm.planned_planting_at"
                        :class="fieldClass"
                        type="date"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                /></FarmField>
            </fieldset>
            <p
                class="min-h-6 text-sm text-muted-foreground"
                role="status"
                aria-live="polite"
            >
                <template v-if="enteredMetres !== null && enteredMetres > 0"
                    >Setara {{ hectares(areaValue(enteredMetres, 'ha')) }} ha =
                    {{ formatMetres(enteredMetres) }} m².</template
                >
                <template v-else
                    >1 ha = 10.000 m². Pergantian satuan mempertahankan luas
                    yang sudah diisi.</template
                >
            </p>
            <Button
                type="submit"
                class="block-action h-10"
                :disabled="batchForm.processing || availableMetres === 0"
                ><LoaderCircle
                    v-if="batchForm.processing"
                    class="animate-spin"
                />{{
                    batchForm.processing ? 'Membuat batch…' : 'Buat batch'
                }}</Button
            >
        </form>
    </section>
    <section
        v-else-if="!block.is_active"
        class="rounded-xl bg-muted p-5 text-sm text-muted-foreground"
    >
        Blok nonaktif. Histori tetap dapat dibaca; pembuatan batch baru tidak
        tersedia.
    </section>
    <section class="space-y-4">
        <div>
            <h2 class="admin-section-title text-xl font-semibold">
                Timeline batch
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Setiap batch memiliki tahap dan tanggalnya sendiri. Nomor batch
                berurutan dalam satu kebun.
            </p>
        </div>
        <p
            class="rounded-xl bg-muted p-4 text-sm leading-6 text-muted-foreground"
        >
            Saat ini, halaman ini mendukung pembuatan alokasi batch dan
            peninjauan timeline. Penyelesaian tahap, realisasi tanam/panen, dan
            pembatalan batch belum tersedia. Membuat batch belum mencatat
            realisasi tanam.
        </p>
        <article
            v-for="batch in batches"
            :key="batch.id"
            class="space-y-5 rounded-xl border border-border bg-card p-5"
        >
            <header>
                <h3 class="admin-section-title text-lg font-semibold">
                    Batch {{ batch.batch_number }} · {{ block.code
                    }}{{ batch.subblock_label }}
                </h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Periode
                    {{
                        rounds.find(
                            (round) => round.id === batch.planting_round_id,
                        )?.round_number
                    }}
                    · Alokasi {{ hectares(batch.reserved_area_ha) }} ha ·
                    Realisasi tanam {{ hectares(batch.planted_area_ha) }} ha
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Rencana tanam:
                    {{
                        batch.planned_planting_at
                            ? blockDate(batch.planned_planting_at)
                            : 'belum ditentukan'
                    }}
                </p>
            </header>
            <p class="text-sm font-medium">
                Tahap saat ini:
                {{
                    batch.stages.find((stage) => stage.status === 'in_progress')
                        ? stageLabels[
                              batch.stages.find(
                                  (stage) => stage.status === 'in_progress',
                              )!.code
                          ]
                        : 'Tidak ada tahap berlangsung'
                }}
            </p>
            <details class="group">
                <summary
                    class="flex min-h-11 cursor-pointer items-center rounded-lg text-sm font-medium text-foreground underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring"
                >
                    Lihat enam tahap dan tanggal
                </summary>
                <BatchTimeline class="mt-4" :stages="batch.stages" />
            </details>
        </article>
        <div
            v-if="!batches.length"
            class="rounded-xl border border-dashed border-border bg-card p-7 text-center"
        >
            <h3 class="font-semibold">Belum ada subblok / batch</h3>
            <p class="mt-2 text-sm text-muted-foreground">
                {{
                    can.createBatch && block.is_active
                        ? 'Buat batch pertama setelah persiapan pembagian blok siap.'
                        : 'Batch akan muncul setelah dibuat oleh petugas atau admin.'
                }}
            </p>
        </div>
    </section>
    <Dialog v-model:open="deleting"
        ><DialogContent
            :class="
                page.props.navigation?.farms
                    ? 'admin-dashboard-theme'
                    : 'farm-block-theme'
            "
            ><DialogHeader
                ><DialogTitle>Hapus {{ block.name }}?</DialogTitle
                ><DialogDescription
                    >Hanya blok tanpa histori periode/batch dan pemetaan yang
                    dapat dihapus. Blok dengan histori dapat dinonaktifkan
                    melalui Ubah blok.</DialogDescription
                ></DialogHeader
            >
            <p v-if="deleteError" role="alert" class="text-sm text-error">
                {{ deleteError }}
            </p>
            <DialogFooter
                ><Button
                    variant="outline"
                    :disabled="deleteForm.processing"
                    @click="deleting = false"
                    >Batal</Button
                ><Button
                    variant="destructive"
                    :disabled="deleteForm.processing"
                    @click="deleteBlock"
                    ><LoaderCircle
                        v-if="deleteForm.processing"
                        class="animate-spin"
                    />{{
                        deleteForm.processing ? 'Menghapus…' : 'Hapus blok'
                    }}</Button
                ></DialogFooter
            ></DialogContent
        ></Dialog
    >
</template>
