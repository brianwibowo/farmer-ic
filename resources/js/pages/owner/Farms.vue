<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight, LandPlot, MapPin, Ruler, Sprout } from '@lucide/vue';
import SummaryStatCard from '@/components/SummaryStatCard.vue';
import { watch } from 'vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import OwnerPagination from '@/components/owner/OwnerPagination.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useOwnerReadFilters } from '@/composables/useOwnerReadFilters';
import type { OwnerFarmRow, OwnerPaginator } from '@/types/owner';
import { ownerArea } from '@/types/owner';

const props = defineProps<{
    farms: OwnerPaginator<OwnerFarmRow>;
    summary: { count: number; area_ha: string; cities: number };
    locationOptions: { cities: string[] };
    filters: { search?: string; city?: string };
}>();
const defaults = { search: '', city: '' };
const values = () => ({
    search: props.filters.search ?? '',
    city: props.filters.city ?? '',
});
const { filters, loading, error, feedback, submit, reset, sync } =
    useOwnerReadFilters('/kebun', values());
watch(
    () => props.filters,
    () => sync(values()),
);
</script>

<template>
    <Head title="Lahan" />
    <OwnerPageHeader
        title="Lahan"
        description="Lihat lahan, blok, dan perjalanan tanam pada tenant aktif."
    />
    <div class="grid grid-cols-2 gap-3">
        <SummaryStatCard label="Total lahan" :value="summary.count" :icon="LandPlot" />
        <SummaryStatCard label="Luas terdaftar (ha)" :value="ownerArea(summary.area_ha)" :icon="Ruler" />
    </div>
    <form
        class="space-y-3 rounded-xl border border-border p-4"
        :aria-busy="loading"
        @submit.prevent="submit()"
    >
        <div>
            <label for="farm-search" class="mb-1 block text-xs font-medium"
                >Cari lahan atau lokasi</label
            ><Input
                id="farm-search"
                v-model="filters.search"
                maxlength="150"
                placeholder="Nama lahan, alamat, atau wilayah"
                :disabled="loading"
            />
        </div>
        <div>
            <label for="farm-city" class="mb-1 block text-xs font-medium"
                >Kabupaten/kota</label
            ><AppSelect
                id="farm-city"
                v-model="filters.city"
                :disabled="loading"
                ><AppSelectOption value="">Semua kabupaten/kota</AppSelectOption
                ><AppSelectOption
                    v-for="city in locationOptions.cities"
                    :key="city"
                    :value="city"
                    >{{ city }}</AppSelectOption
                ></AppSelect
            >
        </div>
        <div class="flex gap-2">
            <Button type="submit" :disabled="loading" class="min-h-11">{{
                loading ? 'Mencari…' : 'Terapkan'
            }}</Button
            ><Button
                v-if="filters.search || filters.city"
                type="button"
                variant="outline"
                :disabled="loading"
                class="min-h-11"
                @click="reset(defaults)"
                >Hapus filter</Button
            >
        </div>
        <p
            v-if="error || feedback"
            :role="error ? 'alert' : 'status'"
            class="text-xs"
            :class="error ? 'text-error' : 'text-muted-foreground'"
        >
            {{ error || feedback }}
        </p>
    </form>
    <p class="text-xs text-muted-foreground">
        {{ farms.total }} lahan ditemukan
    </p>
    <div
        v-if="!farms.data.length"
        role="status"
        class="rounded-xl border border-dashed border-border p-6 text-center"
    >
        <Sprout class="mx-auto size-7 text-muted-foreground" />
        <h2 class="mt-3 text-base font-semibold">
            {{
                filters.search || filters.city
                    ? 'Lahan tidak ditemukan'
                    : 'Belum ada lahan'
            }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-muted-foreground">
            {{
                filters.search || filters.city
                    ? 'Coba ubah kata kunci atau hapus filter.'
                    : 'Lahan akan muncul setelah dicatat oleh Admin.'
            }}
        </p>
    </div>
    <div class="space-y-3">
        <Link
            v-for="farm in farms.data"
            :key="farm.id"
            :href="`/kebun/${farm.id}`"
            class="block space-y-3 rounded-xl border border-border bg-card p-4 hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
            ><div class="flex items-start justify-between gap-3">
                <h2 class="text-base font-semibold break-words">
                    {{ farm.name }}
                </h2>
                <ArrowUpRight
                    class="size-5 shrink-0 text-secondary dark:text-primary"
                />
            </div>
            <p
                class="flex items-start gap-1.5 text-xs leading-5 text-muted-foreground"
            >
                <MapPin class="mt-0.5 size-3.5 shrink-0" />{{
                    [farm.district, farm.city, farm.province]
                        .filter(Boolean)
                        .join(', ') || 'Lokasi belum tercatat'
                }}
            </p>
            <p class="text-lg font-semibold">
                {{ ownerArea(farm.total_area_ha) }}
                <span class="text-xs font-normal text-muted-foreground"
                    >ha · luas terdaftar</span
                >
            </p></Link
        >
    </div>
    <OwnerPagination :paginator="farms" />
</template>
