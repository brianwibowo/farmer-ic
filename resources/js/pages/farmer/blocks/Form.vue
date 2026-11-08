<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import BlockForm from '@/components/blocks/BlockForm.vue';
import BlockLayout from '@/layouts/BlockLayout.vue';
import type { BlockFarm, FarmBlock, BlockFormCapacity } from '@/types/blocks';
defineOptions({ layout: BlockLayout, inheritAttrs: false });
const props = defineProps<{
    farm: BlockFarm;
    block: FarmBlock | null;
    capacity: BlockFormCapacity;
}>();
const back = props.block
    ? `/kebun/${props.farm.id}/blocks/${props.block.id}`
    : `/kebun/${props.farm.id}/blocks`;
</script>
<template>
    <Head :title="block ? 'Ubah blok' : 'Tambah blok'" />
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <Link
            :href="back"
            class="inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground"
            ><ArrowLeft class="size-4" />Kembali ke blok</Link
        >
        <h1 class="admin-page-title">
            {{ block ? 'Ubah blok' : 'Tambah blok' }}
        </h1>
        <section class="rounded-xl border border-border bg-card p-5 sm:p-6">
            <BlockForm
                :farm="farm"
                :block="block"
                :capacity="capacity"
                @cancel="router.visit(back)"
            />
        </section>
    </div>
</template>
