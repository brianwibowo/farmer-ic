<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList, LoaderCircle, Save } from '@lucide/vue';
import { computed } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import { Button } from '@/components/ui/button';
import type { SopFormTemplate, SopTeam, SopVariety } from '@/types/sop';

const props = defineProps<{
    team: SopTeam;
    template: SopFormTemplate | null;
    varieties: SopVariety[];
}>();

const form = useForm({
    variety_id: props.template?.variety_id ?? '',
    name: props.template?.name ?? '',
    description: props.template?.description ?? '',
    is_active: props.template?.is_active ?? true,
});
const selectedVariety = computed(() =>
    props.varieties.find((variety) => variety.id === form.variety_id),
);
const backUrl = props.template
    ? `/admin/sops/${props.template.id}`
    : '/admin/sops';
function submit(): void {
    if (props.template) {
        form.put(`/admin/sops/${props.template.id}`, { preserveScroll: true });
    } else {
        form.post('/admin/sops', { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="template ? `Ubah ${template.name}` : 'Tambah SOP'" />
    <div class="mx-auto max-w-4xl space-y-6">
        <Link :href="backUrl" class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary">
            <ArrowLeft class="size-4" />Kembali
        </Link>
        <header>
            <p class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase">SOP / Template</p>
            <h1 class="admin-page-title">{{ template ? 'Ubah template SOP' : 'Tambah template SOP' }}</h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                Template ini dimiliki tenant <strong>{{ team.name }}</strong> dan berlaku untuk semua kebun di tenant tersebut.
            </p>
        </header>

        <form class="space-y-5" @submit.prevent="submit">
            <fieldset :disabled="form.processing" class="space-y-5 disabled:opacity-75">
                <section class="rounded-xl border border-border bg-card p-5 md:p-6">
                    <div class="mb-6 flex items-start gap-3">
                        <div class="rounded-xl bg-accent p-2.5 text-primary"><ClipboardList class="size-5" /></div>
                        <div>
                            <h2 class="admin-section-title">Identitas template</h2>
                            <p class="mt-1 text-xs text-muted-foreground">Satu template mewakili satu varietas pada tenant ini.</p>
                        </div>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
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
                                placeholder="Contoh: SOP Budidaya Jagung Bisi 18"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            />
                        </FarmField>
                        <FarmField
                            id="sop-variety"
                            label="Varietas"
                            required
                            :error="form.errors.variety_id"
                            :hint="template ? 'Varietas tidak dapat diubah setelah template dibuat.' : 'Pilih varietas yang akan memakai SOP ini.'"
                            v-slot="field"
                        >
                            <select
                                :id="field.id"
                                v-model="form.variety_id"
                                class="admin-field"
                                required
                                :disabled="!!template"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <option value="" disabled>Pilih varietas</option>
                                <option v-for="variety in varieties" :key="variety.id" :value="variety.id">
                                    {{ variety.name }} · {{ variety.commodity }}
                                </option>
                            </select>
                        </FarmField>
                        <FarmField id="sop-active" label="Status" :error="form.errors.is_active" v-slot="field">
                            <label class="flex min-h-12 items-center gap-3 rounded-xl border border-border bg-background px-3">
                                <input v-model="form.is_active" type="checkbox" class="size-4 accent-primary" />
                                <span class="text-sm">Aktif dan dapat dipakai untuk batch baru</span>
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
                    <p v-if="selectedVariety && !template" class="mt-4 rounded-xl bg-muted p-3 text-xs text-muted-foreground">
                        SOP akan dibuat untuk <strong>{{ selectedVariety.name }}</strong> ({{ selectedVariety.commodity }}).
                    </p>
                </section>
            </fieldset>

            <div v-if="form.hasErrors" role="alert" class="rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error">
                Periksa isian yang ditandai. Perubahan belum disimpan.
            </div>
            <div class="flex flex-wrap justify-end gap-3">
                <Button v-if="!form.processing" as-child variant="outline" class="h-10 rounded-xl">
                    <Link :href="backUrl">Batal</Link>
                </Button>
                <Button type="submit" class="h-10 rounded-xl" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    <Save v-else />{{ form.processing ? 'Menyimpan…' : 'Simpan template' }}
                </Button>
            </div>
        </form>
    </div>
</template>
