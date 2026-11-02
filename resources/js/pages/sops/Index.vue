<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    LoaderCircle,
    Pencil,
    Plus,
    Search,
    Sprout,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import DeleteSopDialog from '@/components/sops/DeleteSopDialog.vue';
import { Button } from '@/components/ui/button';
import type { SopFarm, SopFilters, SopPage, SopTemplateRow, SopVariety } from '@/types/sop';

const props = defineProps<{
    sops: SopPage;
    filters: SopFilters;
    farms: SopFarm[];
    varieties: SopVariety[];
    can: { create: boolean };
}>();

const selectedFarmId = ref(props.farms[0]?.id ?? '');
const selectedSop = ref<SopTemplateRow | null>(null);
const createUrl = computed(() =>
    selectedFarmId.value
        ? `/admin/farms/${selectedFarmId.value}/sops/create`
        : '/admin/sops',
);
const form = useForm({
    search: props.filters.search ?? '',
    farm_id: props.filters.farm_id ?? '',
    variety_id: props.filters.variety_id ?? '',
    is_active: props.filters.is_active ?? '',
    sort: props.filters.sort ?? 'name',
    direction: props.filters.direction ?? 'asc',
    per_page: props.filters.per_page ?? 15,
});
const hasFilters = computed(
    () =>
        !!form.search ||
        !!form.farm_id ||
        !!form.variety_id ||
        form.is_active !== '',
);
function apply(): void {
    form.get('/admin/sops', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
function reset(): void {
    form.search = '';
    form.farm_id = '';
    form.variety_id = '';
    form.is_active = '';
    form.sort = 'name';
    form.direction = 'asc';
    apply();
}
</script>

<template>
    <Head title="SOP" />
    <div class="space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase">
                    Operasional / SOP
                </p>
                <h1 class="admin-page-title tracking-tight">Template SOP</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                    Susun langkah budidaya modular per varietas, lalu terapkan ke batch
                    kebun sebagai jadwal HST.
                </p>
            </div>
            <div v-if="can.create && farms.length" class="flex flex-wrap items-end gap-2">
                <label v-if="farms.length > 1" class="space-y-1 text-xs text-muted-foreground">
                    <span>Kebun tujuan</span>
                    <select v-model="selectedFarmId" class="admin-field h-10 min-w-48">
                        <option v-for="farm in farms" :key="farm.id" :value="farm.id">
                            {{ farm.name }}
                        </option>
                    </select>
                </label>
                <Button as-child class="h-10 rounded-xl">
                    <Link :href="createUrl"><Plus />Tambah SOP</Link>
                </Button>
            </div>
        </header>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-border bg-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">Template SOP</p>
                    <ClipboardList class="size-5 text-primary" aria-hidden="true" />
                </div>
                <p class="admin-metric-value mt-4">{{ sops.total }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">Kebun tersedia</p>
                    <Sprout class="size-5 text-primary" aria-hidden="true" />
                </div>
                <p class="admin-metric-value mt-4">{{ farms.length }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">Varietas terdaftar</p>
                    <ClipboardList class="size-5 text-primary" aria-hidden="true" />
                </div>
                <p class="admin-metric-value mt-4">{{ varieties.length }}</p>
            </div>
        </div>

        <section class="overflow-hidden rounded-xl border border-border bg-card">
            <div class="border-b border-border p-4 md:p-5">
                <div class="mb-4 flex items-center gap-2">
                    <h2 class="admin-section-title">Daftar template</h2>
                    <span class="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground">
                        {{ sops.total }}
                    </span>
                </div>
                <form class="grid items-start gap-3 sm:grid-cols-2 xl:grid-cols-4" @submit.prevent="apply">
                    <FarmField id="sop-search" label="Cari SOP" :error="form.errors.search" v-slot="field">
                        <div class="relative">
                            <Search class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground" aria-hidden="true" />
                            <input
                                :id="field.id"
                                v-model="form.search"
                                class="admin-field pl-9!"
                                type="search"
                                maxlength="100"
                                placeholder="Nama SOP atau varietas"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            />
                        </div>
                    </FarmField>
                    <FarmField id="sop-farm" label="Kebun" :error="form.errors.farm_id" v-slot="field">
                        <select :id="field.id" v-model="form.farm_id" class="admin-field">
                            <option value="">Semua kebun</option>
                            <option v-for="farm in farms" :key="farm.id" :value="farm.id">
                                {{ farm.name }}
                            </option>
                        </select>
                    </FarmField>
                    <FarmField id="sop-variety" label="Varietas" :error="form.errors.variety_id" v-slot="field">
                        <select :id="field.id" v-model="form.variety_id" class="admin-field">
                            <option value="">Semua varietas</option>
                            <option v-for="variety in varieties" :key="variety.id" :value="variety.id">
                                {{ variety.name }} · {{ variety.commodity }}
                            </option>
                        </select>
                    </FarmField>
                    <FarmField id="sop-active" label="Status" :error="form.errors.is_active" v-slot="field">
                        <select :id="field.id" v-model="form.is_active" class="admin-field">
                            <option value="">Semua status</option>
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </FarmField>
                    <div class="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-4">
                        <select v-model="form.direction" class="admin-field w-auto!" aria-label="Arah urutan">
                            <option value="asc">Naik</option>
                            <option value="desc">Turun</option>
                        </select>
                        <select v-model.number="form.per_page" class="admin-field w-auto!" aria-label="Jumlah per halaman">
                            <option :value="15">15 / halaman</option>
                            <option :value="30">30 / halaman</option>
                            <option :value="50">50 / halaman</option>
                        </select>
                        <Button type="submit" class="h-10 rounded-xl" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="animate-spin" />
                            <Search v-else />Terapkan
                        </Button>
                        <Button v-if="hasFilters" type="button" variant="ghost" :disabled="form.processing" @click="reset">
                            Reset filter
                        </Button>
                    </div>
                </form>
            </div>

            <div v-if="form.processing" class="space-y-4 p-6" role="status" aria-label="Memuat daftar SOP">
                <div v-for="row in 3" :key="row" class="h-12 animate-pulse rounded-xl bg-muted" />
                <p class="text-sm text-muted-foreground">Memuat SOP.</p>
            </div>
            <div v-else-if="!sops.data.length" class="px-5 py-16 text-center">
                <div class="mx-auto flex size-14 items-center justify-center rounded-xl bg-accent text-primary">
                    <ClipboardList class="size-7" />
                </div>
                <h3 class="mt-4 font-semibold">{{ hasFilters ? 'SOP tidak ditemukan' : 'Belum ada SOP' }}</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                    {{ hasFilters ? 'Coba kata kunci, kebun, atau varietas lain.' : 'Buat template SOP pertama untuk varietas yang kamu tanam.' }}
                </p>
                <Button v-if="hasFilters" variant="outline" class="mt-5 rounded-xl" @click="reset">Reset filter</Button>
                <Button v-else-if="can.create && farms.length" as-child class="mt-5 rounded-xl">
                    <Link :href="createUrl"><Plus />Tambah SOP pertama</Link>
                </Button>
            </div>

            <template v-else>
                <div class="hidden overflow-x-auto md:block" role="region" aria-label="Tabel template SOP" tabindex="0">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/50 text-xs text-muted-foreground">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-semibold">Template</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Kebun</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Varietas</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Versi</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="sop in sops.data" :key="sop.id" class="hover:bg-muted/40">
                                <td class="px-5 py-4">
                                    <Link :href="`/admin/sops/${sop.id}`" class="font-semibold hover:text-primary focus-visible:outline-2 focus-visible:outline-ring">
                                        {{ sop.name }}
                                    </Link>
                                    <p class="mt-1 line-clamp-1 text-xs text-muted-foreground">
                                        {{ sop.description || 'Belum ada deskripsi.' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">{{ sop.farm?.name ?? '—' }}</td>
                                <td class="px-5 py-4">
                                    <p>{{ sop.variety?.name ?? '—' }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ sop.variety?.commodity?.name ?? '' }}</p>
                                </td>
                                <td class="px-5 py-4">{{ sop.versions_count }}</td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="sop.is_active ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'"
                                    >
                                        {{ sop.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-1">
                                        <Button as-child variant="ghost" size="icon" :aria-label="`Lihat ${sop.name}`">
                                            <Link :href="`/admin/sops/${sop.id}`"><ArrowUpRight /></Link>
                                        </Button>
                                        <Button v-if="sop.can.update" as-child variant="ghost" size="icon" :aria-label="`Ubah ${sop.name}`">
                                            <Link :href="`/admin/sops/${sop.id}/edit`"><Pencil /></Link>
                                        </Button>
                                        <Button
                                            v-if="sop.can.delete"
                                            variant="destructive-ghost"
                                            size="icon"
                                            :aria-label="`Hapus ${sop.name}`"
                                            @click="selectedSop = sop"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="divide-y divide-border md:hidden" aria-label="Daftar SOP">
                    <li v-for="sop in sops.data" :key="sop.id" class="p-4">
                        <Link :href="`/admin/sops/${sop.id}`" class="flex items-start justify-between gap-3 font-semibold">
                            <span>{{ sop.name }}</span>
                            <ClipboardList class="size-4 shrink-0 text-primary" />
                        </Link>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ sop.farm?.name }} · {{ sop.variety?.name }}
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground">
                                {{ sop.versions_count }} versi
                            </span>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="sop.is_active ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'"
                            >
                                {{ sop.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Button as-child variant="outline" size="sm">
                                <Link :href="`/admin/sops/${sop.id}`"><ArrowUpRight />Detail</Link>
                            </Button>
                            <Button v-if="sop.can.update" as-child variant="outline" size="sm">
                                <Link :href="`/admin/sops/${sop.id}/edit`"><Pencil />Ubah</Link>
                            </Button>
                            <Button
                                v-if="sop.can.delete"
                                variant="destructive-ghost"
                                size="sm"
                                @click="selectedSop = sop"
                            >
                                <Trash2 />Hapus
                            </Button>
                        </div>
                    </li>
                </ul>
                <nav class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-4" aria-label="Pagination SOP">
                    <p class="text-xs text-muted-foreground">
                        Menampilkan {{ sops.from ?? 0 }}–{{ sops.to ?? 0 }} dari {{ sops.total }} template
                    </p>
                    <div class="flex items-center gap-2">
                        <Button v-if="sops.prev_page_url" as-child variant="outline" size="sm">
                            <Link :href="sops.prev_page_url" preserve-scroll><ChevronLeft />Sebelumnya</Link>
                        </Button>
                        <span aria-current="page" class="px-2 text-xs">{{ sops.current_page }} / {{ sops.last_page }}</span>
                        <Button v-if="sops.next_page_url" as-child variant="outline" size="sm">
                            <Link :href="sops.next_page_url" preserve-scroll>Berikutnya<ChevronRight /></Link>
                        </Button>
                    </div>
                </nav>
            </template>
        </section>
    </div>
    <DeleteSopDialog :sop="selectedSop" @close="selectedSop = null" />
</template>
