<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, LoaderCircle, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import BatchTimeline from '@/components/blocks/BatchTimeline.vue';
import BatchDialog from '@/components/blocks/BatchDialog.vue';
import BlockDialog from '@/components/blocks/BlockDialog.vue';
import MetricInfoTooltip from '@/components/MetricInfoTooltip.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import BlockLayout from '@/layouts/BlockLayout.vue';
import {
    blockDate,
    type BatchCapacity,
    type BlockFarm,
    type FarmBlock,
    type PreparationBatch,
    type BlockFormCapacity,
} from '@/types/blocks';
import { hectares } from '@/types/farms';
defineOptions({ layout: BlockLayout, inheritAttrs: false });
const props = defineProps<{
    farm: BlockFarm;
    block: FarmBlock;
    rounds: { id: string; round_number: number; status: string }[];
    batches: PreparationBatch[];
    batchPermissions: Record<string, boolean>;
    capacity: BatchCapacity;
    blockCapacity: BlockFormCapacity;
    deletionBlocked: boolean;
    can: { update: boolean; delete: boolean; createBatch: boolean };
}>();
const page = usePage();
const theme = computed(() =>
    page.props.navigation?.farms ? 'admin-dashboard-theme' : 'farm-block-theme',
);
const base = `/kebun/${props.farm.id}/blocks/${props.block.id}`;
const editingBlock = ref(false);
const batchDialog = ref(false);
const selectedBatch = ref<PreparationBatch | null>(null);
const deleting = ref(false);
const form = useForm({});
const deleteError = computed(
    () => (form.errors as Record<string, string>).delete,
);
function openBatch(batch: PreparationBatch | null): void {
    selectedBatch.value = batch;
    batchDialog.value = true;
}
</script>
<template>
    <Head :title="`${block.name} · ${farm.name}`" />
    <Link
        v-if="!page.props.navigation?.farms"
        :href="`/kebun/${farm.id}/blocks`"
        class="inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground"
        ><ArrowLeft class="size-4" />Daftar blok</Link
    >
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="admin-page-title text-2xl font-semibold sm:text-3xl">
                {{ block.name }}
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ farm.name }} · {{ block.code }} ·
                {{ hectares(block.area_ha) }} ha ·
                {{ block.is_active ? 'Aktif' : 'Nonaktif' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <Button
                v-if="can.update"
                variant="outline"
                class="block-action"
                @click="editingBlock = true"
                ><Pencil />Ubah blok</Button
            ><Button
                v-if="can.delete"
                variant="destructive-ghost"
                size="icon"
                aria-label="Hapus blok"
                @click="
                    deleting = true;
                    form.clearErrors();
                "
                ><Trash2
            /></Button>
        </div>
    </header>
    <section class="rounded-xl border border-border bg-card px-5 py-4">
        <div
            class="flex flex-wrap items-center justify-between gap-x-8 gap-y-3"
        >
            <div>
                <h2 class="text-sm font-semibold">
                    Land Clearing
                    {{
                        block.land_clearing_completed_at
                            ? 'selesai'
                            : 'berlangsung'
                    }}
                </h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Mulai {{ blockDate(block.land_clearing_started_at)
                    }}<span v-if="block.land_clearing_completed_at">
                        · Selesai
                        {{ blockDate(block.land_clearing_completed_at) }}</span
                    >
                </p>
            </div>
            <div class="flex items-center gap-2">
                <p class="text-sm text-muted-foreground">
                    Lahan tersedia
                    <strong class="ml-1 text-foreground tabular-nums"
                        >{{ hectares(capacity.available_ha) }} ha</strong
                    >
                </p>
                <MetricInfoTooltip
                    label="Lahan tersedia untuk sub blok"
                    :description="`Sisa blok ${hectares(capacity.block_remaining_ha)} ha. Sisa seluruh kebun ${hectares(capacity.farm_remaining_ha)} ha. Batas sub blok mengikuti nilai terkecil dari keduanya.`"
                    example="Sisa blok 6 ha dan sisa kebun 4 ha berarti maksimal sub blok baru 4 ha."
                    :content-class="theme"
                    touch-target
                />
            </div>
        </div>
        <p
            v-if="block.description"
            class="mt-3 border-t border-border pt-3 text-sm whitespace-pre-line text-muted-foreground"
        >
            {{ block.description }}
        </p>
    </section>
    <section class="space-y-4">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="admin-section-title text-lg font-semibold">
                Sub blok
                <span class="ml-1 text-sm font-normal text-muted-foreground">{{
                    batches.length
                }}</span>
            </h2>
            <Button
                v-if="can.createBatch && block.is_active"
                class="block-action"
                :disabled="Number(capacity.available_ha) <= 0"
                @click="openBatch(null)"
                ><Plus />Tambah sub blok</Button
            >
        </header>
        <p
            v-if="!block.is_active"
            class="rounded-xl bg-muted px-4 py-3 text-sm text-muted-foreground"
        >
            Blok nonaktif. Histori tetap tersedia.
        </p>
        <p
            v-else-if="can.createBatch && Number(capacity.available_ha) <= 0"
            class="text-sm text-muted-foreground"
        >
            Lahan sudah teralokasi. Sub blok baru dapat ditambahkan setelah
            alokasi berjalan selesai.
        </p>
        <article
            v-for="batch in batches"
            :key="batch.id"
            class="rounded-xl border border-border bg-card p-5 sm:p-6"
        >
            <header
                class="mb-5 flex flex-wrap items-start justify-between gap-3 border-b border-border pb-4"
            >
                <div>
                    <h3 class="font-semibold">
                        {{ block.code }}{{ batch.subblock_label }}
                        <span
                            class="ml-2 text-xs font-normal text-muted-foreground"
                            >Batch {{ batch.batch_number }} · Periode
                            {{
                                rounds.find(
                                    (round) =>
                                        round.id === batch.planting_round_id,
                                )?.round_number
                            }}</span
                        >
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Alokasi
                        <strong class="font-medium text-foreground"
                            >{{ hectares(batch.reserved_area_ha) }} ha</strong
                        ><span v-if="Number(batch.planted_area_ha) > 0">
                            · Ditanam
                            {{ hectares(batch.planted_area_ha) }} ha</span
                        >
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Rencana tanam:
                        {{
                            batch.planned_planting_at
                                ? blockDate(batch.planned_planting_at)
                                : 'Belum ditentukan'
                        }}
                    </p>
                </div>
                <Button
                    v-if="batchPermissions[batch.id]"
                    variant="ghost"
                    size="sm"
                    class="block-action"
                    :aria-label="`Ubah sub blok ${block.code}${batch.subblock_label}`"
                    @click="openBatch(batch)"
                    ><Pencil class="size-4" />Ubah</Button
                >
            </header>
            <BatchTimeline :stages="batch.stages" />
        </article>
        <div
            v-if="!batches.length"
            class="rounded-xl border border-dashed border-border bg-card p-8 text-center"
        >
            <h3 class="font-semibold">Belum ada sub blok</h3>
            <p class="mt-2 text-sm text-muted-foreground">
                {{
                    can.createBatch && block.is_active
                        ? 'Tambahkan sub blok untuk mulai pengolahan tanah.'
                        : 'Sub blok akan tampil setelah dibuat.'
                }}
            </p>
        </div>
    </section>
    <BatchDialog
        v-model:open="batchDialog"
        :context="`${farm.name} / ${block.code}`"
        :base="base"
        :capacity="capacity"
        :batch="selectedBatch"
        :theme="theme"
    />
    <BlockDialog
        v-model:open="editingBlock"
        :farm="farm"
        :block="block"
        :capacity="blockCapacity"
        :theme="theme"
    />
    <Dialog
        :open="deleting"
        @update:open="
            (value) => {
                if (!form.processing) deleting = value;
            }
        "
        ><DialogContent
            :class="theme"
            :show-close-button="!form.processing"
            @interact-outside.prevent
            @escape-key-down="
                (event) => {
                    if (form.processing) event.preventDefault();
                }
            "
            ><DialogHeader
                ><DialogTitle>Hapus {{ block.name }}?</DialogTitle
                ><DialogDescription>{{
                    deletionBlocked
                        ? 'Blok memiliki histori. Nonaktifkan melalui Ubah blok setelah seluruh sub blok selesai.'
                        : 'Blok ini akan dihapus dari kebun.'
                }}</DialogDescription></DialogHeader
            >
            <p v-if="deleteError" role="alert" class="text-sm text-error">
                {{ deleteError }}
            </p>
            <DialogFooter
                ><Button
                    variant="outline"
                    :disabled="form.processing"
                    @click="deleting = false"
                    >Batal</Button
                ><Button
                    v-if="!deletionBlocked"
                    variant="destructive"
                    :disabled="form.processing"
                    @click="
                        form.delete(base, {
                            onSuccess: () => (deleting = false),
                        })
                    "
                    ><LoaderCircle
                        v-if="form.processing"
                        class="animate-spin"
                    />{{
                        form.processing ? 'Menghapus…' : 'Hapus blok'
                    }}</Button
                ></DialogFooter
            ></DialogContent
        ></Dialog
    >
</template>
