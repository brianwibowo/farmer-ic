<script setup lang="ts">
import { requestConfirmation } from '@/composables/useConfirmation';
import { Sprout } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import BatchForm from './BatchForm.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import type { BatchCapacity, PreparationBatch } from '@/types/blocks';
const props = defineProps<{
    open: boolean;
    context: string;
    returnTo?: 'farm' | 'blocks';
    base: string;
    capacity: BatchCapacity;
    batch: PreparationBatch | null;
    existingBatches?: PreparationBatch[];
    theme: string;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean]; saved: [] }>();
const fields = useTemplateRef<InstanceType<typeof BatchForm>>('fields');
async function close(open: boolean): Promise<void> {
    if (!open && fields.value?.processing()) return;
    if (
        !open &&
        fields.value?.isDirty() &&
        !(await requestConfirmation({
            description:
                'Perubahan subblok belum disimpan. Tutup editor dan buang perubahan ini?',
            confirmLabel: 'Buang perubahan',
            cancelLabel: 'Lanjut mengedit',
        }))
    )
        return;
    if (fields.value?.processing()) return;
    emit('update:open', open);
}
</script>
<template>
    <Dialog :open="props.open" @update:open="close">
        <DialogContent
            :show-close-button="!fields?.processing()"
            :class="[
                theme,
                'flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden rounded-xl border-border bg-card p-0 text-foreground sm:max-w-3xl',
            ]"
            @interact-outside="
                (event) => {
                    if (fields?.processing()) {
                        event.preventDefault();
                        return;
                    }
                    if (fields?.isDirty()) {
                        event.preventDefault();
                        void close(false);
                    }
                }
            "
        >
            <DialogHeader
                class="flex-row items-start gap-4 border-b border-border bg-muted/30 px-5 py-5 pr-12 text-left sm:px-7 sm:pr-14"
            >
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <Sprout class="size-6" />
                </div>
                <div class="min-w-0 space-y-1">
                    <DialogTitle class="text-xl leading-7 font-semibold">{{
                        batch ? 'Ubah sub blok' : 'Tambah sub blok'
                    }}</DialogTitle>
                    <p class="text-sm font-medium text-foreground">
                        {{ context }}
                    </p>
                    <DialogDescription class="text-xs leading-5">{{
                        batch
                            ? 'Perbarui label dan rencana tanam sub blok.'
                            : 'Tambahkan sub blok untuk memulai pengolahan tanah dan penanaman.'
                    }}</DialogDescription>
                </div>
            </DialogHeader>
            <BatchForm
                dialog
                class="min-h-0 flex-1"
                v-if="open"
                ref="fields"
                :return-to="returnTo"
                :base="base"
                :capacity="capacity"
                :batch="batch"
                :existing-batches="existingBatches"
                @saved="
                    emit('update:open', false);
                    emit('saved');
                "
                @cancel="close(false)"
            />
        </DialogContent>
    </Dialog>
</template>
