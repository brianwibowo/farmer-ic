<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import FormErrorSummary from '@/components/farms/FormErrorSummary.vue';
import {
    focusFormErrors,
    useUnsavedChanges,
} from '@/composables/useFormRecovery';
import { hectares } from '@/types/farms';
import { Button } from '@/components/ui/button';
import BlockLayout from '@/layouts/BlockLayout.vue';
import type { BlockFarm, FarmBlock } from '@/types/blocks';

defineOptions({ layout: BlockLayout, inheritAttrs: false });
const props = defineProps<{
    farm: BlockFarm;
    block: FarmBlock | null;
    capacity: {
        maximum_area_ha: string;
        minimum_area_ha: string;
        running_batches: number;
    };
}>();
const base = `/kebun/${props.farm.id}/blocks`;
const back = props.block ? `${base}/${props.block.id}` : base;
const form = useForm({
    code: props.block?.code ?? '',
    name: props.block?.name ?? '',
    area_ha: props.block?.area_ha ?? '',
    description: props.block?.description ?? '',
    is_active: props.block?.is_active ?? true,
});
useUnsavedChanges(() => form.isDirty && !form.processing);
const noCapacity = computed(
    () =>
        form.is_active &&
        Number(props.capacity.maximum_area_ha) <
            Number(props.capacity.minimum_area_ha),
);
const errorFields = {
    code: { id: 'block-code', label: 'Kode blok' },
    name: { id: 'block-name', label: 'Nama blok' },
    area_ha: { id: 'block-area', label: 'Luas blok' },
    is_active: { id: 'block-active', label: 'Status' },
    description: { id: 'block-description', label: 'Catatan' },
};
const fieldClass = 'admin-field block-field';
function submit(): void {
    if (noCapacity.value) return;
    if (props.block)
        form.put(`${base}/${props.block.id}`, {
            preserveScroll: true,
            onError: focusFormErrors,
            onSuccess: () => form.defaults(),
        });
    else
        form.post(base, {
            preserveScroll: true,
            onError: focusFormErrors,
            onSuccess: () => form.defaults(),
        });
}
</script>

<template>
    <Head :title="block ? 'Ubah blok' : 'Tambah blok'" />
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <Link
            :href="back"
            class="inline-flex items-center gap-2 text-sm text-muted-foreground"
            ><ArrowLeft class="size-4" />Kembali ke blok</Link
        >
        <header>
            <h1 class="admin-page-title mt-1 text-2xl font-semibold">
                {{ block ? 'Ubah blok' : 'Tambah blok' }}
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ farm.name }} ·
                {{
                    block
                        ? 'Perubahan luas harus tetap mencukupi alokasi batch berjalan.'
                        : 'Blok baru otomatis memulai tahap Land Clearing.'
                }}
            </p>
        </header>
        <form
            class="space-y-5 rounded-xl border border-border bg-card p-5 sm:p-6"
            @submit.prevent="submit"
        >
            <FormErrorSummary :errors="form.errors" :fields="errorFields" />
            <p
                v-if="noCapacity"
                role="status"
                class="rounded-xl bg-muted p-4 text-sm leading-6 text-foreground"
            >
                Kapasitas kebun tidak mencukupi untuk blok aktif ini. Sesuaikan
                alokasi blok yang ada, atau pilih status Nonaktif untuk mencatat
                blok tanpa alokasi baru.
            </p>
            <p
                class="rounded-xl bg-muted p-4 text-sm leading-6 text-muted-foreground"
            >
                Kapasitas untuk blok aktif ini maksimal
                <strong class="text-foreground"
                    >{{ hectares(capacity.maximum_area_ha) }} ha</strong
                >. Minimal {{ hectares(capacity.minimum_area_ha) }} ha untuk
                mencukupi {{ capacity.running_batches }} batch berjalan. Blok
                nonaktif tidak mengalokasikan kapasitas baru; histori dan
                reservasi tetap dijaga.
            </p>
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
                    :hint="`Maksimal 4 desimal. Batas blok aktif ${hectares(capacity.maximum_area_ha)} ha.`"
                    v-slot="field"
                    ><input
                        :id="field.id"
                        v-model="form.area_ha"
                        :class="fieldClass"
                        type="number"
                        inputmode="decimal"
                        required
                        :min="capacity.minimum_area_ha"
                        :max="
                            form.is_active
                                ? capacity.maximum_area_ha
                                : '99999999.9999'
                        "
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
                        <option
                            :value="false"
                            :disabled="capacity.running_batches > 0"
                        >
                            Nonaktif{{
                                capacity.running_batches > 0
                                    ? ' · masih ada batch berjalan'
                                    : ''
                            }}
                        </option>
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
            <div class="flex flex-wrap justify-end gap-3">
                <Button
                    v-if="!form.processing"
                    as-child
                    variant="outline"
                    class="block-action h-10"
                    ><Link :href="back">Batal</Link></Button
                ><Button
                    type="submit"
                    class="block-action h-10"
                    :disabled="form.processing || noCapacity"
                    ><LoaderCircle
                        v-if="form.processing"
                        class="animate-spin"
                    />{{
                        form.processing ? 'Menyimpan…' : 'Simpan blok'
                    }}</Button
                >
            </div>
        </form>
    </div>
</template>
