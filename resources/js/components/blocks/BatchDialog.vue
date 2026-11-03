<script setup lang="ts">
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
    base: string;
    capacity: BatchCapacity;
    batch: PreparationBatch | null;
    theme: string;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
const fields = useTemplateRef<InstanceType<typeof BatchForm>>('fields');
function close(open: boolean): void {
    if (!open && fields.value?.processing()) return;
    if (
        !open &&
        fields.value?.isDirty() &&
        !window.confirm('Tutup dan buang perubahan yang belum disimpan?')
    )
        return;
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
            @interact-outside.prevent
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
                            ? 'Perbarui alokasi dan rencana tanam.'
                            : 'Alokasikan lahan untuk memulai pengolahan tanah.'
                    }}</DialogDescription>
                </div>
            </DialogHeader>
            <BatchForm
                dialog
                class="min-h-0 flex-1"
                v-if="open"
                ref="fields"
                :base="base"
                :capacity="capacity"
                :batch="batch"
                @saved="emit('update:open', false)"
                @cancel="close(false)"
            />
        </DialogContent>
    </Dialog>
</template>
