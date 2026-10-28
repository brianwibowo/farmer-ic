<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Info, LandPlot, LoaderCircle, Save } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import FarmField from '@/components/farms/FarmField.vue';
import type { ManagedFarm } from '@/types/farms';

const props = defineProps<{
    farm: ManagedFarm | null;
    owners: { id: string; name: string }[];
    team: { id: number; name: string };
}>();
const form = useForm({
    name: props.farm?.name ?? '',
    owner_id: props.farm?.owner_id ?? '',
    total_area_ha: props.farm?.total_area_ha ?? '',
    address: props.farm?.address ?? '',
    district: props.farm?.district ?? '',
    city: props.farm?.city ?? '',
    province: props.farm?.province ?? '',
    latitude: props.farm?.latitude ?? '',
    longitude: props.farm?.longitude ?? '',
    image_url: props.farm?.image_url ?? '',
});
const backUrl = props.farm ? `/admin/farm-management/${props.farm.id}` : '/admin/farm-management';
function submit(): void {
    if (props.farm) {
        form.put(`/admin/farm-management/${props.farm.id}`, { preserveScroll: true });
    } else {
        form.post('/admin/farm-management', { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="farm ? `Ubah ${farm.name}` : 'Tambah kebun'" />
    <div class="mx-auto max-w-4xl space-y-6">
        <Link :href="backUrl" class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"><ArrowLeft class="size-4" />Kembali</Link>
        <header><p class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase">Farm Management</p><h1 class="text-2xl font-bold md:text-3xl">{{ farm ? 'Ubah kebun' : 'Tambah kebun' }}</h1><p class="mt-2 text-sm leading-6 text-muted-foreground">Simpan informasi lahan untuk tim {{ team.name }}. Field bertanda * wajib diisi.</p></header>
        <form class="space-y-5" @submit.prevent="submit">
            <fieldset :disabled="form.processing" class="space-y-5 disabled:opacity-75">
                <section class="rounded-xl border border-border bg-card p-5 md:p-6">
                    <div class="mb-6 flex items-start gap-3"><div class="rounded-xl bg-accent p-2.5 text-primary"><LandPlot class="size-5" /></div><div><h2 class="font-semibold">Informasi kebun</h2><p class="mt-1 text-xs text-muted-foreground">Identitas dan luas resmi lahan.</p></div></div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <FarmField id="farm-name" label="Nama kebun" required :error="form.errors.name" class="sm:col-span-2" v-slot="field"><input :id="field.id" v-model="form.name" class="farm-field" required maxlength="150" placeholder="Contoh: Kebun Cileuksa" :aria-invalid="field.invalid" :aria-describedby="field.description" /></FarmField>
                        <FarmField id="farm-area" label="Luas lahan (ha)" required :error="form.errors.total_area_ha" :hint="farm ? `Luas tanam aktif: ${farm.active_area_ha} ha. Luas kebun harus mencukupinya.` : 'Gunakan hektare, maksimal 4 angka desimal.'" v-slot="field"><input :id="field.id" v-model="form.total_area_ha" class="farm-field" type="number" inputmode="decimal" required min="0.0001" max="99999999.9999" step="0.0001" placeholder="12.5" :aria-invalid="field.invalid" :aria-describedby="field.description" /></FarmField>
                        <FarmField id="farm-owner" label="Pemilik kebun" :error="form.errors.owner_id" hint="Opsional. Pilih Owner aktif yang menjadi anggota tim kebun." v-slot="field"><select :id="field.id" v-model="form.owner_id" class="farm-field" :aria-invalid="field.invalid" :aria-describedby="field.description"><option value="">Belum ditentukan</option><option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option></select></FarmField>
                    </div>
                </section>
                <section class="rounded-xl border border-border bg-card p-5 md:p-6">
                    <h2 class="font-semibold">Lokasi dan koordinat</h2><p class="mt-1 mb-6 text-xs text-muted-foreground">Detail alamat membantu petugas mengenali lokasi kebun.</p>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <FarmField id="farm-address" label="Alamat lengkap" :error="form.errors.address" class="sm:col-span-2" v-slot="field"><textarea :id="field.id" v-model="form.address" class="farm-field resize-y" rows="3" maxlength="5000" placeholder="Desa, jalan, atau petunjuk menuju lahan" :aria-invalid="field.invalid" :aria-describedby="field.description" /></FarmField>
                        <FarmField v-for="item in [{ key: 'district', label: 'Kecamatan' }, { key: 'city', label: 'Kota / kabupaten' }, { key: 'province', label: 'Provinsi' }] as const" :id="`farm-${item.key}`" :key="item.key" :label="item.label" :error="form.errors[item.key]" v-slot="field"><input :id="field.id" v-model="form[item.key]" class="farm-field" maxlength="100" :aria-invalid="field.invalid" :aria-describedby="field.description" /></FarmField>
                        <div class="hidden sm:block" />
                        <FarmField id="farm-latitude" label="Latitude" :error="form.errors.latitude" hint="Rentang -90 sampai 90." v-slot="field"><input :id="field.id" v-model="form.latitude" class="farm-field" type="number" inputmode="decimal" min="-90" max="90" step="0.0000001" placeholder="-6.5965000" :aria-invalid="field.invalid" :aria-describedby="field.description" /></FarmField>
                        <FarmField id="farm-longitude" label="Longitude" :error="form.errors.longitude" hint="Rentang -180 sampai 180." v-slot="field"><input :id="field.id" v-model="form.longitude" class="farm-field" type="number" inputmode="decimal" min="-180" max="180" step="0.0000001" placeholder="106.9502000" :aria-invalid="field.invalid" :aria-describedby="field.description" /></FarmField>
                    </div>
                    <p class="mt-4 flex items-start gap-2 rounded-xl bg-muted p-3 text-xs leading-5 text-muted-foreground"><Info class="mt-0.5 size-4 shrink-0" />Koordinat opsional. Isi latitude dan longitude bersamaan, atau kosongkan keduanya.</p>
                </section>
                <section class="rounded-xl border border-border bg-card p-5 md:p-6"><h2 class="mb-5 font-semibold">Dokumentasi kebun</h2><FarmField id="farm-image" label="Tautan foto (opsional)" :error="form.errors.image_url" hint="Tautan HTTP/HTTPS. Field ini menyimpan referensi foto, bukan mengunggah file." v-slot="field"><input :id="field.id" v-model="form.image_url" type="url" class="farm-field" maxlength="2048" placeholder="https://…" :aria-invalid="field.invalid" :aria-describedby="field.description" /></FarmField></section>
            </fieldset>
            <div v-if="form.hasErrors" role="alert" class="rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error">Periksa isian yang ditandai. Perubahan belum disimpan.</div>
            <div class="flex flex-wrap justify-end gap-3"><Button v-if="!form.processing" as-child variant="outline" class="h-10 rounded-xl"><Link :href="backUrl">Batal</Link></Button><Button type="submit" class="h-10 rounded-xl" :disabled="form.processing"><LoaderCircle v-if="form.processing" class="animate-spin" /><Save v-else />{{ form.processing ? 'Menyimpan…' : 'Simpan kebun' }}</Button></div>
        </form>
    </div>
</template>
