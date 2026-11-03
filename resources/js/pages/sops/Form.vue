<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList, LoaderCircle, Save } from '@lucide/vue';
import { computed } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import FormErrorSummary from '@/components/farms/FormErrorSummary.vue';
import { Button } from '@/components/ui/button';
import {
    focusFormErrors,
    useUnsavedChanges,
} from '@/composables/useFormRecovery';
import type { SopCommodity, SopFormTemplate, SopTeam } from '@/types/sop';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    team: SopTeam;
    template: SopFormTemplate | null;
    commodities: SopCommodity[];
}>();

const form = useForm({
    commodity_id: props.template?.commodity_id ?? '',
    name: props.template?.name ?? '',
    description: props.template?.description ?? '',
    is_active: props.template?.is_active ?? true,
});

const errorFields = computed<Record<string, { id: string; label: string }>>(
    () => ({
        name: { id: 'sop-name', label: 'Nama template' },
        commodity_id: { id: 'sop-commodity', label: 'Komoditas' },
        is_active: { id: 'sop-active', label: 'Status' },
        description: { id: 'sop-description', label: 'Deskripsi' },
    }),
);

useUnsavedChanges(() => form.isDirty && !form.processing);

const selectedCommodity = computed(() =>
    props.commodities.find((commodity) => commodity.id === form.commodity_id),
);
const backUrl = props.template
    ? `/admin/sops/${props.template.id}`
    : '/admin/sops';

function submit(): void {
    const options = {
        preserveScroll: true,
        onError: focusFormErrors,
        onSuccess: () => form.defaults(),
    };
    if (props.template) {
        form.put(`/admin/sops/${props.template.id}`, options);
    } else {
        form.post('/admin/sops', options);
    }
}
</script>

<template>
    <Head :title="template ? `Ubah ${template.name}` : 'Tambah SOP'" />
    <div class="mx-auto max-w-4xl space-y-6">
        <Link
            :href="backUrl"
            class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"
        >
            <ArrowLeft class="size-4" />Kembali
        </Link>

        <header>
            <h1 class="admin-page-title">
                {{ template ? 'Ubah template SOP' : 'Tambah template SOP' }}
            </h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                Simpan template SOP untuk tim
                <strong>{{ team.name }}</strong
                >. Berlaku untuk semua kebun yang menanam komoditas yang sama.
                Field bertanda * wajib diisi.
            </p>
        </header>

        <form class="space-y-5" @submit.prevent="submit">
            <FormErrorSummary :errors="form.errors" :fields="errorFields" />

            <fieldset
                :disabled="form.processing"
                class="min-w-0 space-y-5 disabled:opacity-75"
            >
                <section
                    class="admin-card rounded-xl border border-border bg-card p-5 md:p-6"
                >
                    <div class="mb-6 flex items-start gap-3">
                        <div class="rounded-xl bg-accent p-2.5 text-primary">
                            <ClipboardList class="size-5" />
                        </div>
                        <div>
                            <h2 class="admin-section-title">
                                Identitas template
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Satu template mewakili satu komoditas pada
                                tenant ini.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <FarmField
                            id="sop-name"
                            label="Nama template"
                            required
                            :error="form.errors.name"
                            class="sm:col-span-2"
                            v-slot="field"
                        >
                            <input
                                :id="field.id"
                                v-model="form.name"
                                class="admin-field"
                                required
                                maxlength="150"
                                placeholder="Contoh: SOP Budidaya Jagung"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            />
                        </FarmField>

                        <FarmField
                            id="sop-commodity"
                            label="Komoditas"
                            required
                            :error="form.errors.commodity_id"
                            :hint="
                                template
                                    ? 'Komoditas tidak dapat diubah setelah template dibuat.'
                                    : 'Pilih komoditas yang akan memakai SOP ini. Varietas tidak membedakan SOP.'
                            "
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="form.commodity_id"
                                class="admin-field"
                                required
                                :disabled="!!template"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value="" disabled>
                                    Pilih komoditas
                                </AppSelectOption>
                                <AppSelectOption
                                    v-for="commodity in commodities"
                                    :key="commodity.id"
                                    :value="commodity.id"
                                >
                                    {{ commodity.name }}
                                </AppSelectOption>
                            </AppSelect>
                        </FarmField>

                        <FarmField
                            id="sop-active"
                            label="Status"
                            :error="form.errors.is_active"
                            v-slot="field"
                        >
                            <label
                                :for="field.id"
                                class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-border bg-background px-3 transition-colors hover:bg-muted/30"
                            >
                                <input
                                    :id="field.id"
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="size-4 accent-primary"
                                />
                                <span class="text-sm">
                                    Aktif dan dapat dipakai untuk batch baru
                                </span>
                            </label>
                        </FarmField>

                        <FarmField
                            id="sop-description"
                            label="Deskripsi"
                            :error="form.errors.description"
                            class="sm:col-span-2"
                            v-slot="field"
                        >
                            <textarea
                                :id="field.id"
                                v-model="form.description"
                                class="admin-field resize-y"
                                rows="4"
                                maxlength="5000"
                                placeholder="Tujuan SOP, cakupan, atau catatan khusus."
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            />
                        </FarmField>
                    </div>

                    <p
                        v-if="selectedCommodity && !template"
                        class="mt-4 rounded-xl bg-accent/60 p-3 text-xs text-accent-foreground"
                    >
                        SOP akan dibuat untuk komoditas
                        <strong>{{ selectedCommodity.name }}</strong
                        >. Varietas tanam tidak perlu dipilih.
                    </p>
                </section>
            </fieldset>

            <div class="flex flex-wrap justify-end gap-3">
                <Button
                    v-if="!form.processing"
                    as-child
                    variant="outline"
                    class="h-10 rounded-xl"
                >
                    <Link :href="backUrl">Batal</Link>
                </Button>
                <Button
                    type="submit"
                    class="h-10 rounded-xl"
                    :disabled="form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    <Save v-else />
                    {{ form.processing ? 'Menyimpan...' : 'Simpan template' }}
                </Button>
            </div>
        </form>
    </div>
</template>
