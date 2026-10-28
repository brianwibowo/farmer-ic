<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowUpRight, Building2, ChevronLeft, ChevronRight, LandPlot, LoaderCircle, MapPin, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import DeleteFarmDialog from '@/components/farms/DeleteFarmDialog.vue';
import FarmField from '@/components/farms/FarmField.vue';
import { hectares, type FarmFilters, type FarmPage, type FarmRow } from '@/types/farms';

const props = defineProps<{
    farms: FarmPage;
    summary: { count: number; area_ha: string; cities: number };
    filters: FarmFilters;
    team: { id: number; name: string };
    can: { create: boolean };
}>();
const selectedFarm = ref<FarmRow | null>(null);
const filters = useForm({
    search: props.filters.search ?? '',
    city: props.filters.city ?? '',
    province: props.filters.province ?? '',
    sort: props.filters.sort ?? 'name',
    direction: props.filters.direction ?? 'asc',
    per_page: props.filters.per_page ?? 15,
});
const hasFilters = computed(() => !!(filters.search || filters.city || filters.province));
function apply(): void {
    filters.get('/admin/farm-management', { preserveState: true, preserveScroll: true, replace: true });
}
function reset(): void {
    filters.search = ''; filters.city = ''; filters.province = ''; filters.sort = 'name'; filters.direction = 'asc';
    apply();
}
</script>

<template>
    <Head title="Farm Management" />
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase">Operasional / Kebun</p>
                <h1 class="text-2xl font-bold tracking-tight md:text-3xl">Kelola kebun</h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">Pantau dan kelola lahan yang dapat kamu akses.</p>
            </div>
            <Button v-if="can.create" as-child class="h-10 rounded-xl"><Link href="/admin/farm-management/create"><Plus /> Tambah kebun</Link></Button>
        </header>

        <div class="grid gap-4 sm:grid-cols-3">
            <div v-for="item in [
                { label: 'Total kebun', value: summary.count, icon: LandPlot, unit: 'kebun' },
                { label: 'Luas terdaftar', value: hectares(summary.area_ha), icon: MapPin, unit: 'ha' },
                { label: 'Wilayah kota/kabupaten', value: summary.cities, icon: Building2, unit: 'wilayah' },
            ]" :key="item.label" class="rounded-xl border border-border bg-card p-5">
                <div class="flex items-center justify-between gap-3"><p class="text-sm text-muted-foreground">{{ item.label }}</p><component :is="item.icon" class="size-5 text-primary" aria-hidden="true" /></div>
                <p class="mt-4 text-3xl font-bold tracking-tight">{{ item.value }} <span class="text-xs font-medium text-muted-foreground">{{ item.unit }}</span></p>
            </div>
        </div>

        <section class="overflow-hidden rounded-xl border border-border bg-card">
            <div class="border-b border-border p-4 md:p-5">
                <div class="mb-4 flex items-center gap-2"><h2 class="text-base font-semibold">Daftar kebun</h2><span class="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground">{{ farms.total }}</span></div>
                <form class="grid items-start gap-3 sm:grid-cols-2 xl:grid-cols-4" @submit.prevent="apply">
                    <FarmField id="farm-search" label="Cari kebun" :error="filters.errors.search" v-slot="field">
                        <div class="relative"><Search class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground" aria-hidden="true" /><input :id="field.id" v-model="filters.search" class="farm-field pl-9!" type="search" placeholder="Nama atau lokasi kebun" maxlength="150" :aria-invalid="field.invalid" :aria-describedby="field.description" /></div>
                    </FarmField>
                    <FarmField id="farm-city" label="Kota / kabupaten" :error="filters.errors.city" v-slot="field"><input :id="field.id" v-model="filters.city" class="farm-field" placeholder="Semua kota" maxlength="100" :aria-invalid="field.invalid" :aria-describedby="field.description" /></FarmField>
                    <FarmField id="farm-province" label="Provinsi" :error="filters.errors.province" v-slot="field"><input :id="field.id" v-model="filters.province" class="farm-field" placeholder="Semua provinsi" maxlength="100" :aria-invalid="field.invalid" :aria-describedby="field.description" /></FarmField>
                    <FarmField id="farm-sort" label="Urutkan" :error="filters.errors.sort" v-slot="field"><select :id="field.id" v-model="filters.sort" class="farm-field"><option value="name">Nama kebun</option><option value="total_area_ha">Luas lahan</option><option value="created_at">Waktu dibuat</option></select></FarmField>
                    <div class="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-4">
                        <select v-model="filters.direction" class="farm-field w-auto!" aria-label="Arah urutan"><option value="asc">Naik</option><option value="desc">Turun</option></select>
                        <select v-model.number="filters.per_page" class="farm-field w-auto!" aria-label="Jumlah kebun per halaman"><option :value="15">15 / halaman</option><option :value="30">30 / halaman</option><option :value="50">50 / halaman</option></select>
                        <Button type="submit" class="h-10 rounded-xl" :disabled="filters.processing"><LoaderCircle v-if="filters.processing" class="animate-spin" /><Search v-else />Terapkan</Button>
                        <Button v-if="hasFilters" type="button" variant="ghost" :disabled="filters.processing" @click="reset">Reset filter</Button>
                    </div>
                </form>
            </div>
            <div v-if="filters.processing" class="space-y-4 p-6" role="status" aria-label="Memuat daftar kebun"><div v-for="row in 3" :key="row" class="h-12 animate-pulse rounded-xl bg-muted" /><p class="text-sm text-muted-foreground">Memuat kebun…</p></div>
            <div v-else-if="!farms.data.length" class="px-5 py-16 text-center">
                <div class="mx-auto flex size-14 items-center justify-center rounded-xl bg-accent text-primary"><LandPlot class="size-7" /></div>
                <h3 class="mt-4 font-semibold">{{ hasFilters ? 'Kebun tidak ditemukan' : 'Belum ada kebun' }}</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">{{ hasFilters ? 'Coba kata kunci atau wilayah lain untuk menampilkan kebun.' : 'Data kebun yang dapat kamu akses akan muncul di sini.' }}</p>
                <Button v-if="hasFilters" variant="outline" class="mt-5 rounded-xl" @click="reset">Reset filter</Button>
                <Button v-else-if="can.create" as-child class="mt-5 rounded-xl"><Link href="/admin/farm-management/create"><Plus />Tambah kebun pertama</Link></Button>
            </div>
            <template v-else>
                <div class="hidden overflow-x-auto md:block" role="region" aria-label="Tabel daftar kebun" tabindex="0">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/50 text-xs text-muted-foreground"><tr><th scope="col" class="px-5 py-3 font-semibold">Nama kebun</th><th scope="col" class="px-5 py-3 font-semibold">Lokasi</th><th scope="col" class="px-5 py-3 font-semibold">Luas</th><th scope="col" class="px-5 py-3 text-right font-semibold">Tindakan</th></tr></thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="farm in farms.data" :key="farm.id" class="hover:bg-muted/40">
                                <td class="px-5 py-4"><Link :href="`/admin/farm-management/${farm.id}`" class="font-semibold hover:text-primary focus-visible:outline-2 focus-visible:outline-ring">{{ farm.name }}</Link><p class="mt-1 text-xs text-muted-foreground">{{ farm.district ?? 'Kecamatan belum diisi' }}</p></td>
                                <td class="px-5 py-4"><p>{{ farm.city ?? '—' }}</p><p class="mt-1 text-xs text-muted-foreground">{{ farm.province ?? '—' }}</p></td>
                                <td class="px-5 py-4 whitespace-nowrap">{{ hectares(farm.total_area_ha) }} ha</td>
                                <td class="px-5 py-4"><div class="flex justify-end gap-1"><Button as-child variant="ghost" size="icon" :aria-label="`Detail ${farm.name}`"><Link :href="`/admin/farm-management/${farm.id}`"><ArrowUpRight /></Link></Button><Button v-if="farm.can.update" as-child variant="ghost" size="icon" :aria-label="`Ubah ${farm.name}`"><Link :href="`/admin/farm-management/${farm.id}/edit`"><Pencil /></Link></Button><Button v-if="farm.can.delete" variant="ghost" size="icon" class="text-error" :aria-label="`Hapus ${farm.name}`" @click="selectedFarm = farm"><Trash2 /></Button></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="divide-y divide-border md:hidden" aria-label="Daftar kebun">
                    <li v-for="farm in farms.data" :key="farm.id" class="p-4">
                        <Link :href="`/admin/farm-management/${farm.id}`" class="flex items-start justify-between gap-3 font-semibold"><span>{{ farm.name }}</span><ArrowUpRight class="size-4 shrink-0 text-primary" /></Link>
                        <p class="mt-2 text-xs text-muted-foreground">{{ [farm.district, farm.city, farm.province].filter(Boolean).join(', ') || 'Lokasi belum diisi' }}</p>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2"><span class="rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground">{{ hectares(farm.total_area_ha) }} ha</span><div class="flex gap-2"><Button v-if="farm.can.update" as-child variant="outline" size="sm"><Link :href="`/admin/farm-management/${farm.id}/edit`"><Pencil />Ubah</Link></Button><Button v-if="farm.can.delete" variant="ghost" size="sm" class="text-error" @click="selectedFarm = farm"><Trash2 />Hapus</Button></div></div>
                    </li>
                </ul>
                <nav class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-4" aria-label="Pagination kebun">
                    <p class="text-xs text-muted-foreground">Menampilkan {{ farms.from }}–{{ farms.to }} dari {{ farms.total }} kebun</p>
                    <div class="flex items-center gap-2"><Button v-if="farms.prev_page_url" as-child variant="outline" size="sm"><Link :href="farms.prev_page_url" preserve-scroll><ChevronLeft />Sebelumnya</Link></Button><span aria-current="page" class="px-2 text-xs">{{ farms.current_page }} / {{ farms.last_page }}</span><Button v-if="farms.next_page_url" as-child variant="outline" size="sm"><Link :href="farms.next_page_url" preserve-scroll>Berikutnya<ChevronRight /></Link></Button></div>
                </nav>
            </template>
        </section>
    </div>
    <DeleteFarmDialog :farm="selectedFarm" @close="selectedFarm = null" />
</template>
