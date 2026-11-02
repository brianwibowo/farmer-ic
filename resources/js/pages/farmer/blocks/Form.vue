<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, LoaderCircle } from '@lucide/vue';
import FarmField from '@/components/farms/FarmField.vue';
import { Button } from '@/components/ui/button';
import BlockLayout from '@/layouts/BlockLayout.vue';
import type { BlockFarm, FarmBlock } from '@/types/blocks';

defineOptions({ layout: BlockLayout });
const props = defineProps<{ farm: BlockFarm; block: FarmBlock | null }>();
const base = `/kebun/${props.farm.id}/blocks`;
const back = props.block ? `${base}/${props.block.id}` : base;
const form = useForm({
    code: props.block?.code ?? '',
    name: props.block?.name ?? '',
    area_ha: props.block?.area_ha ?? '',
    description: props.block?.description ?? '',
    is_active: props.block?.is_active ?? true,
});
const fieldClass =
    'w-full min-h-12 rounded-xl border border-border bg-background px-3 py-2 focus-visible:outline-2 focus-visible:outline-ring aria-invalid:border-error';
function submit(): void {
    if (props.block)
        form.put(`${base}/${props.block.id}`, { preserveScroll: true });
    else form.post(base, { preserveScroll: true });
}
</script>

<template>
    <Head :title="block ? 'Ubah blok' : 'Tambah blok'" />
    <Link
        :href="back"
        class="inline-flex items-center gap-2 text-sm text-muted-foreground"
        ><ArrowLeft class="size-4" />Kembali ke blok</Link
    >
    <header>
        <p class="text-sm text-muted-foreground">{{ farm.name }}</p>
        <h1 class="mt-1 text-2xl font-semibold">
            {{ block ? 'Ubah blok' : 'Tambah blok' }}
        </h1>
        <p class="mt-2 text-sm text-muted-foreground">
            {{
                block
                    ? 'Perubahan luas harus tetap mencukupi alokasi batch berjalan.'
                    : 'Blok baru otomatis memulai tahap Land Clearing.'
            }}
        </p>
    </header>
    <form
        class="max-w-2xl space-y-5 rounded-xl border border-border bg-card p-5 sm:p-6"
        @submit.prevent="submit"
    >
        <fieldset
            :disabled="form.processing"
            class="grid gap-5 disabled:opacity-70 sm:grid-cols-2"
        >
            <FarmField
                id="block-code"
                label="Kode blok"
                required
                :error="form.errors.code"
                hint="Unik per kebun, misalnya B01. Huruf otomatis menjadi kapital."
                v-slot="field"
                ><input
                    :id="field.id"
                    v-model="form.code"
                    :class="fieldClass"
                    required
                    maxlength="30"
                    pattern="[A-Za-z0-9][A-Za-z0-9_\-]*"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.description"
            /></FarmField>
            <FarmField
                id="block-name"
                label="Nama blok"
                required
                :error="form.errors.name"
                v-slot="field"
                ><input
                    :id="field.id"
                    v-model="form.name"
                    :class="fieldClass"
                    required
                    maxlength="150"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.description"
            /></FarmField>
            <FarmField
                id="block-area"
                label="Luas blok (ha)"
                required
                :error="form.errors.area_ha"
                hint="Maksimal 4 angka desimal."
                v-slot="field"
                ><input
                    :id="field.id"
                    v-model="form.area_ha"
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
            <FarmField
                id="block-active"
                label="Status blok"
                :error="form.errors.is_active"
                v-slot="field"
                ><select
                    :id="field.id"
                    v-model="form.is_active"
                    :class="fieldClass"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.description"
                >
                    <option :value="true">Aktif</option>
                    <option :value="false">Nonaktif</option>
                </select></FarmField
            >
            <FarmField
                id="block-description"
                label="Catatan"
                :error="form.errors.description"
                class="sm:col-span-2"
                v-slot="field"
            >
                <textarea
                    :id="field.id"
                    v-model="form.description"
                    :class="fieldClass"
                    rows="3"
                    maxlength="5000"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.description"
                />
            </FarmField>
        </fieldset>
        <p v-if="form.hasErrors" role="alert" class="text-sm text-error">
            Periksa isian yang ditandai. Data belum disimpan.
        </p>
        <div class="flex flex-wrap justify-end gap-3">
            <Button
                v-if="!form.processing"
                as-child
                variant="outline"
                class="h-12 rounded-xl"
                ><Link :href="back">Batal</Link></Button
            ><Button
                type="submit"
                class="h-12 rounded-xl"
                :disabled="form.processing"
                ><LoaderCircle v-if="form.processing" class="animate-spin" />{{
                    form.processing ? 'Menyimpan…' : 'Simpan blok'
                }}</Button
            >
        </div>
    </form>
</template>
