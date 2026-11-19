<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import FarmCycleField from './FarmCycleField.vue';
import { useFarmCycles } from '@/composables/useFarmCycles';
import { LoaderCircle, Save } from '@lucide/vue';
import FarmField from '@/components/farms/FarmField.vue';
import FormErrorSummary from '@/components/farms/FormErrorSummary.vue';
import {
    focusFormErrors,
    useUnsavedChanges,
} from '@/composables/useFormRecovery';
import { Button } from '@/components/ui/button';
import { hectares } from '@/types/farms';
import type { BatchCapacity, PreparationBatch } from '@/types/blocks';
const props = defineProps<{
    dialog?: boolean;
    returnTo?: 'farm' | 'blocks';
    base: string;
    capacity: BatchCapacity;
    batch: PreparationBatch | null;
}>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
const base = props.base;
const { defaults: cycleDefaults } = useFarmCycles();
const batchForm = useForm({
    ...cycleDefaults(),
    return_to: props.returnTo ?? 'blocks',
    subblock_label: props.batch?.subblock_label ?? '',
    planned_planting_at: props.batch?.planned_planting_at?.slice(0, 10) ?? '',
});
useUnsavedChanges(() => batchForm.isDirty && !batchForm.processing);
function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            batchForm.defaults();
            emit('saved');
        },
        onError: focusFormErrors,
    };
    if (props.batch)
        batchForm
            .transform(
                ({
                    farm_cycle_id: _cycle,
                    create_farm_cycle: _create,
                    ...data
                }) => data,
            )
            .put(`${base}/batches/${props.batch.id}`, options);
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
                        Sisa lahan blok untuk penanaman
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Luas sub blok ditentukan secara otomatis dari akumulasi
                        laporan penanaman.
                    </p>
                </div>
                <p class="text-2xl font-bold tabular-nums">
                    {{ hectares(props.capacity.available_ha) }}
                    <span class="text-sm font-medium text-muted-foreground"
                        >ha tersedia</span
                    >
                </p>
            </div>
            <FormErrorSummary
                :errors="batchForm.errors"
                :fields="{
                    farm_cycle_id: {
                        id: 'batch-farm-cycle',
                        label: 'Siklus lahan',
                    },
                    create_farm_cycle: {
                        id: 'batch-farm-cycle',
                        label: 'Siklus lahan',
                    },
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
                <FarmCycleField
                    v-if="!batch"
                    id="batch-farm-cycle"
                    v-model="batchForm.farm_cycle_id"
                    v-model:create="batchForm.create_farm_cycle"
                    class="sm:col-span-2"
                    :disabled="batchForm.processing"
                    :error="
                        batchForm.errors.farm_cycle_id ||
                        batchForm.errors.create_farm_cycle
                    "
                />
                <FarmField
                    id="batch-label"
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
                Pilih siklus lahan untuk subblok baru
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
                    :disabled="
                        batchForm.processing ||
                        (!batch &&
                            !batchForm.farm_cycle_id &&
                            !batchForm.create_farm_cycle) ||
                        (!batch && Number(props.capacity.available_ha) <= 0)
                    "
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
