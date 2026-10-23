<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUp,
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    CircleAlert,
    CircleX,
    LandPlot,
    MapPin,
    Search,
    Sprout,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import { clearDemoStorage } from '@/composables/useDemoStorage';
import type { SeedType } from '@/data/kebun';
import { listMappedKebun, type MappedKebun } from '@/services/kebunService';

const kebun = ref<MappedKebun[]>([]);
const searchQuery = ref('');
const selectedSeed = ref<'all' | SeedType>('all');
const isLoading = ref(true);
const errorMessage = ref('');
const resetMessage = ref('');
const pageRoot = ref<HTMLElement | null>(null);
const showBackToTop = ref(false);
let scrollContainer: HTMLElement | null = null;

const BACK_TO_TOP_THRESHOLD = 160;
const seedOptions: Array<'all' | SeedType> = ['all', 'Jagung', 'Rumput', 'Kedelai'];

const filteredKebun = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase();

    return kebun.value.filter((item) => {
        const searchable = [
            item.name,
            item.city,
            item.district,
            item.seedType,
            item.seedTypeDetail ?? '',
            item.healthStatus,
        ]
            .join(' ')
            .toLocaleLowerCase();
        const matchesSearch = !query || searchable.includes(query);
        const matchesSeed = selectedSeed.value === 'all' || item.seedType === selectedSeed.value;

        return matchesSearch && matchesSeed;
    });
});

function formatHectare(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

function healthMeta(status: MappedKebun['healthStatus']) {
    if (status === 'Sehat') {
        return { icon: CheckCircle2, label: 'Sehat', class: 'border-success/25 bg-success/10 text-success' };
    }
    if (status === 'Kritis') {
        return { icon: CircleX, label: 'Kritis', class: 'border-error/25 bg-error/10 text-error' };
    }
    return { icon: CircleAlert, label: 'Perlu perhatian', class: 'border-warning/45 bg-warning/30 text-warning-foreground' };
}

async function loadKebun(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        kebun.value = await listMappedKebun();
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

function handleScroll(): void {
    showBackToTop.value =
        (scrollContainer?.scrollTop ?? 0) > BACK_TO_TOP_THRESHOLD;
}

function scrollToTop(): void {
    if (!scrollContainer) return;

    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    scrollContainer.scrollTo({
        top: 0,
        behavior: reduceMotion ? 'auto' : 'smooth',
    });
}

onMounted(() => {
    void loadKebun();

    // The page scrolls inside FarmerMobileShell's <main>, not the window.
    scrollContainer = pageRoot.value?.closest('main') ?? null;
    scrollContainer?.addEventListener('scroll', handleScroll, {
        passive: true,
    });
    handleScroll();
});

onBeforeUnmount(() => {
    scrollContainer?.removeEventListener('scroll', handleScroll);
    scrollContainer = null;
});
</script>

<template>
    <Head title="Kebun" />

    <FarmerMobileShell active-key="garden">
        <div ref="pageRoot" data-kebun-list class="px-4 pt-4 pb-5">
            <header class="flex items-start justify-between gap-3">
                <div>
                    <!-- <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">Ruang kerja petani</p> -->
                    <h1 class="mt-1 text-[22px] leading-7 font-semibold text-foreground">Daftar Kebun</h1>
                </div>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-success hover:text-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="void resetDemoData()"
                >
                    Reset demo
                </button>
            </header>

            <div class="mt-5 flex gap-2">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">Cari kebun</span>
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Cari nama, lokasi, atau komoditas"
                        class="h-11 w-full rounded-xl border border-border bg-card px-3 pr-10 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                    <Search :size="19" :stroke-width="1.8" class="absolute top-1/2 right-3 -translate-y-1/2 text-primary" aria-hidden="true" />
                </label>
            </div>

            <div class="mt-2.5" role="group" aria-label="Filter kategori komoditas">
                <div class="period-chip-row flex items-center gap-1.5 overflow-x-auto">
                    <button
                        v-for="option in seedOptions"
                        :key="option"
                        type="button"
                        :aria-pressed="selectedSeed === option"
                        :class="[
                            'inline-flex h-[34px] min-w-0 flex-1 items-center justify-center rounded-lg border px-2.5 text-[13px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            selectedSeed === option
                                ? 'border-secondary bg-secondary text-secondary-foreground'
                                : 'border-border bg-card text-foreground hover:border-primary hover:text-secondary',
                        ]"
                        @click="selectedSeed = option"
                    >
                        <span class="truncate">{{ option === 'all' ? 'Semua' : option }}</span>
                    </button>
                </div>
            </div>

            <div v-if="resetMessage" class="mt-4 flex items-center justify-between gap-3 rounded-xl border border-success/25 bg-success/10 px-3 py-2.5 text-xs text-success" role="status">
                <span>{{ resetMessage }}</span>
                <button type="button" class="rounded p-1 text-base leading-none hover:bg-success/10 focus-visible:outline-2 focus-visible:outline-ring" aria-label="Tutup notifikasi" @click="resetMessage = ''">&times;</button>
            </div>

            <div v-if="isLoading" class="mt-5 space-y-3" aria-live="polite" aria-label="Memuat data kebun">
                <div v-for="index in 4" :key="index" class="flex gap-3 rounded-2xl border border-border bg-card p-3">
                    <div class="size-[84px] shrink-0 animate-pulse rounded-xl bg-muted" />
                    <div class="min-w-0 flex-1 space-y-3 pt-1">
                        <div class="h-4 w-3/4 animate-pulse rounded bg-muted" />
                        <div class="h-3 w-1/2 animate-pulse rounded bg-muted" />
                        <div class="h-3 w-2/3 animate-pulse rounded bg-muted" />
                    </div>
                </div>
            </div>

            <div v-else-if="errorMessage" class="mt-5 rounded-2xl border border-error/25 bg-error/10 px-4 py-4 text-sm text-error" role="alert">
                {{ errorMessage }}
                <button type="button" class="mt-3 rounded-lg bg-error px-3 py-2 text-xs font-semibold text-error-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" @click="void loadKebun()">Coba lagi</button>
            </div>

            <div v-else-if="filteredKebun.length === 0" class="mt-8 rounded-2xl border border-dashed border-border bg-muted/50 px-5 py-10 text-center">
                <LandPlot :size="36" class="mx-auto text-primary" aria-hidden="true" />
                <h2 class="mt-3 text-base font-semibold text-foreground">Kebun tidak ditemukan</h2>
                <p class="mt-1 text-sm leading-5 text-muted-foreground">Coba ubah kata kunci atau filter komoditas.</p>
            </div>

            <ul v-else class="mt-5 space-y-3" aria-label="Daftar kebun">
                <li v-for="item in filteredKebun" :key="item.id">
                    <Link
                        :href="`/kebun/detail?id=${encodeURIComponent(item.id)}`"
                        class="group block rounded-2xl border border-border bg-card p-3 transition-colors hover:border-primary hover:bg-muted/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        <div class="flex gap-3">
                            <img :src="item.image" :alt="`Foto ${item.name}`" class="w-28 shrink-0 self-stretch rounded-xl object-cover" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <h2 class="truncate text-base leading-5 font-semibold text-foreground group-hover:text-secondary">{{ item.name }}</h2>
                                        <p class="mt-1 flex items-center gap-1.5 truncate text-xs text-muted-foreground">
                                            <span class="sr-only">Lokasi:</span><MapPin :size="14" class="shrink-0 text-primary" aria-hidden="true" />{{ item.city }} · {{ item.district }}
                                        </p>
                                    </div>
                                    <ChevronRight :size="18" class="mt-1 shrink-0 text-muted-foreground group-hover:text-primary" aria-hidden="true" />
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                    <span class="flex items-center gap-1.5"><Sprout :size="14" class="text-primary" aria-hidden="true" />{{ item.seedType }}</span>
                                    <span class="flex items-center gap-1.5"><LandPlot :size="14" class="text-primary" aria-hidden="true" />{{ formatHectare(item.usedHectare) }}/{{ formatHectare(item.availableHectare) }} ha</span>
                                </div>
                                <div class="mt-2 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <CalendarDays :size="13" class="shrink-0 text-primary" aria-hidden="true" />
                                    <span>Proyeksi panen:</span>
                                    <span class="font-medium text-secondary">{{ item.harvestAt }}</span>
                                </div>
                                <span :class="['mt-2 inline-flex w-fit items-center gap-1 rounded-full border px-2 py-1 text-[10px] font-semibold', healthMeta(item.healthStatus).class]">
                                    <component :is="healthMeta(item.healthStatus).icon" :size="12" aria-hidden="true" />{{ healthMeta(item.healthStatus).label }}
                                </span>
                            </div>
                        </div>
                    </Link>
                </li>
            </ul>

            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-2 opacity-0"
            >
                <button
                    v-if="showBackToTop"
                    type="button"
                    aria-label="Kembali ke atas"
                    class="absolute right-4 bottom-[calc(92px+env(safe-area-inset-bottom,0px))] z-40 inline-flex size-11 items-center justify-center rounded-full border border-primary/30 bg-primary text-primary-foreground shadow-lg shadow-primary/30 transition-colors hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="scrollToTop"
                >
                    <ArrowUp :size="20" :stroke-width="2" aria-hidden="true" />
                </button>
            </Transition>
        </div>
    </FarmerMobileShell>
</template>

<style>
[data-kebun-list] { color-scheme: light; }
.period-chip-row { margin: -4px; padding: 4px; scrollbar-width: none; -ms-overflow-style: none; }
.period-chip-row::-webkit-scrollbar { display: none; }
main:has(> [data-kebun-list]) { scrollbar-width: none; -ms-overflow-style: none; }
main:has(> [data-kebun-list])::-webkit-scrollbar { display: none; }
</style>
