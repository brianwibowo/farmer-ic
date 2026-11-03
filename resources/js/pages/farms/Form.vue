<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ClipboardList,
    LandPlot,
    LoaderCircle,
    Save,
} from '@lucide/vue';
import { computed, useTemplateRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import FarmField from '@/components/farms/FarmField.vue';
import FormErrorSummary from '@/components/farms/FormErrorSummary.vue';
import FarmRegionFields from '@/components/farms/FarmRegionFields.vue';
import {
    focusFormErrors,
    useUnsavedChanges,
} from '@/composables/useFormRecovery';
import FarmLocationMap from '@/components/farms/FarmLocationMap.vue';
import {
    hectares,
    type FarmSopOption,
    type LandTenure,
    type ManagedFarm,
} from '@/types/farms';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    farm: ManagedFarm | null;
    owners: { id: string; name: string }[];
    sops: FarmSopOption[];
    team: { id: number; name: string };
}>();
const regionFields = useTemplateRef<{
    valid: () => boolean;
    firstIncompleteId: () => string;
}>('regionFields');
const form = useForm({
    name: props.farm?.name ?? '',
    land_tenure: (props.farm?.land_tenure ?? '') as LandTenure | '',
    owner_id: props.farm?.owner_id ?? '',
    default_sop_template_id: props.farm?.default_sop_template_id ?? '',
    total_area_ha: props.farm?.total_area_ha ?? '',
    address: props.farm?.address ?? '',
    district: props.farm?.district ?? '',
    city: props.farm?.city ?? '',
    province: props.farm?.province ?? '',
    district_code: props.farm?.district_code ?? '',
    latitude: props.farm?.latitude ?? '',
    longitude: props.farm?.longitude ?? '',
    image_url: props.farm?.image_url ?? '',
});
const errorFields = computed<Record<string, { id: string; label: string }>>(
    () => ({
        name: { id: 'farm-name', label: 'Nama kebun' },
        total_area_ha: { id: 'farm-area', label: 'Luas lahan' },
        land_tenure: { id: 'farm-tenure', label: 'Status lahan' },
        owner_id: { id: 'farm-owner', label: 'Pemilik' },
        district_code: {
            id: regionFields.value?.firstIncompleteId() ?? 'farm-province',
            label: 'Wilayah',
        },
        address: { id: 'farm-address', label: 'Alamat' },
        image_url: { id: 'farm-image', label: 'Tautan foto' },
    }),
);
useUnsavedChanges(() => form.isDirty && !form.processing);
watch(
    () => [form.district_code, form.city, form.province],
    () => form.clearErrors('district_code', 'district', 'city', 'province'),
);
form.transform(({ owner_id, ...data }) =>
    props.farm && owner_id === props.farm.owner_id
        ? data
        : { ...data, owner_id },
);
const location = computed<[number, number] | null>({
    get: (): [number, number] | null =>
        form.latitude !== '' && form.longitude !== ''
            ? [Number(form.latitude), Number(form.longitude)]
            : null,
    set: (point: [number, number] | null) => {
        form.latitude = point ? point[0].toFixed(7) : '';
        form.longitude = point ? point[1].toFixed(7) : '';
        form.clearErrors('latitude', 'longitude');
    },
});
const backUrl = props.farm
    ? `/admin/farm-management/${props.farm.id}`
    : '/admin/farm-management';
function submit(): void {
    if (regionFields.value && !regionFields.value.valid()) {
        form.setError(
            'district_code',
            'Lengkapi pilihan provinsi, kota/kabupaten, dan kecamatan dari daftar atau kosongkan seluruh wilayah.',
        );
        void focusFormErrors();
        return;
    }
    if (form.district_code && (!form.city || !form.province)) {
        form.setError(
            'district_code',
            'Lengkapi pilihan provinsi, kota/kabupaten, dan kecamatan.',
        );
        void focusFormErrors();
        return;
    }
    const options = {
        preserveScroll: true,
        onError: focusFormErrors,
        onSuccess: () => form.defaults(),
    };
    if (props.farm) {
        form.put(`/admin/farm-management/${props.farm.id}`, options);
    } else {
        form.post('/admin/farm-management', options);
    }
}
</script>

<template>
    <Head :title="farm ? `Ubah ${farm.name}` : 'Tambah kebun'" />
    <div class="mx-auto max-w-4xl space-y-6">
        <Link
            :href="backUrl"
            class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"
            ><ArrowLeft class="size-4" />Kembali</Link
        >
        <header>
            <h1 class="admin-page-title">
                {{ farm ? 'Ubah kebun' : 'Tambah kebun' }}
            </h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                Simpan informasi lahan untuk tim {{ team.name }}. Field bertanda
                * wajib diisi.
            </p>
        </header>
        <form class="space-y-5" @submit.prevent="submit">
            <FormErrorSummary :errors="form.errors" :fields="errorFields" />
            <fieldset
                :disabled="form.processing"
                class="min-w-0 space-y-5 disabled:opacity-75"
            >
                <section
                    class="rounded-xl border border-border bg-card p-5 md:p-6"
                >
                    <div class="mb-6 flex items-start gap-3">
                        <div class="rounded-xl bg-accent p-2.5 text-primary">
                            <LandPlot class="size-5" />
                        </div>
                        <div>
                            <h2 class="admin-section-title">Informasi kebun</h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Identitas dan luas resmi lahan.
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <FarmField
                            id="farm-name"
                            label="Nama kebun"
                            required
                            :error="form.errors.name"
                            class="sm:col-span-2"
                            v-slot="field"
                            ><input
                                :id="field.id"
                                v-model="form.name"
                                class="admin-field"
                                required
                                maxlength="150"
                                placeholder="Contoh: Kebun Cileuksa"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                        /></FarmField>
                        <FarmField
                            id="farm-area"
                            label="Luas lahan (ha)"
                            required
                            :error="form.errors.total_area_ha"
                            :hint="
                                farm
                                    ? `Minimal ${hectares(farm.capacity.minimum_area_ha)} ha. Blok aktif ${hectares(farm.capacity.block_area_ha)} ha; alokasi tanam berjalan ${hectares(farm.capacity.reserved_area_ha)} ha; realisasi aktif ${hectares(farm.active_area_ha)} ha. Angka ini saling membatasi, bukan dijumlahkan.`
                                    : 'Gunakan hektare, maksimal 4 angka desimal.'
                            "
                            v-slot="field"
                            ><input
                                :id="field.id"
                                v-model="form.total_area_ha"
                                class="admin-field"
                                type="number"
                                inputmode="decimal"
                                required
                                :min="
                                    farm?.capacity.minimum_area_ha ?? '0.0001'
                                "
                                max="99999999.9999"
                                step="0.0001"
                                placeholder="12.5"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                        /></FarmField>
                        <FarmField
                            id="farm-tenure"
                            label="Status penguasaan lahan"
                            required
                            :error="form.errors.land_tenure"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="form.land_tenure"
                                class="admin-field"
                                required
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value="" disabled>
                                    Pilih status lahan
                                </AppSelectOption>
                                <AppSelectOption value="owned"
                                    >Milik sendiri</AppSelectOption
                                >
                                <AppSelectOption value="rented"
                                    >Sewa</AppSelectOption
                                >
                                <AppSelectOption value="partnership"
                                    >Kerja sama</AppSelectOption
                                >
                            </AppSelect>
                        </FarmField>
                        <FarmField
                            id="farm-owner"
                            label="Pemilik kebun"
                            :error="form.errors.owner_id"
                            :hint="
                                farm?.owner_restricted
                                    ? 'Pemilik saat ini memiliki akses terbatas. Biarkan pilihan ini untuk mempertahankan penugasan.'
                                    : owners.length
                                      ? 'Opsional. Pilih Owner aktif yang menjadi anggota tim kebun.'
                                      : 'Belum ada Owner yang dapat dipilih sesuai akses kamu.'
                            "
                            v-slot="field"
                            ><AppSelect
                                :id="field.id"
                                v-model="form.owner_id"
                                class="admin-field"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value=""
                                    >Belum ditentukan</AppSelectOption
                                >
                                <AppSelectOption
                                    v-if="
                                        farm?.owner_id &&
                                        !owners.some(
                                            (owner) =>
                                                owner.id === farm?.owner_id,
                                        )
                                    "
                                    :value="farm.owner_id"
                                >
                                    Pertahankan pemilik saat ini · akses
                                    terbatas
                                </AppSelectOption>
                                <AppSelectOption
                                    v-for="owner in owners"
                                    :key="owner.id"
                                    :value="owner.id"
                                >
                                    {{ owner.name }}
                                </AppSelectOption>
                            </AppSelect></FarmField
                        >
                    </div>
                </section>
                <section
                    class="rounded-xl border border-border bg-card p-5 md:p-6"
                >
                    <div class="mb-6 flex items-start gap-3">
                        <div class="rounded-xl bg-accent p-2.5 text-primary">
                            <ClipboardList class="size-5" />
                        </div>
                        <div>
                            <h2 class="admin-section-title">
                                SOP default kebun
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Petugas tidak memilih SOP saat mencatat
                                penanaman. Sistem memakai SOP ini secara
                                otomatis.
                            </p>
                        </div>
                    </div>
                    <FarmField
                        id="farm-default-sop"
                        label="SOP default"
                        :error="form.errors.default_sop_template_id"
                        hint="Pilih template SOP tenant untuk komoditas kebun ini. Varietas tanam tidak memengaruhi pilihan."
                        v-slot="field"
                    >
                        <AppSelect
                            :id="field.id"
                            v-model="form.default_sop_template_id"
                            class="admin-field"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                        >
                            <AppSelectOption value=""
                                >Belum ditentukan</AppSelectOption
                            >
                            <AppSelectOption
                                v-for="sop in sops"
                                :key="sop.id"
                                :value="sop.id"
                            >
                                {{ sop.name }} ·
                                {{
                                    sop.commodity?.name ??
                                    'Komoditas belum diisi'
                                }}{{ sop.is_active ? '' : ' (nonaktif)' }}
                            </AppSelectOption>
                        </AppSelect>
                    </FarmField>
                    <p
                        v-if="!sops.length"
                        class="mt-4 rounded-xl border border-warning/30 bg-warning/10 p-3 text-sm text-warning"
                    >
                        Belum ada template SOP di tenant ini. Buat template
                        terlebih dahulu di menu SOP.
                    </p>
                </section>
                <section
                    class="rounded-xl border border-border bg-card p-5 md:p-6"
                >
                    <h2 class="admin-section-title">Lokasi kebun</h2>
                    <p class="mt-1 mb-6 text-xs text-muted-foreground">
                        Detail alamat membantu petugas mengenali lokasi kebun.
                    </p>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <FarmField
                            id="farm-address"
                            label="Alamat lengkap"
                            :error="form.errors.address"
                            class="sm:col-span-2"
                            v-slot="field"
                        >
                            <textarea
                                :id="field.id"
                                v-model="form.address"
                                class="admin-field resize-y"
                                rows="3"
                                maxlength="5000"
                                placeholder="Desa, jalan, atau petunjuk menuju lahan"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            />
                        </FarmField>
                        <FarmRegionFields
                            ref="regionFields"
                            v-model:district-code="form.district_code"
                            v-model:district="form.district"
                            v-model:city="form.city"
                            v-model:province="form.province"
                            :initial-region="farm?.region ?? null"
                            :legacy="!!farm && !farm.district_code"
                            :error="
                                form.errors.district_code ||
                                form.errors.district ||
                                form.errors.city ||
                                form.errors.province
                            "
                            :disabled="form.processing"
                        />
                    </div>
                    <div class="mt-6 space-y-3">
                        <h3 class="text-sm font-medium">
                            Titik lokasi kebun
                            <span class="font-normal text-muted-foreground"
                                >(opsional)</span
                            >
                        </h3>
                        <FarmLocationMap
                            v-model="location"
                            editable
                            :disabled="form.processing"
                            :error="
                                form.errors.latitude || form.errors.longitude
                            "
                        />
                        <p class="text-xs text-muted-foreground">
                            Pin menandai satu titik lokasi. Luas lahan tetap
                            diisi pada informasi kebun.
                        </p>
                    </div>
                </section>
                <section
                    class="rounded-xl border border-border bg-card p-5 md:p-6"
                >
                    <h2 class="admin-section-title mb-5">Dokumentasi kebun</h2>
                    <FarmField
                        id="farm-image"
                        label="Tautan foto (opsional)"
                        :error="form.errors.image_url"
                        hint="Tautan HTTP/HTTPS. Field ini menyimpan referensi foto, bukan mengunggah file."
                        v-slot="field"
                        ><input
                            :id="field.id"
                            v-model="form.image_url"
                            type="url"
                            class="admin-field"
                            maxlength="2048"
                            placeholder="https://…"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                    /></FarmField>
                </section>
            </fieldset>
            <div class="flex flex-wrap justify-end gap-3">
                <Button
                    v-if="!form.processing"
                    as-child
                    variant="outline"
                    class="h-10 rounded-xl"
                    ><Link :href="backUrl">Batal</Link></Button
                ><Button
                    type="submit"
                    class="h-10 rounded-xl"
                    :disabled="form.processing"
                    ><LoaderCircle
                        v-if="form.processing"
                        class="animate-spin"
                    /><Save v-else />{{
                        form.processing ? 'Menyimpan…' : 'Simpan kebun'
                    }}</Button
                >
            </div>
        </form>
    </div>
</template>
