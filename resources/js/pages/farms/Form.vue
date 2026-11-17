<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    LandPlot,
    LoaderCircle,
    Save,
} from '@lucide/vue';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import { toast } from '@/lib/toast';
import { Button } from '@/components/ui/button';
import FarmField from '@/components/farms/FarmField.vue';
import FormErrorSummary from '@/components/farms/FormErrorSummary.vue';
import FarmRegionFields from '@/components/farms/FarmRegionFields.vue';
import {
    focusFormErrors,
    useUnsavedChanges,
} from '@/composables/useFormRecovery';
import FarmLocationMap from '@/components/farms/FarmLocationMap.vue';
import FarmGeocodingFeedback from '@/components/farms/FarmGeocodingFeedback.vue';
import { useFarmGeocoding } from '@/composables/useFarmGeocoding';
import {
    hectares,
    type LandTenure,
    type ManagedFarm,
} from '@/types/farms';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    farm: ManagedFarm | null;
    team: { id: number; name: string };
    picOptions: { id: string; name: string }[];
}>();
const regionFields = useTemplateRef<{
    valid: () => boolean;
    firstIncompleteId: () => string;
}>('regionFields');
const form = useForm({
    name: props.farm?.name ?? '',
    pic_user_id: props.farm?.pic_user_id ?? '',
    land_tenure: (props.farm?.land_tenure ?? '') as LandTenure | '',
    land_tenure_party: props.farm?.land_tenure_party ?? '',
    total_area_ha: props.farm?.total_area_ha ?? '',
    target_volume_ton: props.farm?.target_volume_ton ?? '',
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
        name: { id: 'farm-name', label: 'Nama lahan' },
        pic_user_id: { id: 'farm-pic', label: 'PIC lahan' },
        total_area_ha: { id: 'farm-area', label: 'Luas lahan' },
        target_volume_ton: {
            id: 'farm-target-volume',
            label: 'Target volume panen',
        },
        land_tenure: { id: 'farm-tenure', label: 'Status lahan' },
        land_tenure_party: { id: 'farm-tenure-party', label: 'Nama pihak terkait' },
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
const requiresLandTenureParty = computed(
    () => form.land_tenure === 'rented' || form.land_tenure === 'partnership',
);
watch(
    () => form.land_tenure,
    () => {
        if (!requiresLandTenureParty.value) form.land_tenure_party = '';
        form.clearErrors('land_tenure_party');
    },
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
const {
    loading: geocodingLoading,
    message: geocodingMessage,
    candidates: geocodingCandidates,
    selected: geocodingSelected,
    focus: geocodingFocus,
    ready: geocodingReady,
    completeAddress,
    retry: retryGeocoding,
    choose: chooseLocation,
    beginManual: beginManualLocation,
    setManual: setManualLocation,
} = useFarmGeocoding({
    address: () => form.address,
    districtCode: () => form.district_code,
    regionValid: () => regionFields.value?.valid() ?? false,
    disabled: () => form.processing,
    location,
    farmId: props.farm?.id,
    notify: (message) => toast.warning(message),
});
const waitingForLocation = ref(false);
async function finishAddress(): Promise<void> {
    await nextTick();
    await completeAddress();
}
const backUrl = props.farm
    ? `/admin/farm-management/${props.farm.id}`
    : '/admin/farm-management';
async function submit(): Promise<void> {
    if (form.processing || waitingForLocation.value) return;
    if (requiresLandTenureParty.value && !form.land_tenure_party.trim()) {
        form.setError(
            'land_tenure_party',
            'Isi nama pemilik lahan atau mitra kerja sama.',
        );
        void focusFormErrors();
        return;
    }
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
    waitingForLocation.value = true;
    try {
        await finishAddress();
    } finally {
        waitingForLocation.value = false;
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
    <Head :title="farm ? `Ubah ${farm.name}` : 'Tambah lahan'" />
    <div class="mx-auto max-w-4xl space-y-[18px]">
        <Link
            :href="backUrl"
            class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"
            ><ArrowLeft class="size-4" />Kembali</Link
        >
        <header>
            <h1 class="admin-page-title">
                {{ farm ? 'Ubah lahan' : 'Tambah lahan' }}
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
                <section class="admin-card rounded-xl bg-card p-5 md:p-6">
                    <div class="mb-6 flex items-start gap-3">
                        <div class="rounded-xl bg-accent p-2.5 text-primary">
                            <LandPlot class="size-5" />
                        </div>
                        <div>
                            <h2 class="admin-section-title">Informasi lahan</h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Identitas dan luas resmi lahan.
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <FarmField
                            id="farm-name"
                            label="Nama lahan"
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
                                placeholder="Contoh: Lahan Cileuksa"
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
                            id="farm-target-volume"
                            label="Target volume panen (ton)"
                            :required="!farm"
                            hint="Target total panen lahan. 1 ton = 1.000 kg."
                            :error="form.errors.target_volume_ton"
                            v-slot="field"
                        >
                            <input
                                :id="field.id"
                                v-model="form.target_volume_ton"
                                class="admin-field"
                                type="number"
                                inputmode="decimal"
                                :required="!farm"
                                min="0.001"
                                max="999999999.999"
                                step="0.001"
                                placeholder="Contoh: 80"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            />
                        </FarmField>
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
                            v-if="requiresLandTenureParty"
                            id="farm-tenure-party"
                            :label="
                                form.land_tenure === 'rented'
                                    ? 'Nama pemilik lahan'
                                    : 'Nama mitra kerja sama'
                            "
                            required
                            hint="Nama orang, perusahaan, atau instansi yang menjadi pihak terkait."
                            :error="form.errors.land_tenure_party"
                            v-slot="field"
                        >
                            <input
                                :id="field.id"
                                v-model="form.land_tenure_party"
                                class="admin-field"
                                type="text"
                                required
                                maxlength="255"
                                placeholder="Contoh: Koperasi Tani Makmur"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            />
                        </FarmField>
                        <FarmField
                            id="farm-pic"
                            label="PIC lahan (opsional)"
                            :error="form.errors.pic_user_id"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="form.pic_user_id"
                                class="admin-field"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value=""
                                    >Belum ditentukan</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="pic in picOptions"
                                    :key="pic.id"
                                    :value="pic.id"
                                    >{{ pic.name }}</AppSelectOption
                                >
                            </AppSelect>
                        </FarmField>
                    </div>
                </section>
                <section
                    class="admin-card rounded-xl bg-card p-5 md:p-6"
                >
                    <h2 class="admin-section-title">Lokasi lahan</h2>
                    <p class="mt-1 mb-6 text-xs text-muted-foreground">
                        Detail alamat membantu petugas mengenali lokasi lahan.
                    </p>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <FarmField
                            id="farm-address"
                            label="Alamat lengkap"
                            hint="Gunakan alamat lokasi lahan. Hindari nama pribadi, nomor telepon, atau informasi rahasia karena alamat dikirim ke layanan pencarian peta."
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
                                @blur="finishAddress"
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
                            @complete="finishAddress"
                        />
                    </div>
                    <div class="mt-6 space-y-3">
                        <h3 class="text-sm font-medium">
                            Titik lokasi lahan
                            <span class="font-normal text-muted-foreground"
                                >(opsional)</span
                            >
                        </h3>
                        <FarmGeocodingFeedback
                            :loading="geocodingLoading"
                            :message="geocodingMessage"
                            :candidates="geocodingCandidates"
                            :selected="geocodingSelected"
                            :ready="geocodingReady"
                            :disabled="form.processing"
                            @retry="retryGeocoding"
                            @choose="chooseLocation"
                        />
                        <FarmLocationMap
                            :model-value="location"
                            :focus="geocodingFocus"
                            geocoding
                            @update:model-value="setManualLocation"
                            @manual-start="beginManualLocation"
                            editable
                            :disabled="form.processing"
                            :error="
                                form.errors.latitude || form.errors.longitude
                            "
                        />
                        <p class="text-xs text-muted-foreground">
                            Pin menandai satu titik lokasi. Luas lahan tetap
                            diisi pada informasi lahan.
                        </p>
                    </div>
                </section>
                <section class="admin-card rounded-xl bg-card p-5 md:p-6">
                    <h2 class="admin-section-title mb-5">Dokumentasi lahan</h2>
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
                    :disabled="form.processing || waitingForLocation"
                    ><LoaderCircle
                        v-if="form.processing || waitingForLocation"
                        class="animate-spin"
                    /><Save v-else />{{
                        waitingForLocation
                            ? 'Menentukan lokasi…'
                            : form.processing
                              ? 'Menyimpan…'
                              : 'Simpan lahan'
                    }}</Button
                >
            </div>
        </form>
    </div>
</template>
