<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CheckCircle2,
    ClipboardList,
    Info,
    LoaderCircle,
    Save,
    Sprout,
} from '@lucide/vue';
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
    <div class="space-y-[18px]">
        <Link
            :href="backUrl"
            class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"
        >
            <ArrowLeft class="size-4" />{{
                template ? 'Kembali ke detail SOP' : 'Kembali ke daftar SOP'
            }}
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-4">
                <div
                    class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-accent text-primary"
                >
                    <ClipboardList class="size-7" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <h1
                        class="admin-page-title tracking-tight break-words text-primary"
                    >
                        {{ template ? 'Ubah template SOP' : 'Tambah template SOP' }}
                    </h1>
                    <p class="mt-1.5 text-sm leading-6 text-muted-foreground">
                        Simpan template SOP untuk tim
                        <strong class="font-semibold text-foreground">{{
                            team.name
                        }}</strong
                        >. Berlaku untuk semua lahan dengan komoditas yang sama.
                    </p>
                </div>
            </div>
        </header>

        <form class="space-y-[18px]" @submit.prevent="submit">
            <FormErrorSummary :errors="form.errors" :fields="errorFields" />

            <div
                class="grid items-start gap-[18px] xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]"
            >
                <fieldset
                    :disabled="form.processing"
                    class="min-w-0 space-y-[18px] disabled:opacity-75"
                >
                    <section class="admin-panel space-y-6 rounded-xl bg-card">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2
                                    class="admin-section-title flex items-center gap-2"
                                >
                                    <ClipboardList
                                        class="size-5 text-primary"
                                        aria-hidden="true"
                                    />Identitas template
                                </h2>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Satu template mewakili satu komoditas pada
                                    tenant ini. Field bertanda * wajib diisi.
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
                                        : 'Pilih komoditas yang akan memakai SOP ini.'
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
                                label="Status template"
                                :error="form.errors.is_active"
                                v-slot="field"
                            >
                                <label
                                    :for="field.id"
                                    class="flex min-h-10 cursor-pointer items-center justify-between gap-3 rounded-xl border border-input bg-background px-3.5 py-2 transition-colors hover:bg-muted/40"
                                >
                                    <span class="flex items-center gap-2.5">
                                        <input
                                            :id="field.id"
                                            v-model="form.is_active"
                                            type="checkbox"
                                            class="size-4 accent-primary"
                                        />
                                        <span class="text-sm font-medium">
                                            Aktif untuk batch baru
                                        </span>
                                    </span>
                                    <span
                                        class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                        :class="
                                            form.is_active
                                                ? 'bg-success/10 text-success'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{ form.is_active ? 'Aktif' : 'Nonaktif' }}
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
                                    placeholder="Tujuan SOP, cakupan kegiatan, atau catatan khusus."
                                    :aria-invalid="field.invalid"
                                    :aria-describedby="field.description"
                                />
                            </FarmField>
                        </div>

                        <div
                            class="flex flex-wrap items-center justify-end gap-3 border-t border-border pt-5"
                        >
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
                                <LoaderCircle
                                    v-if="form.processing"
                                    class="animate-spin"
                                />
                                <Save v-else />
                                {{
                                    form.processing
                                        ? 'Menyimpan...'
                                        : 'Simpan template'
                                }}
                            </Button>
                        </div>
                    </section>
                </fieldset>

                <aside class="min-w-0 space-y-[18px]">
                    <section class="admin-panel space-y-4 rounded-xl bg-card">
                        <h2 class="admin-section-title flex items-center gap-2">
                            <Info
                                class="size-5 text-primary"
                                aria-hidden="true"
                            />Ringkasan cakupan
                        </h2>

                        <dl class="space-y-3 text-sm">
                            <div
                                class="flex items-center justify-between gap-3 rounded-xl bg-background p-3"
                            >
                                <dt
                                    class="flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <Building2 class="size-3.5 text-primary" />Tim
                                </dt>
                                <dd class="font-medium">{{ team.name }}</dd>
                            </div>

                            <div
                                class="flex items-center justify-between gap-3 rounded-xl bg-background p-3"
                            >
                                <dt
                                    class="flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <Sprout class="size-3.5 text-primary" />Komoditas
                                </dt>
                                <dd class="font-medium">
                                    {{
                                        selectedCommodity?.name ??
                                        'Belum dipilih'
                                    }}
                                </dd>
                            </div>

                            <div
                                class="flex items-center justify-between gap-3 rounded-xl bg-background p-3"
                            >
                                <dt
                                    class="flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <CheckCircle2
                                        class="size-3.5 text-primary"
                                    />Status
                                </dt>
                                <dd>
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="
                                            form.is_active
                                                ? 'bg-success/10 text-success'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{
                                            form.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'
                                        }}
                                    </span>
                                </dd>
                            </div>
                        </dl>

                        <p
                            v-if="!template"
                            class="border-t border-border pt-3 text-xs leading-6 text-muted-foreground"
                        >
                            Setelah template tersimpan, Anda dapat membuat versi
                            SOP, menyusun langkah perawatan/panen berdasarkan HST,
                            lalu menerbitkannya agar otomatis dipakai subblok
                            baru.
                        </p>
                    </section>
                </aside>
            </div>
        </form>
    </div>
</template>
