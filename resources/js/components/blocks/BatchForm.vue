<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FarmCycleField from './FarmCycleField.vue';
import { useFarmCycles } from '@/composables/useFarmCycles';
import { ChevronDown, ChevronUp, LoaderCircle, Save } from '@lucide/vue';
import FarmField from '@/components/farms/FarmField.vue';
import FormErrorSummary from '@/components/farms/FormErrorSummary.vue';
import {
    focusFormErrors,
    useUnsavedChanges,
} from '@/composables/useFormRecovery';
import { Button } from '@/components/ui/button';
import { hectares } from '@/types/farms';
import { blockDate, type BatchCapacity, type PreparationBatch } from '@/types/blocks';
const props = defineProps<{
    dialog?: boolean;
    returnTo?: 'farm' | 'blocks';
    base: string;
    capacity: BatchCapacity;
    batch: PreparationBatch | null;
    existingBatches?: PreparationBatch[];
}>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
const base = props.base;
const { defaults: cycleDefaults } = useFarmCycles();
const latestCycleId = props.existingBatches?.find((b) => b.farm_cycle_id)?.farm_cycle_id;
const batchForm = useForm({
    ...cycleDefaults(),
    ...(latestCycleId ? { farm_cycle_id: latestCycleId, create_farm_cycle: false } : {}),
    return_to: props.returnTo ?? 'blocks',
    subblock_label: props.batch?.subblock_label ?? '',
    planned_planting_at: props.batch?.planned_planting_at?.slice(0, 10) ?? '',
});
useUnsavedChanges(() => batchForm.isDirty && !batchForm.processing);

const showPreviousSubblocks = ref(true);
const statusLabels: Record<string, string> = {
    planned: 'Tahap Persiapan',
    active: 'Sedang Berjalan',
    harvested: 'Selesai Panen',
    cancelled: 'Dibatalkan',
};

function statusBadgeClass(status: string): string {
    switch (status) {
        case 'active':
            return 'bg-success/15 text-success';
        case 'harvested':
            return 'bg-primary/15 text-primary';
        case 'planned':
            return 'bg-warning/15 text-warning';
        default:
            return 'bg-muted text-muted-foreground';
    }
}
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
                <div
                    v-if="!batch && existingBatches && existingBatches.length > 0"
                    class="rounded-xl border border-border bg-muted/20 p-3.5 space-y-3 sm:col-span-2"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-primary inline-block"></span>
                                Riwayat Subblok Terdaftar pada Blok Ini ({{ existingBatches.length }})
                            </p>
                            <p class="text-[11px] text-muted-foreground mt-0.5">
                                Blok ini telah memiliki {{ existingBatches.length }} subblok terdaftar dalam siklus berjalan.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline focus-visible:outline-2 focus-visible:outline-ring rounded p-1"
                            :aria-expanded="showPreviousSubblocks"
                            @click="showPreviousSubblocks = !showPreviousSubblocks"
                        >
                            <span>{{ showPreviousSubblocks ? 'Tutup rincian' : 'Lihat rincian' }}</span>
                            <ChevronUp v-if="showPreviousSubblocks" class="size-3.5" />
                            <ChevronDown v-else class="size-3.5" />
                        </button>
                    </div>

                    <div v-show="showPreviousSubblocks" class="space-y-2 pt-1 border-t border-border/60">
                        <div
                            v-for="item in existingBatches"
                            :key="item.id"
                            class="flex items-center justify-between gap-3 rounded-lg bg-card border border-border/80 px-3 py-2.5 text-xs"
                        >
                            <div class="min-w-0 space-y-0.5">
                                <p class="font-semibold text-foreground">
                                    Subblok {{ item.subblock_label }}
                                    <span v-if="item.farm_cycle_number" class="text-muted-foreground font-normal ml-1">
                                        — Terdaftar pada Siklus {{ item.farm_cycle_number }}
                                    </span>
                                </p>
                                <p class="text-[11px] text-muted-foreground tabular-nums">
                                    Alokasi luas: {{ hectares(item.planted_area_ha) }} ha
                                    <template v-if="item.planned_planting_at">
                                        · Rencana tanam: {{ blockDate(item.planned_planting_at) }}
                                    </template>
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-medium"
                                :class="statusBadgeClass(item.status)"
                            >
                                {{ statusLabels[item.status] ?? item.status }}
                            </span>
                        </div>
                    </div>
                </div>
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
