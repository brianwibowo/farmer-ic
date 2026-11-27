<script setup lang="ts">
import PageBackButton from '@/components/PageBackButton.vue';
import { Head, router } from '@inertiajs/vue3';
import BlockForm from '@/components/blocks/BlockForm.vue';
import type { BoundaryMapBlock } from '@/components/farms/FarmBlockBoundaryMap.vue';
import BlockLayout from '@/layouts/BlockLayout.vue';
import type { BlockFarm, FarmBlock, BlockFormCapacity } from '@/types/blocks';
defineOptions({ layout: BlockLayout, inheritAttrs: false });
const props = defineProps<{
    farm: BlockFarm;
    block: FarmBlock | null;
    existingBlocks?: BoundaryMapBlock[];
    capacity: BlockFormCapacity;
}>();
const back = props.block
    ? `/kebun/${props.farm.id}/blocks/${props.block.id}`
    : `/kebun/${props.farm.id}/blocks`;
</script>
<template>
    <Head :title="block ? 'Ubah blok' : 'Tambah blok'" />
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <header class="flex min-w-0 items-center gap-2">
            <PageBackButton :href="back" label="Kembali ke blok" />
            <h1 class="admin-page-title min-w-0">
                {{ block ? 'Ubah blok' : 'Tambah blok' }}
            </h1>
        </header>
        <section class="rounded-xl border border-border bg-card p-5 sm:p-6">
            <BlockForm
                :farm="farm"
                :block="block"
                :existing-blocks="existingBlocks"
                :capacity="capacity"
                @cancel="router.visit(back)"
            />
        </section>
    </div>
</template>
