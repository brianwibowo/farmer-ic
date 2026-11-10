<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import OwnerCycleCard from '@/components/owner/OwnerCycleCard.vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import type { OwnerBlock, OwnerCycle } from '@/types/owner';
import { ownerArea, ownerDate } from '@/types/owner';

defineProps<{
    farm: { id: string; name: string };
    block: OwnerBlock;
    cycles: OwnerCycle[];
}>();
</script>

<template>
    <Head :title="`${block.code} · ${block.name}`" />
    <OwnerPageHeader
        :title="`${block.code} · ${block.name}`"
        :description="farm.name"
        :back-href="`/kebun/${farm.id}`"
        back-label="Detail kebun"
    />
    <section class="space-y-4 rounded-xl border border-border p-4">
        <div class="flex items-center justify-between gap-3">
            <p class="text-2xl font-semibold">
                {{ ownerArea(block.area_ha) }}
                <span class="text-sm font-normal">ha</span>
            </p>
            <span class="rounded-full bg-muted px-3 py-1 text-xs">{{
                block.is_active ? 'Blok aktif' : 'Blok nonaktif'
            }}</span>
        </div>
        <p v-if="block.description" class="text-sm leading-6">
            {{ block.description }}
        </p>
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-xs text-muted-foreground">
                    Mulai land clearing
                </dt>
                <dd class="mt-1">
                    {{ ownerDate(block.land_clearing_started_at) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">
                    Selesai land clearing
                </dt>
                <dd class="mt-1">
                    {{ ownerDate(block.land_clearing_completed_at) }}
                </dd>
            </div>
        </dl>
    </section>
    <section aria-labelledby="block-batches">
        <h2 id="block-batches" class="text-base font-semibold">
            Batch & timeline
        </h2>
        <p class="mt-1 text-xs leading-5 text-muted-foreground">
            Alokasi, realisasi, tahap, dan tugas mengikuti catatan setiap batch.
        </p>
        <p
            v-if="!cycles.length"
            class="mt-3 rounded-xl bg-muted p-4 text-sm leading-6 text-muted-foreground"
        >
            Belum ada batch pada blok ini. Batch akan muncul setelah dicatat
            oleh Admin atau Petugas.
        </p>
        <div class="mt-4 space-y-4">
            <OwnerCycleCard
                v-for="cycle in cycles"
                :key="cycle.id"
                :cycle="cycle"
            />
        </div>
    </section>
</template>
