<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, LoaderCircle, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import BatchTimeline from '@/components/blocks/BatchTimeline.vue';
import FarmField from '@/components/farms/FarmField.vue';
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
    type BlockFarm,
    type FarmBlock,
    type PreparationBatch,
} from '@/types/blocks';
import { hectares } from '@/types/farms';

defineOptions({ layout: BlockLayout });
const props = defineProps<{
    farm: BlockFarm;
    block: FarmBlock;
    rounds: { id: string; round_number: number; status: string }[];
    batches: PreparationBatch[];
    can: { update: boolean; delete: boolean; createBatch: boolean };
}>();
const base = `/kebun/${props.farm.id}/blocks/${props.block.id}`;
const batchForm = useForm({ reserved_area_ha: '', subblock_label: '' });
const deleteForm = useForm({});
const deleteError = computed(
    () => (deleteForm.errors as Record<string, string | undefined>).delete,
);
const deleting = ref(false);
function createBatch(): void {
    batchForm.post(`${base}/batches`, {
        preserveScroll: true,
        onSuccess: () => batchForm.reset(),
    });
}
function deleteBlock(): void {
    deleteForm.delete(base, {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = false;
        },
    });
}
const fieldClass =
    'h-12 w-full rounded-xl border border-border bg-background px-3 focus-visible:outline-2 focus-visible:outline-ring aria-invalid:border-error';
</script>

<template>
    <Head :title="`${block.name} · ${farm.name}`" />
    <Link
        :href="`/kebun/${farm.id}/blocks`"
        class="inline-flex items-center gap-2 text-sm text-muted-foreground"
        ><ArrowLeft class="size-4" />Daftar blok</Link
    >
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-muted-foreground">
                {{ farm.name }} / {{ block.code }}
            </p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">
                {{ block.name }}
            </h1>
            <p class="mt-2 text-sm">
                {{ hectares(block.area_ha) }} ha ·
                {{ block.is_active ? 'Aktif' : 'Nonaktif' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <Button
                v-if="can.update"
                as-child
                variant="outline"
                class="h-12 rounded-xl"
                ><Link :href="`${base}/edit`"><Pencil />Ubah blok</Link></Button
            ><Button
                v-if="can.delete"
                variant="outline"
                class="h-12 rounded-xl text-error"
                @click="deleting = true"
                ><Trash2 />Hapus blok</Button
            >
        </div>
    </header>
    <section class="rounded-xl border border-border bg-card p-5">
        <h2 class="text-lg font-semibold">
            Land Clearing
            {{ block.land_clearing_completed_at ? 'selesai' : 'berlangsung' }}
        </h2>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">Mulai</dt>
                <dd class="mt-1">
                    {{ blockDate(block.land_clearing_started_at) }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Selesai</dt>
                <dd class="mt-1">
                    {{
                        block.land_clearing_completed_at
                            ? blockDate(block.land_clearing_completed_at)
                            : 'Otomatis saat batch pertama dibuat'
                    }}
                </dd>
            </div>
        </dl>
        <p
            v-if="block.description"
            class="mt-4 text-sm whitespace-pre-line text-muted-foreground"
        >
            {{ block.description }}
        </p>
    </section>
    <section
        v-if="can.createBatch && block.is_active"
        class="rounded-xl border border-border bg-card p-5 sm:p-6"
    >
        <h2 class="text-lg font-semibold">Buat subblok / batch</h2>
        <p class="mt-2 text-sm leading-6 text-muted-foreground">
            Alokasikan bagian blok untuk satu batch. Pengolahan tanah dimulai
            langsung tanpa persetujuan Admin. Ini belum merupakan laporan
            realisasi tanam.
        </p>
        <form class="mt-5 space-y-4" @submit.prevent="createBatch">
            <fieldset
                :disabled="batchForm.processing"
                class="grid gap-4 sm:grid-cols-2"
            >
                <FarmField
                    id="batch-label"
                    label="Label subblok (opsional)"
                    hint="Kosongkan untuk label otomatis A, B, C, dan seterusnya."
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
                    id="batch-area"
                    label="Alokasi luas (ha)"
                    required
                    :error="batchForm.errors.reserved_area_ha"
                    v-slot="field"
                    ><input
                        :id="field.id"
                        v-model="batchForm.reserved_area_ha"
                        :class="fieldClass"
                        type="number"
                        inputmode="decimal"
                        required
                        min="0.0001"
                        max="99999999.9999"
                        step="0.0001"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                /></FarmField>
            </fieldset>
            <Button
                type="submit"
                class="h-12 rounded-xl"
                :disabled="batchForm.processing"
                ><LoaderCircle
                    v-if="batchForm.processing"
                    class="animate-spin"
                />{{
                    batchForm.processing ? 'Membuat batch…' : 'Buat batch'
                }}</Button
            >
        </form>
    </section>
    <section
        v-else-if="!block.is_active"
        class="rounded-xl bg-muted p-5 text-sm text-muted-foreground"
    >
        Blok nonaktif. Histori tetap dapat dibaca; pembuatan batch baru tidak
        tersedia.
    </section>
    <section class="space-y-4">
        <div>
            <h2 class="text-xl font-semibold">Timeline batch</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Setiap batch memiliki tahap dan tanggalnya sendiri. Nomor batch
                berurutan dalam satu kebun.
            </p>
        </div>
        <article
            v-for="batch in batches"
            :key="batch.id"
            class="space-y-5 rounded-xl border border-border bg-card p-5"
        >
            <header>
                <h3 class="text-lg font-semibold">
                    Batch {{ batch.batch_number }} · {{ block.code
                    }}{{ batch.subblock_label }}
                </h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Periode
                    {{
                        rounds.find(
                            (round) => round.id === batch.planting_round_id,
                        )?.round_number
                    }}
                    · Alokasi {{ hectares(batch.reserved_area_ha) }} ha ·
                    Realisasi tanam {{ hectares(batch.planted_area_ha) }} ha
                </p>
            </header>
            <BatchTimeline :stages="batch.stages" />
        </article>
        <div
            v-if="!batches.length"
            class="rounded-xl border border-dashed border-border bg-card p-7 text-center"
        >
            <h3 class="font-semibold">Belum ada subblok / batch</h3>
            <p class="mt-2 text-sm text-muted-foreground">
                {{
                    can.createBatch && block.is_active
                        ? 'Buat batch pertama setelah persiapan pembagian blok siap.'
                        : 'Batch akan muncul setelah dibuat oleh petugas atau admin.'
                }}
            </p>
        </div>
    </section>
    <Dialog v-model:open="deleting"
        ><DialogContent
            ><DialogHeader
                ><DialogTitle>Hapus {{ block.name }}?</DialogTitle
                ><DialogDescription
                    >Hanya blok tanpa histori periode/batch dan pemetaan yang
                    dapat dihapus. Blok dengan histori dapat dinonaktifkan
                    melalui Ubah blok.</DialogDescription
                ></DialogHeader
            >
            <p v-if="deleteError" role="alert" class="text-sm text-error">
                {{ deleteError }}
            </p>
            <DialogFooter
                ><Button
                    variant="outline"
                    :disabled="deleteForm.processing"
                    @click="deleting = false"
                    >Batal</Button
                ><Button
                    variant="destructive"
                    :disabled="deleteForm.processing"
                    @click="deleteBlock"
                    ><LoaderCircle
                        v-if="deleteForm.processing"
                        class="animate-spin"
                    />{{
                        deleteForm.processing ? 'Menghapus…' : 'Hapus blok'
                    }}</Button
                ></DialogFooter
            ></DialogContent
        ></Dialog
    >
</template>
