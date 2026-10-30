<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeft, ClipboardList, ExternalLink, LandPlot, MapPin, Pencil, Sprout, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import DeleteFarmDialog from '@/components/farms/DeleteFarmDialog.vue';
import FarmLocationMap from '@/components/farms/FarmLocationMap.vue';
import { hectares, landTenureLabel, type ManagedFarm, type FarmPermissions } from '@/types/farms';

defineOptions({ inheritAttrs: false });

const props = defineProps<{ farm: ManagedFarm; can: FarmPermissions & { dailyLogs: boolean } }>();
const deleting = ref(false);
const coordinates = computed<[number, number] | null>(() => props.farm.latitude !== null && props.farm.longitude !== null
    ? [Number(props.farm.latitude), Number(props.farm.longitude)] : null);
</script>

<template>
    <Head :title="farm.name" />
    <div class="space-y-6">
        <Link href="/admin/farm-management" class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"><ArrowLeft class="size-4" />Daftar kebun</Link>
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div><p class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase">Detail kebun</p><h1 class="admin-page-title tracking-tight">{{ farm.name }}</h1><p class="mt-2 flex items-center gap-1 text-sm text-muted-foreground"><MapPin class="size-4 shrink-0" />{{ [farm.city, farm.province].filter(Boolean).join(', ') || 'Lokasi belum diisi' }}</p></div>
            <div class="flex flex-wrap gap-2"><Button v-if="can.forceDelete" as-child variant="destructive-outline" class="h-10 rounded-xl"><Link :href="`/admin/farm-management/${farm.id}/force-delete`"><Trash2 />Force delete</Link></Button><Button v-if="can.delete" variant="destructive-outline" class="h-10 rounded-xl" @click="deleting = true"><Trash2 />Hapus</Button><Button v-if="can.update" as-child class="h-10 rounded-xl"><Link :href="`/admin/farm-management/${farm.id}/edit`"><Pencil />Ubah kebun</Link></Button></div>
        </header>
        <div class="grid gap-4 sm:grid-cols-3">
            <section v-for="metric in [
                { label: 'Luas terdaftar', value: hectares(farm.total_area_ha), unit: 'ha', icon: LandPlot },
                { label: 'Luas tanam aktif', value: hectares(farm.active_area_ha), unit: 'ha', icon: Sprout },
                { label: 'Siklus tanam aktif', value: farm.active_cycles, unit: 'siklus', icon: ClipboardList },
            ]" :key="metric.label" class="rounded-xl border border-border bg-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm text-muted-foreground">{{ metric.label }}</h2><component :is="metric.icon" class="size-5 text-primary" /></div><p class="admin-metric-value mt-4">{{ metric.value }} <span class="text-xs font-medium text-muted-foreground">{{ metric.unit }}</span></p></section>
        </div>
        <div class="grid items-start gap-5 lg:grid-cols-3">
            <section class="rounded-xl border border-border bg-card p-5 md:p-6 lg:col-span-2">
                <h2 class="admin-section-title">Informasi lahan</h2><p class="mt-1 text-xs text-muted-foreground">Identitas dan alamat yang tercatat di sistem.</p>
                <dl class="mt-6 grid gap-6 sm:grid-cols-2">
                    <div><dt class="text-xs text-muted-foreground">Nama kebun</dt><dd class="mt-1.5 font-medium">{{ farm.name }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Tim</dt><dd class="mt-1.5 font-medium">{{ farm.team_name }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Status penguasaan lahan</dt><dd class="mt-1.5 font-medium">{{ landTenureLabel(farm.land_tenure) }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Pemilik</dt><dd class="mt-1.5 font-medium">{{ farm.owner_name ?? 'Belum ditentukan / akses terbatas' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Provinsi</dt><dd class="mt-1.5 font-medium">{{ farm.province ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Kota / kabupaten</dt><dd class="mt-1.5 font-medium">{{ farm.city ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Kecamatan</dt><dd class="mt-1.5 font-medium">{{ farm.district ?? '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-muted-foreground">Alamat lengkap</dt><dd class="mt-1.5 whitespace-pre-line">{{ farm.address ?? 'Alamat belum diisi.' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-muted-foreground">Referensi foto</dt><dd class="mt-1.5"><a v-if="farm.image_url" :href="farm.image_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-primary underline">Buka tautan foto<ExternalLink class="size-3" /></a><span v-else class="text-muted-foreground">Belum ada tautan foto.</span></dd></div>
                </dl>
            </section>
            <div class="space-y-5">
                <section class="rounded-xl border border-border bg-card p-5">
                    <div class="mb-3 flex items-center gap-2"><MapPin class="size-5 text-primary" /><h2 class="admin-section-title">Lokasi kebun</h2></div>
                    <FarmLocationMap v-if="coordinates" :model-value="coordinates" />
                    <p v-else class="text-sm leading-6 text-muted-foreground">Lokasi kebun belum dipilih. Lengkapi pada halaman ubah kebun.</p>
                </section>
                <section v-if="can.dailyLogs" class="rounded-xl border border-border bg-accent p-5 text-accent-foreground"><ClipboardList class="mb-3 size-6" /><h2 class="admin-section-title">Logbook harian</h2><p class="mt-2 text-sm leading-6">Lihat laporan dan hasil kegiatan yang dicatat untuk kebun ini.</p><Button as-child class="mt-4 h-10 w-full rounded-xl"><Link :href="`/kebun/${farm.id}/daily-logs`">Buka logbook</Link></Button></section>
            </div>
        </div>
    </div>
    <DeleteFarmDialog :farm="deleting ? farm : null" @close="deleting = false" />
</template>
