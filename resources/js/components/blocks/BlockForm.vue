<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Save } from '@lucide/vue';
import { computed, watch } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import FormErrorSummary from '@/components/farms/FormErrorSummary.vue';
import {
    focusFormErrors,
    useUnsavedChanges,
} from '@/composables/useFormRecovery';
import { hectares } from '@/types/farms';
import { Button } from '@/components/ui/button';
import type { BlockFarm, FarmBlock } from '@/types/blocks';
import { areaValue, squareMetres, type AreaUnit } from '@/lib/landArea';

const props = defineProps<{
    dialog?: boolean;
    farm: BlockFarm;
    block: FarmBlock | null;
    returnTo?: 'farm';
    capacity: {
        maximum_area_ha: string;
        minimum_area_ha: string;
        running_batches: number;
    };
}>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
const base = `/kebun/${props.farm.id}/blocks`;
const form = useForm({
    return_to: props.returnTo ?? '',
    code: props.block?.code ?? '',
    name: props.block?.name ?? '',
    area_value: props.block?.area_ha ?? '',
    area_unit: 'ha' as AreaUnit,
    description: props.block?.description ?? '',
    is_active: props.block?.is_active ?? true,
});
useUnsavedChanges(() => form.isDirty && !form.processing);
const enteredMetres = computed(() =>
    squareMetres(form.area_value, form.area_unit),
);
const areaError = computed(() => {
    if (!form.area_value.trim()) return null;
    const metres = enteredMetres.value;
    if (metres === null)
        return form.area_unit === 'm2'
            ? 'Masukkan m² bulat tanpa pemisah ribuan, misalnya 50000.'
            : 'Masukkan luas ha dengan maksimal 4 desimal, misalnya 0,5.';
    const minimum = squareMetres(props.capacity.minimum_area_ha) ?? 1;
    if (metres < minimum)
        return `Luas minimal ${hectares(props.capacity.minimum_area_ha)} ha (${new Intl.NumberFormat('id-ID').format(minimum)} m²).`;
    if (metres > 999999999999) return 'Luas melebihi batas penyimpanan.';
    if (
        form.is_active &&
        metres > (squareMetres(props.capacity.maximum_area_ha) ?? 0)
    )
        return `Luas melebihi kapasitas blok aktif ${hectares(props.capacity.maximum_area_ha)} ha.`;
    return null;
});
const serverAreaError = computed(
    () =>
        form.errors.area_value ??
        (form.errors as Record<string, string>).area_ha,
);
watch(
    () => form.area_unit,
    (unit, previous) => {
        const metres = squareMetres(form.area_value, previous);
        if (metres !== null) form.area_value = areaValue(metres, unit);
        form.clearErrors('area_value', 'area_unit');
        delete (form.errors as Record<string, string>).area_ha;
    },
);
watch(
    () => form.area_value,
    () => {
        form.clearErrors('area_value');
        delete (form.errors as Record<string, string>).area_ha;
    },
);
const noCapacity = computed(
    () =>
        form.is_active &&
        Number(props.capacity.maximum_area_ha) <
            Number(props.capacity.minimum_area_ha),
);
const errorFields = {
    code: { id: 'block-code', label: 'Kode blok' },
    name: { id: 'block-name', label: 'Nama blok' },
    area_ha: { id: 'block-area', label: 'Luas blok' },
    area_value: { id: 'block-area', label: 'Luas blok' },
    area_unit: { id: 'block-area-unit', label: 'Satuan luas' },
    is_active: { id: 'block-active', label: 'Status' },
    description: { id: 'block-description', label: 'Catatan' },
};
defineExpose({
    isDirty: () => form.isDirty,
    processing: () => form.processing,
});
const fieldClass = 'admin-field block-field';
function submit(): void {
    if (noCapacity.value) return;
    if (areaError.value || enteredMetres.value === null) {
        form.setError('area_value', areaError.value ?? 'Isi luas blok.');
        void focusFormErrors();
        return;
    }
    if (props.block)
        form.put(`${base}/${props.block.id}`, {
            preserveScroll: true,
            onError: focusFormErrors,
            onSuccess: () => {
                form.defaults();
                emit('saved');
            },
        });
    else
        form.post(base, {
            preserveScroll: true,
            onError: focusFormErrors,
            onSuccess: () => {
                form.defaults();
                emit('saved');
            },
        });
}
</script>

<template>
    <form
        :class="dialog ? 'flex min-h-0 flex-col' : 'space-y-5'"
        @submit.prevent="submit"
    >
        <div
            :class="
                dialog
                    ? 'min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-7 sm:py-6'
                    : 'space-y-5'
            "
        >
            <FormErrorSummary :errors="form.errors" :fields="errorFields" />
            <p
                v-if="noCapacity"
                role="status"
                class="rounded-xl bg-muted p-4 text-sm leading-6 text-foreground"
            >
                Kapasitas kebun tidak mencukupi untuk blok aktif ini. Sesuaikan
                alokasi blok yang ada, atau pilih status Nonaktif untuk mencatat
                blok tanpa alokasi baru.
            </p>
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-muted/40 px-4 py-4"
            >
                <div>
                    <p class="text-sm font-medium">Kapasitas blok aktif</p>
                    <p
                        v-if="capacity.running_batches"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Minimum {{ hectares(capacity.minimum_area_ha) }} ha
                        untuk {{ capacity.running_batches }} sub blok berjalan.
                    </p>
                    <p v-else class="mt-1 text-xs text-muted-foreground">
                        Luas blok mengikuti sisa lahan kebun.
                    </p>
                </div>
                <p class="text-2xl font-bold tabular-nums">
                    {{ hectares(capacity.maximum_area_ha) }}
                    <span class="text-sm font-medium text-muted-foreground"
                        >ha tersedia</span
                    >
                </p>
            </div>
            <fieldset
                :disabled="form.processing"
                class="grid gap-5 disabled:opacity-70 sm:grid-cols-2"
            >
                <FarmField
                    id="block-code"
                    label="Kode blok"
                    required
                    :error="form.errors.code"
                    hint="Contoh: B01. Unik dalam kebun ini."
                    v-slot="field"
                    ><input
                        :id="field.id"
                        v-model="form.code"
                        placeholder="Contoh: B03"
                        :class="fieldClass"
                        required
                        maxlength="30"
                        pattern="[A-Za-z0-9][A-Za-z0-9_\-]*"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                /></FarmField>
                <FarmField
                    id="block-name"
                    label="Nama blok"
                    required
                    :error="form.errors.name"
                    v-slot="field"
                    ><input
                        :id="field.id"
                        v-model="form.name"
                        placeholder="Contoh: Blok Timur"
                        :class="fieldClass"
                        required
                        maxlength="150"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                /></FarmField>
                <FarmField
                    id="block-area"
                    label="Luas blok"
                    required
                    :error="areaError ?? serverAreaError"
                    :hint="
                        form.area_unit === 'ha'
                            ? 'Maksimal 4 desimal. Gunakan koma atau titik, misalnya 0,5.'
                            : 'Gunakan m² bulat tanpa pemisah ribuan, misalnya 5000.'
                    "
                    v-slot="field"
                    ><input
                        :id="field.id"
                        v-model="form.area_value"
                        :class="fieldClass"
                        type="text"
                        inputmode="decimal"
                        required
                        :placeholder="
                            form.area_unit === 'ha'
                                ? 'Contoh: 5'
                                : 'Contoh: 50000'
                        "
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                /></FarmField>
                <FarmField
                    id="block-area-unit"
                    label="Satuan luas"
                    required
                    :error="form.errors.area_unit"
                    v-slot="field"
                >
                    <AppSelect
                        :id="field.id"
                        v-model="form.area_unit"
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
                <p
                    class="text-xs leading-5 text-muted-foreground sm:col-span-2"
                >
                    1 ha = 10.000 m².<span
                        v-if="enteredMetres !== null && !areaError"
                    >
                        Setara {{ hectares(areaValue(enteredMetres, 'ha')) }} ha
                        ({{
                            new Intl.NumberFormat('id-ID').format(enteredMetres)
                        }}
                        m²).</span
                    >
                </p>
                <FarmField
                    id="block-active"
                    label="Status blok"
                    :error="form.errors.is_active"
                    v-slot="field"
                    ><AppSelect
                        :id="field.id"
                        v-model="form.is_active"
                        :class="fieldClass"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                    >
                        <AppSelectOption :value="true">Aktif</AppSelectOption>
                        <AppSelectOption
                            :value="false"
                            :disabled="capacity.running_batches > 0"
                        >
                            Nonaktif{{
                                capacity.running_batches > 0
                                    ? ' · masih ada batch berjalan'
                                    : ''
                            }}
                        </AppSelectOption>
                    </AppSelect></FarmField
                >
                <FarmField
                    id="block-description"
                    label="Catatan"
                    :error="form.errors.description"
                    class="sm:col-span-2"
                    v-slot="field"
                >
                    <textarea
                        :id="field.id"
                        v-model="form.description"
                        :class="fieldClass"
                        rows="3"
                        maxlength="5000"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                    />
                </FarmField>
            </fieldset>
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
                    :disabled="form.processing"
                    type="button"
                    variant="outline"
                    class="block-action h-10"
                    @click="emit('cancel')"
                    >Batal</Button
                ><Button
                    type="submit"
                    class="block-action h-10"
                    :disabled="form.processing || noCapacity"
                    ><LoaderCircle
                        v-if="form.processing"
                        class="animate-spin"
                    /><Save v-else class="size-4" />{{
                        form.processing ? 'Menyimpan…' : 'Simpan blok'
                    }}</Button
                >
            </div>
        </div>
    </form>
</template>
