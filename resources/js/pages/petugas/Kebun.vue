<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MapPin, Ruler } from '@lucide/vue';
import { computed } from 'vue';
import FarmLocationMap from '@/components/farms/FarmLocationMap.vue';

const props = defineProps<{
    farm: {
        id: string;
        name: string;
        address: string | null;
        city: string | null;
        totalAreaHa: string;
        latitude: string | null;
        longitude: string | null;
    } | null;
}>();

const coordinates = computed<[number, number] | null>(() => {
    if (!props.farm?.latitude || !props.farm.longitude) return null;

    return [Number(props.farm.latitude), Number(props.farm.longitude)];
});
</script>

<template>
    <Head title="Kebun · Petugas" />

    <header>
        <p class="text-xs font-medium tracking-wide text-muted-foreground">
            Ruang Petugas
        </p>
        <h1 class="mt-2 text-2xl leading-8 font-semibold">Kebun</h1>
        <p class="mt-2 text-sm leading-6 text-muted-foreground">
            Lokasi kebun tugas Anda berdasarkan data yang terdaftar.
        </p>
    </header>

    <section
        v-if="farm"
        class="mt-6 rounded-xl border border-border bg-card p-4"
        aria-label="Kebun tugas"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-foreground">
                    {{ farm.name }}
                </h2>
                <p class="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                    <MapPin class="size-4 shrink-0" aria-hidden="true" />
                    {{ farm.city ?? farm.address ?? 'Lokasi belum dilengkapi' }}
                </p>
            </div>
            <span class="flex shrink-0 items-center gap-1 rounded-lg bg-muted px-2 py-1 text-xs font-medium text-muted-foreground">
                <Ruler class="size-3.5" aria-hidden="true" />
                {{ farm.totalAreaHa }} ha
            </span>
        </div>

        <div class="mt-4">
            <FarmLocationMap :model-value="coordinates" />
        </div>
    </section>

    <section
        v-else
        class="mt-6 flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-border bg-background px-5 py-12 text-center"
        aria-label="Belum ada kebun tugas"
    >
        <div class="mb-4 flex size-14 items-center justify-center rounded-xl bg-muted text-secondary dark:text-primary">
            <MapPin class="size-7" aria-hidden="true" />
        </div>
        <h2 class="text-base font-semibold">Kebun belum ditugaskan</h2>
        <p class="mt-2 max-w-64 text-sm leading-6 text-muted-foreground">
            Hubungi admin untuk mendapatkan penugasan kebun.
        </p>
    </section>
</template>
