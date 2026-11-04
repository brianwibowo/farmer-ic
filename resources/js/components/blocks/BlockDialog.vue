<script setup lang="ts">
import { LandPlot } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import BlockForm from './BlockForm.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import type { BlockFarm, FarmBlock, BlockFormCapacity } from '@/types/blocks';
const props = defineProps<{
    open: boolean;
    farm: BlockFarm;
    block: FarmBlock | null;
    capacity: BlockFormCapacity;
    returnTo?: 'farm' | 'blocks';
    theme: string;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
const fields = useTemplateRef<InstanceType<typeof BlockForm>>('fields');
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
                    <LandPlot class="size-6" />
                </div>
                <div class="min-w-0 space-y-1">
                    <DialogTitle class="text-xl leading-7 font-semibold">{{
                        block ? 'Ubah blok' : 'Tambah blok'
                    }}</DialogTitle>
                    <p class="text-sm font-medium text-foreground">
                        {{ farm.name }}
                    </p>
                    <DialogDescription class="text-xs leading-5">{{
                        block
                            ? 'Perbarui identitas dan alokasi lahan blok.'
                            : 'Atur identitas dan luas blok baru.'
                    }}</DialogDescription>
                </div>
            </DialogHeader>
            <BlockForm
                dialog
                class="min-h-0 flex-1"
                v-if="open"
                ref="fields"
                :farm="farm"
                :block="block"
                :capacity="capacity"
                :return-to="returnTo"
                @saved="emit('update:open', false)"
                @cancel="close(false)"
            />
        </DialogContent>
    </Dialog>
</template>
