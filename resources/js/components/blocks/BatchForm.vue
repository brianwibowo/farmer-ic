<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Save } from '@lucide/vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import FarmField from '@/components/farms/FarmField.vue';
import FormErrorSummary from '@/components/farms/FormErrorSummary.vue';
import {
    focusFormErrors,
    useUnsavedChanges,
} from '@/composables/useFormRecovery';
import { Button } from '@/components/ui/button';
import { areaValue, squareMetres, type AreaUnit } from '@/lib/landArea';
import { hectares } from '@/types/farms';
import type { BatchCapacity, PreparationBatch } from '@/types/blocks';
const props = defineProps<{
    dialog?: boolean;
    base: string;
    capacity: BatchCapacity;
    batch: PreparationBatch | null;
}>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
const base = props.base;
const batchForm = useForm({
    area_value: props.batch?.reserved_area_ha ?? '',
    area_unit: 'ha' as AreaUnit,
    subblock_label: props.batch?.subblock_label ?? '',
    planned_planting_at: props.batch?.planned_planting_at?.slice(0, 10) ?? '',
});
useUnsavedChanges(() => batchForm.isDirty && !batchForm.processing);
const availableMetres = computed(
    () =>
        (squareMetres(props.capacity.available_ha) ?? 0) +
        (props.batch ? (squareMetres(props.batch.reserved_area_ha) ?? 0) : 0),
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
        return `Luas ini melebihi kapasitas sub blok ${hectares(areaValue(availableMetres.value, 'ha'))} ha (${formatMetres(availableMetres.value)} m²). Kurangi luas sub blok.`;
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
function submit(): void {
    if (areaError.value || enteredMetres.value === null) {
        batchForm.setError(
            'area_value',
            areaError.value ?? 'Isi luas yang akan dialokasikan untuk batch.',
        );
        void focusFormErrors();
        return;
    }
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            batchForm.defaults();
            emit('saved');
        },
        onError: focusFormErrors,
    };
    if (props.batch)
        batchForm.put(`${base}/batches/${props.batch.id}`, options);
    else batchForm.post(`${base}/batches`, options);
}

const fieldClass = 'admin-field block-field';
defineExpose({
    isDirty: () => batchForm.isDirty,
    processing: () => batchForm.processing,
});
</script>
<template>
    <form
        :class="dialog ? 'flex min-h-0 flex-col' : 'space-y-4'"
        @submit.prevent="submit"
    >
        <div
            :class="
                dialog
                    ? 'min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-7 sm:py-6'
                    : 'space-y-5'
            "
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-muted/40 px-4 py-4"
            >
                <div>
                    <p class="text-sm font-medium">
                        Luas yang dapat dialokasikan
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Mengikuti kapasitas blok dan kebun.
                    </p>
                </div>
                <p class="text-2xl font-bold tabular-nums">
                    {{ hectares(areaValue(availableMetres, 'ha')) }}
                    <span class="text-sm font-medium text-muted-foreground"
                        >ha tersedia</span
                    >
                </p>
            </div>
            <FormErrorSummary
                :errors="batchForm.errors"
                :fields="{
                    area_value: { id: 'batch-area', label: 'Luas sub blok' },
                    reserved_area_ha: {
                        id: 'batch-area',
                        label: 'Luas sub blok',
                    },
                    area_unit: { id: 'batch-area-unit', label: 'Satuan luas' },
                    subblock_label: {
                        id: 'batch-label',
                        label: 'Label sub blok',
                    },
                    planned_planting_at: {
                        id: 'batch-planned-planting',
                        label: 'Rencana tanggal tanam',
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
                    label="Label sub blok (opsional)"
                    :hint="
                        batch
                            ? 'Kosongkan untuk mempertahankan label saat ini.'
                            : 'Kosongkan untuk label otomatis A, B, C.'
                    "
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
                    label="Luas sub blok"
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
                    hint="Tanggal rencana, bukan realisasi tanam."
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
        </div>
        <div
            :class="
                dialog
                    ? 'flex shrink-0 flex-wrap items-center justify-end gap-3 border-t border-border bg-muted/20 px-5 py-4 sm:justify-between sm:px-7'
                    : 'flex flex-wrap justify-end gap-3'
            "
        >
            <p
                v-if="dialog"
                class="hidden text-xs text-muted-foreground sm:block"
            >
                <span class="text-error">*</span> Wajib diisi
            </p>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="batchForm.processing"
                    @click="emit('cancel')"
                    >Batal</Button
                ><Button
                    type="submit"
                    class="block-action h-10"
                    :disabled="batchForm.processing || availableMetres === 0"
                    ><LoaderCircle
                        v-if="batchForm.processing"
                        class="animate-spin"
                    /><Save v-else class="size-4" />{{
                        batchForm.processing
                            ? 'Menyimpan…'
                            : batch
                              ? 'Simpan perubahan'
                              : 'Tambah sub blok'
                    }}</Button
                >
            </div>
        </div>
    </form>
</template>
