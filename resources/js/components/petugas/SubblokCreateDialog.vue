<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Sprout } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import FormErrorSummary from '@/components/farms/FormErrorSummary.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { focusFormErrors } from '@/composables/useFormRecovery';
import { gardenArea } from '@/types/petugas-garden';
import FarmCycleField from '@/components/blocks/FarmCycleField.vue';
import { useFarmCycles } from '@/composables/useFarmCycles';

export type SubblokBlockOption = {
    id: string;
    name: string;
    is_active: boolean;
    available_ha: string;
    can_create_batch: boolean;
};

const props = defineProps<{
    open: boolean;
    blocks: SubblokBlockOption[];
    selectedBlockId?: string | null;
    lockBlock?: boolean;
    returnTo: 'kebun' | 'penanaman';
    workDate?: string | null;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const eligibleBlocks = computed(() =>
    props.blocks.filter((block) => block.is_active && block.can_create_batch),
);

const chosenBlockId = ref('');
const { defaults: cycleDefaults } = useFarmCycles();
const clientBlockError = ref('');

const form = useForm({
    ...cycleDefaults(),
    return_to: props.returnTo,
    work_date: props.workDate ?? null,
    subblock_label: '',
    planned_planting_at: '',
});

const selectedBlock = computed(
    () =>
        props.blocks.find((block) => block.id === chosenBlockId.value) ??
        eligibleBlocks.value.find((block) => Number(block.available_ha) > 0) ??
        eligibleBlocks.value[0] ??
        null,
);

const canSubmitBlock = computed(
    () =>
        selectedBlock.value !== null &&
        selectedBlock.value.is_active &&
        selectedBlock.value.can_create_batch &&
        Number(selectedBlock.value.available_ha) > 0,
);

const errors = computed<Record<string, string | undefined>>(() => ({
    ...form.errors,
    ...(clientBlockError.value ? { block_id: clientBlockError.value } : {}),
}));

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        const preferred =
            (props.selectedBlockId &&
                props.blocks.find((b) => b.id === props.selectedBlockId)?.id) ||
            eligibleBlocks.value.find((b) => Number(b.available_ha) > 0)?.id ||
            eligibleBlocks.value[0]?.id ||
            '';
        chosenBlockId.value = preferred;
        clientBlockError.value = '';
        form.return_to = props.returnTo;
        form.work_date = props.workDate ?? null;
        form.subblock_label = '';
        form.planned_planting_at = '';
        Object.assign(form, cycleDefaults());
        form.clearErrors();
        form.defaults();
    },
);

watch(chosenBlockId, () => {
    clientBlockError.value = '';
    form.clearErrors();
});

function close(nextOpen: boolean): void {
    if (!nextOpen && form.processing) return;
    if (
        !nextOpen &&
        form.isDirty &&
        !window.confirm('Tutup dan buang perubahan yang belum disimpan?')
    ) {
        return;
    }
    emit('update:open', nextOpen);
}

function submit(): void {
    if (form.processing) return;
    clientBlockError.value = '';
    if (!selectedBlock.value) {
        clientBlockError.value = 'Pilih blok aktif terlebih dahulu.';
        void focusFormErrors();
        return;
    }
    if (
        !canSubmitBlock.value ||
        (!form.farm_cycle_id && !form.create_farm_cycle)
    ) {
        clientBlockError.value =
            'Kapasitas blok terpilih penuh atau blok belum aktif.';
        void focusFormErrors();
        return;
    }
    form.post(`/petugas/blocks/${selectedBlock.value.id}/subbloks`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.defaults();
            emit('update:open', false);
        },
        onError: focusFormErrors,
    });
}

const fieldClass =
    'block min-h-11 w-full rounded-xl border border-border bg-card px-3 py-2.5 text-base text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-error disabled:bg-muted [color-scheme:light] dark:[color-scheme:dark]';
</script>

<template>
    <Dialog :open="props.open" @update:open="close">
        <DialogContent
            :show-close-button="!form.processing"
            class="admin-dashboard-theme petugas-dashboard-theme flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden rounded-2xl border-border bg-card p-0 text-foreground sm:max-w-md"
            @interact-outside.prevent
        >
            <DialogHeader
                class="flex-row items-center gap-3 px-5 py-4 pr-12 text-left"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-secondary dark:text-primary"
                >
                    <Sprout class="size-5" aria-hidden="true" />
                </div>
                <div class="min-w-0 space-y-1">
                    <DialogTitle class="text-lg leading-6 font-semibold">
                        Tambah subblok
                    </DialogTitle>
                    <DialogDescription class="sr-only">
                        Isi siklus lahan, label subblok, dan rencana tanggal tanam.
                    </DialogDescription>
                </div>
            </DialogHeader>

            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div
                        v-if="selectedBlock"
                        class="flex items-center justify-between gap-3 rounded-xl border border-border bg-muted/40 px-4 py-3"
                    >
                        <div class="min-w-0">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                {{ selectedBlock.name }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Sisa lahan untuk penanaman
                            </p>
                        </div>
                        <p class="shrink-0 text-lg font-bold tabular-nums">
                            {{ gardenArea(selectedBlock.available_ha) }}
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >ha</span
                            >
                        </p>
                    </div>

                    <FormErrorSummary
                        :errors="errors"
                        :fields="{
                            farm_cycle_id: {
                                id: 'petugas-farm-cycle',
                                label: 'Siklus lahan',
                            },
                            create_farm_cycle: {
                                id: 'petugas-farm-cycle',
                                label: 'Siklus lahan',
                            },
                            block_id: {
                                id: 'petugas-subblok-block',
                                label: 'Blok',
                            },
                            subblock_label: {
                                id: 'petugas-subblok-label',
                                label: 'Label subblok',
                            },
                            planned_planting_at: {
                                id: 'petugas-subblok-planned',
                                label: 'Rencana tanggal tanam',
                            },
                        }"
                    />

                    <fieldset :disabled="form.processing" class="space-y-4">
                        <FarmCycleField
                            id="petugas-farm-cycle"
                            v-model="form.farm_cycle_id"
                            v-model:create="form.create_farm_cycle"
                            :disabled="form.processing"
                            :error="
                                form.errors.farm_cycle_id ||
                                form.errors.create_farm_cycle
                            "
                        />
                        <div v-if="!lockBlock" class="space-y-2">
                            <label
                                for="petugas-subblok-block"
                                class="petugas-label block"
                                >Blok <span class="text-error">*</span></label
                            >
                            <AppSelect
                                id="petugas-subblok-block"
                                v-model="chosenBlockId"
                                required
                                placeholder="Pilih blok"
                                :class="fieldClass"
                                :aria-invalid="!!errors.block_id"
                            >
                                <AppSelectOption
                                    v-for="block in eligibleBlocks"
                                    :key="block.id"
                                    :value="block.id"
                                    :disabled="Number(block.available_ha) <= 0"
                                >
                                    {{ block.name }} ({{
                                        Number(block.available_ha) > 0
                                            ? `sisa ${gardenArea(block.available_ha)} ha`
                                            : 'penuh'
                                    }})
                                </AppSelectOption>
                            </AppSelect>
                            <p
                                v-if="errors.block_id"
                                class="text-xs text-error"
                            >
                                {{ errors.block_id }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label
                                for="petugas-subblok-label"
                                class="petugas-label block"
                                >Label subblok (opsional)</label
                            >
                            <input
                                id="petugas-subblok-label"
                                v-model="form.subblock_label"
                                :class="fieldClass"
                                maxlength="30"
                                pattern="[A-Za-z0-9][A-Za-z0-9_\-]*"
                                placeholder="Contoh: A atau UTARA-1"
                                :aria-invalid="!!errors.subblock_label"
                                aria-describedby="petugas-subblok-label-hint"
                            />
                            <p
                                id="petugas-subblok-label-hint"
                                class="text-xs text-muted-foreground"
                            >
                                Kosongkan untuk label otomatis A, B, C.
                            </p>
                            <p
                                v-if="errors.subblock_label"
                                class="text-xs text-error"
                            >
                                {{ errors.subblock_label }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label
                                for="petugas-subblok-planned"
                                class="petugas-label block"
                                >Rencana tanggal tanam (opsional)</label
                            >
                            <input
                                id="petugas-subblok-planned"
                                v-model="form.planned_planting_at"
                                type="date"
                                :class="fieldClass"
                                :aria-invalid="!!errors.planned_planting_at"
                                aria-describedby="petugas-subblok-planned-hint"
                            />
                            <p
                                id="petugas-subblok-planned-hint"
                                class="text-xs text-muted-foreground"
                            >
                                Tanggal rencana, bukan realisasi tanam.
                            </p>
                            <p
                                v-if="errors.planned_planting_at"
                                class="text-xs text-error"
                            >
                                {{ errors.planned_planting_at }}
                            </p>
                        </div>
                    </fieldset>
                </div>

                <DialogFooter
                    class="flex-row flex-wrap items-center justify-end gap-2 px-5 py-3"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11 rounded-xl"
                        :disabled="form.processing"
                        @click="close(false)"
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        class="min-h-11 rounded-xl"
                        :disabled="
                            form.processing ||
                            !canSubmitBlock ||
                            (!form.farm_cycle_id && !form.create_farm_cycle)
                        "
                    >
                        <LoaderCircle
                            v-if="form.processing"
                            class="size-4 animate-spin motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        <Plus v-else class="size-4" aria-hidden="true" />
                        {{ form.processing ? 'Menyimpan…' : 'Tambah subblok' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
