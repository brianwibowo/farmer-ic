<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { ChevronDown, ChevronUp, Pencil, Plus } from '@lucide/vue';
import BatchDialog from './BatchDialog.vue';
import BatchTimeline from './BatchTimeline.vue';
import BatchPlantGrowth from './BatchPlantGrowth.vue';
import MetricInfoTooltip from '@/components/MetricInfoTooltip.vue';
import { Button } from '@/components/ui/button';
import {
    blockDate,
    stageLabels,
    type BlockFarm,
    type ManagedBlock,
    type PreparationBatch,
} from '@/types/blocks';
import { hectares } from '@/types/farms';
const props = defineProps<{
    farm: BlockFarm;
    block: ManagedBlock;
    batchPermissions: Record<string, boolean>;
    theme: string;
    returnTo: 'farm' | 'blocks';
}>();
const editing = ref(false);
const expanded = ref(true);
const contentId = useId();
const selected = ref<PreparationBatch | null>(null);
const base = computed(() => `/kebun/${props.farm.id}/blocks/${props.block.id}`);
function open(batch: PreparationBatch | null): void {
    selected.value = batch;
    editing.value = true;
}
const statusLabels: Record<string, string> = {
    planned: 'Persiapan',
    active: 'Berjalan',
    harvested: 'Selesai panen',
    cancelled: 'Dibatalkan',
};
function currentStage(batch: PreparationBatch): string {
    if (batch.status === 'cancelled') return 'Dibatalkan';
    if (batch.status === 'harvested') return 'Selesai panen';
    const current = batch.stages.find(
        (stage) => stage.status === 'in_progress',
    );
    return current
        ? (stageLabels[current.code] ?? current.code)
        : 'Menunggu dimulai';
}
const progressSummary = computed(() => {
    const counts = new Map<string, number>();
    for (const batch of props.block.batches) {
        const label = currentStage(batch);
        counts.set(label, (counts.get(label) ?? 0) + 1);
    }
    return [...counts].map(([label, count]) => ({ label, count }));
});
</script>
<template>
    <section :aria-label="`Sub blok ${block.name}`" class="space-y-5">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h4 class="text-base font-bold text-primary">
                    Sub blok &amp; progres
                    <span class="ml-1 font-normal text-muted-foreground"
                        >({{ block.batches.length }})</span
                    >
                </h4>
                <div
                    class="mt-1 flex flex-wrap items-center gap-x-1 text-sm text-muted-foreground"
                >
                    Sisa lahan untuk sub blok baru
                    <strong class="font-semibold text-foreground tabular-nums"
                        >{{
                            hectares(block.batch_capacity.available_ha)
                        }}
                        ha</strong
                    >
                    <MetricInfoTooltip
                        label="Sisa alokasi sub blok"
                        :description="`Sisa blok ${hectares(block.batch_capacity.block_remaining_ha)} ha. Sisa kebun ${hectares(block.batch_capacity.farm_remaining_ha)} ha. Alokasi baru mengikuti nilai terkecil dari keduanya.`"
                        example="Sisa blok 6 ha dan sisa kebun 4 ha berarti sub blok baru maksimal 4 ha."
                        :content-class="theme"
                        touch-target
                    />
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="block.can.createBatch && block.is_active"
                    variant="outline"
                    size="sm"
                    class="block-action"
                    :disabled="Number(block.batch_capacity.available_ha) <= 0"
                    :aria-label="`Tambah sub blok di ${block.name}`"
                    @click="open(null)"
                    ><Plus class="size-4" />Tambah sub blok</Button
                >
                <Button
                    variant="ghost"
                    size="sm"
                    class="block-action"
                    :aria-expanded="expanded"
                    :aria-controls="contentId"
                    :aria-label="`${expanded ? 'Ringkas' : 'Tampilkan detail'} sub blok di ${block.name}`"
                    @click="expanded = !expanded"
                >
                    <ChevronUp v-if="expanded" class="size-4" />
                    <ChevronDown v-else class="size-4" />
                    {{ expanded ? 'Ringkas' : 'Tampilkan detail' }}
                </Button>
            </div>
        </header>
        <p
            v-if="!expanded || !block.batches.length"
            class="text-xs leading-relaxed text-muted-foreground"
        >
            Pembersihan lahan
            {{ block.land_clearing_completed_at ? 'selesai' : 'belum dimulai' }}<span v-if="block.land_clearing_completed_at">
                · Dilaporkan {{ blockDate(block.land_clearing_completed_at) }}</span>
        </p>
        <ul
            v-if="!expanded && progressSummary.length"
            class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-muted-foreground"
            aria-label="Ringkasan progres sub blok"
        >
            <li v-for="item in progressSummary" :key="item.label">
                <strong class="font-semibold text-foreground"
                    >{{ item.count }} sub blok</strong
                >
                · {{ item.label }}
            </li>
        </ul>
        <p v-if="!block.is_active" class="text-xs text-muted-foreground">
            Blok nonaktif. Riwayat sub blok tetap tersedia.
        </p>
        <p
            v-else-if="
                block.can.createBatch &&
                Number(block.batch_capacity.available_ha) <= 0
            "
            class="text-xs text-muted-foreground"
        >
            Lahan sudah teralokasi. Sub blok baru dapat ditambahkan setelah
            alokasi berjalan selesai.
        </p>
        <div :id="contentId" v-show="expanded" class="space-y-8">
            <article
                v-for="batch in block.batches"
                :key="batch.id"
                class="border-t border-border pt-6"
            >
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <h5 class="text-lg font-bold text-foreground">
                            Sub blok {{ block.code }}{{ batch.subblock_label }}
                        </h5>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Batch {{ batch.batch_number }} ·
                            {{ statusLabels[batch.status] ?? batch.status }}
                        </p>
                    </div>
                    <Button
                        v-if="batchPermissions[batch.id]"
                        variant="ghost"
                        size="sm"
                        class="block-action"
                        :aria-label="`Ubah sub blok ${block.code}${batch.subblock_label}`"
                        @click="open(batch)"
                        ><Pencil class="size-4" />Ubah</Button
                    >
                </header>
                <dl
                    class="mt-5 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3"
                >
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Luas sub blok
                        </dt>
                        <dd class="mt-1 text-lg font-bold tabular-nums">
                            {{ hectares(batch.reserved_area_ha) }}
                            <span class="text-sm font-medium">ha</span>
                        </dd>
                        <p
                            v-if="Number(batch.planted_area_ha) > 0"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Ditanam {{ hectares(batch.planted_area_ha) }} ha
                        </p>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Tahap saat ini
                        </dt>
                        <dd
                            class="mt-1 text-sm font-semibold"
                            :class="
                                batch.status === 'cancelled'
                                    ? 'text-muted-foreground'
                                    : 'text-primary'
                            "
                        >
                            {{ currentStage(batch) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Rencana tanam
                        </dt>
                        <dd class="mt-1 text-sm font-semibold">
                            {{
                                batch.planned_planting_at
                                    ? blockDate(batch.planned_planting_at)
                                    : 'Belum ditentukan'
                            }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-5 bg-muted/40 p-4 sm:p-5">
                    <h6 class="mb-4 text-sm font-semibold text-primary">
                        Tahap &amp; tanggal progres
                    </h6>
                    <BatchTimeline
                        :stages="batch.stages"
                        :growth="batch.growth"
                    >
                        <template
                            v-if="
                                returnTo === 'farm' && batch.growth?.available
                            "
                            #growth
                        >
                            <BatchPlantGrowth
                                :growth="batch.growth"
                                :planted-at="batch.planted_at"
                                :theme="theme"
                            />
                        </template>
                    </BatchTimeline>
                </div>
            </article>
            <p
                v-if="!block.batches.length"
                class="rounded-xl bg-muted/40 px-4 py-4 text-sm text-muted-foreground"
            >
                Belum ada sub blok.
                {{
                    block.can.createBatch && block.is_active
                        ? 'Tambahkan sub blok untuk memulai pengolahan tanah.'
                        : 'Sub blok akan tampil setelah dibuat.'
                }}
            </p>
        </div>
    </section>
    <BatchDialog
        v-model:open="editing"
        :context="`${farm.name} / ${block.code}`"
        :base="base"
        :capacity="block.batch_capacity"
        :batch="selected"
        :theme="theme"
        :return-to="returnTo"
    />
</template>
