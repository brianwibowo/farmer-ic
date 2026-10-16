<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ChevronDown,
    Clock,
    Search,
    SquareActivity,
    User,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import {
    activityTypeOptions,
    formatIsoToShortDate,
    parseIndonesianDateToIso,
    type ActivityLog,
    type ActivityType,
} from '@/data/activityLogs';
import { kebunSeedData } from '@/data/kebun';
import { listActivityLogs } from '@/services/activityLogService';

const activities = ref<ActivityLog[]>([]);
const searchQuery = ref('');
const selectedType = ref<'' | 'all' | ActivityType>('');
const startDate = ref('');
const endDate = ref('');
const kebunParam = ref('');

const isFilterOpen = ref(false);
const filterDropdownRef = ref<HTMLElement | null>(null);

const isDateFilterOpen = ref(false);
const dateFilterRef = ref<HTMLElement | null>(null);

const isLoading = ref(true);
const errorMessage = ref('');

const kebunSubtitle = computed(() => {
    if (kebunParam.value) {
        const match = kebunSeedData.find(
            (item) => item.id === kebunParam.value,
        );
        if (match) return match.name;
    }

    return 'Kebon A';
});

const backHref = computed(() => {
    if (kebunParam.value) {
        return `/kebun/detail?kebun=${encodeURIComponent(kebunParam.value)}`;
    }

    return '/kebun/detail';
});

const selectedTypeLabel = computed(() => {
    if (selectedType.value === '' || selectedType.value === 'all') {
        return 'Aktivitas';
    }

    return (
        activityTypeOptions.find((opt) => opt.value === selectedType.value)
            ?.shortLabel ?? 'Aktivitas'
    );
});

const hasActiveDateFilter = computed(
    () => Boolean(startDate.value) || Boolean(endDate.value),
);

const dateFilterLabel = computed(() => {
    if (startDate.value && endDate.value) {
        if (startDate.value === endDate.value) {
            return formatIsoToShortDate(startDate.value);
        }
        return `${formatIsoToShortDate(startDate.value)} – ${formatIsoToShortDate(endDate.value)}`;
    }

    if (startDate.value) {
        return `≥ ${formatIsoToShortDate(startDate.value)}`;
    }

    if (endDate.value) {
        return `≤ ${formatIsoToShortDate(endDate.value)}`;
    }

    return 'Tanggal';
});

function selectTypeOption(value: 'all' | ActivityType): void {
    selectedType.value = value;
    isFilterOpen.value = false;
}

function resetDateFilter(): void {
    startDate.value = '';
    endDate.value = '';
}

function handleDocumentClick(event: MouseEvent): void {
    const target = event.target as Node | null;
    if (!target) return;

    if (
        isFilterOpen.value &&
        filterDropdownRef.value &&
        !filterDropdownRef.value.contains(target)
    ) {
        isFilterOpen.value = false;
    }

    if (
        isDateFilterOpen.value &&
        dateFilterRef.value &&
        !dateFilterRef.value.contains(target)
    ) {
        isDateFilterOpen.value = false;
    }
}

const filteredActivities = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase();

    return activities.value.filter((item) => {
        if (kebunParam.value && item.kebunId !== kebunParam.value) {
            return false;
        }

        const searchable = [
            item.typeLabel,
            item.timestamp,
            item.kebunName,
            item.city,
            item.officerName,
            item.seedType,
        ]
            .join(' ')
            .toLocaleLowerCase();

        const matchesSearch = !query || searchable.includes(query);
        const matchesType =
            selectedType.value === '' ||
            selectedType.value === 'all' ||
            item.type === selectedType.value;

        let matchesDate = true;
        if (startDate.value || endDate.value) {
            const itemIso = parseIndonesianDateToIso(item.timestamp);
            if (!itemIso) {
                matchesDate = false;
            } else {
                const effectiveStart =
                    startDate.value &&
                    endDate.value &&
                    startDate.value > endDate.value
                        ? endDate.value
                        : startDate.value;
                const effectiveEnd =
                    startDate.value &&
                    endDate.value &&
                    startDate.value > endDate.value
                        ? startDate.value
                        : endDate.value;

                if (effectiveStart && itemIso < effectiveStart) {
                    matchesDate = false;
                }
                if (effectiveEnd && itemIso > effectiveEnd) {
                    matchesDate = false;
                }
            }
        }

        return matchesSearch && matchesType && matchesDate;
    });
});

function detailHrefFor(item: ActivityLog): string {
    const params = new URLSearchParams({
        id: item.id,
        from: 'kebun',
    });

    if (kebunParam.value) {
        params.set('kebun', kebunParam.value);
    }

    return `/rincian-aktivitas?${params.toString()}`;
}

function thumbnailFor(item: ActivityLog): string {
    return item.photos[0]?.image ?? '/images/kebun-placeholder.svg';
}

async function loadActivities(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        activities.value = await listActivityLogs();
    } catch {
        errorMessage.value = 'Data aktivitas belum dapat dimuat. Coba lagi.';
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    kebunParam.value = params.get('kebun') ?? '';
    document.addEventListener('pointerdown', handleDocumentClick);
    void loadActivities();
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleDocumentClick);
});
</script>

<template>
    <Head :title="`Aktivitas - ${kebunSubtitle}`" />

    <FarmerMobileShell active-key="garden">
        <div data-kebun-aktivitas-list class="px-4 pt-12 pb-5">
            <!-- Header Row: Back Button + Title/Subtitle + Date Range Filter -->
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2.5">
                    <Link
                        :href="backHref"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-secondary transition-colors hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        aria-label="Kembali ke Detail Kebun"
                    >
                        <ArrowLeft :size="18" :stroke-width="1.8" />
                    </Link>
                    <div class="min-w-0">
                        <h1
                            class="truncate text-[20px] leading-6 font-semibold text-foreground"
                        >
                            Aktivitas
                        </h1>
                        <p
                            class="mt-0.5 truncate text-[16px] leading-5 font-semibold text-secondary"
                        >
                            {{ kebunSubtitle }}
                        </p>
                    </div>
                </div>

                <!-- Timestamp / Date Range Filter Dropdown -->
                <div ref="dateFilterRef" class="relative shrink-0 pt-1">
                    <button
                        type="button"
                        aria-label="Filter rentang tanggal aktivitas"
                        :aria-expanded="isDateFilterOpen"
                        aria-haspopup="dialog"
                        class="inline-flex h-[38px] items-center gap-2 rounded-lg bg-secondary px-3 text-[13px] font-medium text-secondary-foreground shadow-xs transition-opacity hover:opacity-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        @click="isDateFilterOpen = !isDateFilterOpen"
                    >
                        <span class="max-w-[125px] truncate">{{
                            dateFilterLabel
                        }}</span>
                        <CalendarDays
                            :size="17"
                            :stroke-width="1.8"
                            class="shrink-0 text-secondary-foreground"
                            aria-hidden="true"
                        />
                    </button>

                    <div
                        v-if="isDateFilterOpen"
                        role="dialog"
                        aria-label="Pilih rentang tanggal"
                        class="absolute top-[calc(100%+6px)] right-0 z-30 w-[264px] rounded-xl border border-border bg-card p-3.5 shadow-lg"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-foreground">
                                Filter Rentang Tanggal
                            </span>
                            <button
                                v-if="hasActiveDateFilter"
                                type="button"
                                class="text-xs font-medium text-secondary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                @click="resetDateFilter"
                            >
                                Reset
                            </button>
                        </div>

                        <div class="mt-3 space-y-2.5">
                            <label class="block">
                                <span
                                    class="mb-1 block text-[11px] font-medium text-muted-foreground"
                                >
                                    Dari Tanggal
                                </span>
                                <input
                                    v-model="startDate"
                                    type="date"
                                    :max="endDate || undefined"
                                    class="h-9 w-full rounded-lg border border-border bg-card px-2.5 text-xs text-foreground outline-none focus:border-primary focus:ring-2 focus:ring-primary/30"
                                />
                            </label>

                            <label class="block">
                                <span
                                    class="mb-1 block text-[11px] font-medium text-muted-foreground"
                                >
                                    Sampai Tanggal
                                </span>
                                <input
                                    v-model="endDate"
                                    type="date"
                                    :min="startDate || undefined"
                                    class="h-9 w-full rounded-lg border border-border bg-card px-2.5 text-xs text-foreground outline-none focus:border-primary focus:ring-2 focus:ring-primary/30"
                                />
                            </label>
                        </div>

                        <div class="mt-3 flex justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                @click="resetDateFilter"
                            >
                                Hapus
                            </button>
                            <button
                                type="button"
                                class="rounded-lg bg-secondary px-3 py-1.5 text-xs font-semibold text-secondary-foreground transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                @click="isDateFilterOpen = false"
                            >
                                Terapkan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search & Activity Type Filter Row -->
            <div class="mt-4 flex gap-2">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">Cari aktivitas kebun</span>
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Cari kata kunci"
                        class="h-[38px] w-full rounded-lg border border-primary bg-card px-3 pr-10 text-[14px] text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/30"
                    />
                    <Search
                        :size="20"
                        :stroke-width="1.8"
                        class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-primary"
                        aria-hidden="true"
                    />
                </label>

                <div
                    ref="filterDropdownRef"
                    class="relative w-[132px] shrink-0"
                >
                    <button
                        type="button"
                        aria-label="Filter jenis aktivitas"
                        :aria-expanded="isFilterOpen"
                        aria-haspopup="listbox"
                        class="flex h-[38px] w-full items-center justify-between gap-1 rounded-lg border border-primary bg-card px-3 text-[14px] text-foreground outline-none focus:ring-2 focus:ring-primary/30"
                        @click="isFilterOpen = !isFilterOpen"
                    >
                        <span class="truncate">{{ selectedTypeLabel }}</span>
                        <ChevronDown
                            :size="16"
                            :stroke-width="1.8"
                            :class="[
                                'shrink-0 text-primary transition-transform duration-150',
                                isFilterOpen ? 'rotate-180' : '',
                            ]"
                            aria-hidden="true"
                        />
                    </button>

                    <ul
                        v-if="isFilterOpen"
                        role="listbox"
                        aria-label="Pilihan jenis aktivitas"
                        class="absolute inset-x-0 top-[calc(100%+6px)] z-20 w-full rounded-xl border border-border bg-card p-1 shadow-md"
                    >
                        <li>
                            <button
                                type="button"
                                role="option"
                                :aria-selected="
                                    selectedType === '' ||
                                    selectedType === 'all'
                                "
                                :class="[
                                    'flex w-full items-center rounded-lg px-3 py-2 text-left text-[13px] leading-4 transition-colors',
                                    selectedType === '' ||
                                    selectedType === 'all'
                                        ? 'bg-muted font-semibold text-secondary'
                                        : 'text-foreground hover:bg-muted/70',
                                ]"
                                @click="selectTypeOption('all')"
                            >
                                Semua
                            </button>
                        </li>
                        <li
                            v-for="option in activityTypeOptions"
                            :key="option.value"
                        >
                            <button
                                type="button"
                                role="option"
                                :aria-selected="selectedType === option.value"
                                :class="[
                                    'flex w-full items-center rounded-lg px-3 py-2 text-left text-[13px] leading-4 transition-colors',
                                    selectedType === option.value
                                        ? 'bg-muted font-semibold text-secondary'
                                        : 'text-foreground hover:bg-muted/70',
                                ]"
                                @click="selectTypeOption(option.value)"
                            >
                                {{ option.shortLabel }}
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Loading Skeleton -->
            <div
                v-if="isLoading"
                class="mt-6 space-y-6"
                aria-live="polite"
                aria-label="Memuat data aktivitas kebun"
            >
                <div
                    v-for="index in 5"
                    :key="index"
                    class="flex animate-pulse gap-5"
                >
                    <div
                        class="h-[74px] w-[78px] shrink-0 rounded-xs bg-muted"
                    />
                    <div class="flex-1 space-y-3 pt-1">
                        <div class="h-4 w-40 rounded bg-muted" />
                        <div class="h-3 w-36 rounded bg-muted" />
                        <div class="h-3 w-28 rounded bg-muted" />
                    </div>
                </div>
            </div>

            <!-- Error State -->
            <p
                v-else-if="errorMessage"
                class="mt-4 rounded-lg bg-error/10 px-3 py-2 text-sm text-error"
            >
                {{ errorMessage }}
            </p>

            <!-- Empty State -->
            <div
                v-else-if="filteredActivities.length === 0"
                class="mt-12 rounded-xl bg-muted px-5 py-8 text-center"
            >
                <SquareActivity
                    :size="34"
                    class="mx-auto text-primary"
                    aria-hidden="true"
                />
                <h2 class="mt-3 text-base font-semibold text-foreground">
                    Aktivitas tidak ditemukan
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Coba ubah kata kunci, jenis aktivitas, atau rentang tanggal.
                </p>
            </div>

            <!-- Activity List -->
            <ul v-else class="mt-6 space-y-8">
                <li v-for="item in filteredActivities" :key="item.id">
                    <Link
                        :href="detailHrefFor(item)"
                        class="group -m-1 flex gap-5 rounded-lg p-1 transition-colors hover:bg-muted/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        <img
                            :src="thumbnailFor(item)"
                            :alt="`Foto ${item.typeLabel} di ${kebunSubtitle}`"
                            class="h-[74px] w-[78px] shrink-0 rounded-xs object-cover"
                        />
                        <div class="min-w-0 flex-1 pt-0.5">
                            <h2
                                class="truncate text-[16px] leading-5 font-medium text-foreground group-hover:text-secondary dark:group-hover:text-primary"
                            >
                                {{ item.typeLabel }}
                            </h2>
                            <div
                                class="mt-1.5 space-y-1 text-[13px] leading-5 text-foreground"
                            >
                                <p
                                    class="flex min-w-0 items-center gap-1.5 truncate"
                                >
                                    <Clock
                                        :size="16"
                                        :stroke-width="1.8"
                                        class="shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        item.timestamp
                                    }}</span>
                                </p>
                                <p
                                    class="flex min-w-0 items-center gap-1.5 truncate"
                                >
                                    <User
                                        :size="16"
                                        :stroke-width="1.8"
                                        class="shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        item.officerName
                                    }}</span>
                                </p>
                            </div>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>
    </FarmerMobileShell>
</template>

<style>
[data-kebun-aktivitas-list] {
    color-scheme: light;
    --background: #f9faf9;
    --foreground: #070807;
    --surface: #abe7b2;
    --surface-foreground: #070807;
    --card: #ffffff;
    --card-foreground: #070807;
    --popover: #ffffff;
    --popover-foreground: #070807;
    --primary: #68bb6c;
    --primary-foreground: #ffffff;
    --secondary: #1c5e21;
    --secondary-foreground: #ffffff;
    --accent: #93a99f;
    --accent-foreground: #070807;
    --muted: #ebf3ed;
    --muted-foreground: #2a1a0e;
    --text-muted: #2a1a0e;
    --border: #d9d9d9;
    --input: #d9d9d9;
    --ring: #68bb6c;
    --success: #4a943e;
    --success-foreground: #ffffff;
    --warning: #edc92c;
    --warning-foreground: #070807;
    --error: #bc0202;
    --error-foreground: #ffffff;
    --destructive: #bc0202;
    --destructive-foreground: #ffffff;
    --info: #d9d9d9;
    --info-foreground: #070807;
    --disable: #e5e7ea;
    --disable-foreground: #8c9096;
}

main:has(> [data-kebun-aktivitas-list]) {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

main:has(> [data-kebun-aktivitas-list])::-webkit-scrollbar {
    display: none;
}
</style>
