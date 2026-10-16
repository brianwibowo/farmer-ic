<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Activity, ChevronDown, LandPlot, MapPin, Search, Sprout } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import { listKebun } from '@/services/kebunService';
import { clearDemoStorage } from '@/composables/useDemoStorage';
import type { Kebun, SeedType } from '@/data/kebun';

const kebun = ref<Kebun[]>([]);
const searchQuery = ref('');
const selectedSeed = ref<'' | 'all' | SeedType>('');
const isLoading = ref(true);
const errorMessage = ref('');
const resetMessage = ref('');
const seedOptions: Array<'all' | SeedType> = ['all', 'Jagung', 'Rumput', 'Kedelai'];

const filteredKebun = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase();

    return kebun.value.filter((item) => {
        const searchable = [
            item.name, item.city, item.usedHectare.toString(),
            item.availableHectare.toString(), `${item.usedHectare}/${item.availableHectare} ha`,
            item.seedType, item.healthStatus, item.image,
        ].join(' ').toLocaleLowerCase();
        const matchesSearch = !query || searchable.includes(query);
        const matchesSeed = selectedSeed.value === '' || selectedSeed.value === 'all' || item.seedType === selectedSeed.value;

        return matchesSearch && matchesSeed;
    });
});

function formatHectare(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

function healthClass(status: Kebun['healthStatus']): string {
    if (status === 'Sehat') return 'text-success';
    if (status === 'Kritis') return 'text-error';
    return 'text-warning';
}

async function loadKebun(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        kebun.value = await listKebun();
    } catch {
        errorMessage.value = 'Data kebun belum dapat dimuat. Coba lagi.';
    } finally {
        isLoading.value = false;
    }
}

async function resetDemoData(): Promise<void> {
    clearDemoStorage();
    resetMessage.value = 'Data demo kebun telah direset.';
    await loadKebun();
}

onMounted(() => void loadKebun());
</script>

<template>
    <Head title="Kebun" />

    <FarmerMobileShell active-key="garden">
        <div class="px-4 pt-16 pb-5">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-[20px] leading-7 font-semibold text-card-foreground">List Kebun</h1>
                <button type="button" class="text-xs font-medium text-success underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" @click="void resetDemoData()">
                    Reset demo
                </button>
            </div>

            <div class="mt-4 flex gap-2">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">Cari kebun</span>
                    <input v-model="searchQuery" type="search" placeholder="Cari kata kunci" class="h-[38px] w-full rounded-lg border border-primary bg-card px-3 pr-10 text-[14px] text-card-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/30" />
                    <Search :size="20" :stroke-width="1.8" class="absolute top-1/2 right-3 -translate-y-1/2 text-primary" aria-hidden="true" />
                </label>

                <label class="relative w-[94px] shrink-0">
                    <span class="sr-only">Filter jenis benih</span>
                    <select v-model="selectedSeed" class="h-[38px] w-full appearance-none rounded-lg border border-primary bg-card px-3 pr-7 text-[14px] text-card-foreground outline-none focus:ring-2 focus:ring-primary/30">
                        <option value="" disabled hidden>Benih</option>
                        <option v-for="option in seedOptions" :key="option" :value="option">{{ option === 'all' ? 'Semua' : option }}</option>
                    </select>
                    <ChevronDown :size="16" :stroke-width="1.8" class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-primary" aria-hidden="true" />
                </label>
            </div>

            <div v-if="resetMessage" class="mt-4 flex items-center justify-between gap-3 rounded-lg bg-muted px-3 py-2 text-sm text-success" role="status">
                <span>{{ resetMessage }}</span>
                <button type="button" class="shrink-0 rounded px-1 text-lg leading-none text-success/80 hover:text-success focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" aria-label="Tutup notifikasi" @click="resetMessage = ''">&times;</button>
            </div>

            <div v-if="isLoading" class="mt-6 space-y-6" aria-live="polite" aria-label="Memuat data kebun">
                <div v-for="index in 5" :key="index" class="flex animate-pulse gap-5">
                    <div class="h-[74px] w-[78px] shrink-0 rounded-sm bg-muted" />
                    <div class="flex-1 space-y-3 pt-1"><div class="h-4 w-36 rounded bg-muted" /><div class="h-3 w-28 rounded bg-muted" /><div class="h-3 w-32 rounded bg-muted" /></div>
                </div>
            </div>

            <p v-else-if="errorMessage" class="mt-4 rounded-lg bg-error/10 px-3 py-2 text-sm text-error">{{ errorMessage }}</p>

            <div v-else-if="filteredKebun.length === 0" class="mt-12 rounded-xl bg-muted px-5 py-8 text-center">
                <LandPlot :size="34" class="mx-auto text-primary" aria-hidden="true" />
                <h2 class="mt-3 text-base font-semibold text-card-foreground">Kebun tidak ditemukan</h2>
                <p class="mt-1 text-sm text-muted-foreground">Coba ubah kata kunci atau filter benih.</p>
            </div>

            <ul v-else class="mt-6 space-y-10">
                <li v-for="item in filteredKebun" :key="item.id">
                    <Link
                        :href="`/kebun/detail?id=${encodeURIComponent(item.id)}`"
                        class="flex w-full gap-5 text-left focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary"
                    >
                    <img :src="item.image" :alt="`Foto ${item.name}`" class="h-[74px] w-[78px] shrink-0 rounded-xs object-cover" />
                    <div class="min-w-0 flex-1 pt-0.5">
                        <h2 class="truncate text-[16px] leading-5 font-medium text-card-foreground">{{ item.name }}</h2>
                        <div class="mt-1.5 grid grid-cols-2 gap-x-3 gap-y-1 text-[16px] leading-5 text-card-foreground">
                            <span class="flex min-w-0 items-center gap-1.5 truncate"><MapPin :size="18" :stroke-width="1.8" class="shrink-0 text-primary" aria-hidden="true" /><span class="truncate">{{ item.city }}</span></span>
                            <span class="flex min-w-0 items-center gap-1.5 truncate"><Sprout :size="18" :stroke-width="1.8" class="shrink-0 text-primary" aria-hidden="true" /><span class="truncate">{{ item.seedType }}</span></span>
                            <span class="flex min-w-0 items-center gap-1.5 truncate"><LandPlot :size="18" :stroke-width="1.8" class="shrink-0 text-primary" aria-hidden="true" /><span class="truncate">{{ formatHectare(item.usedHectare) }}/{{ formatHectare(item.availableHectare) }} ha</span></span>
                            <span :class="['flex min-w-0 items-center gap-1.5 truncate', healthClass(item.healthStatus)]"><Activity :size="18" :stroke-width="1.8" class="shrink-0" aria-hidden="true" /><span class="truncate">{{ item.healthStatus }}</span></span>
                        </div>
                    </div>
                    </Link>
                </li>
            </ul>
        </div>
    </FarmerMobileShell>
</template>
