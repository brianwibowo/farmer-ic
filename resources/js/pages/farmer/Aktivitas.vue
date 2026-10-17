<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Clock, MapPin, Search, SquareActivity } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import ActivityDateRangeFilter from '@/components/farmer/ActivityDateRangeFilter.vue';
import ActivityTypeFilterDropdown from '@/components/farmer/ActivityTypeFilterDropdown.vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import { clearDemoStorage } from '@/composables/useDemoStorage';
import {
    parseIndonesianDateToIso,
    type ActivityLog,
    type ActivityType,
} from '@/data/activityLogs';
import type { Kebun } from '@/data/kebun';
import { listActivityLogs } from '@/services/activityLogService';
import { listKebun } from '@/services/kebunService';

const page = usePage();
const activities = ref<ActivityLog[]>([]);
const kebunList = ref<Kebun[]>([]);
const searchQuery = ref('');
const selectedType = ref<'' | 'all' | ActivityType>('');
const startDate = ref('');
const endDate = ref('');
const isLoading = ref(true);
const errorMessage = ref('');
const resetMessage = ref('');

const kebunParam = computed(() => {
    try {
        const url = new URL(page.url, 'http://localhost');
        return (
            url.searchParams.get('kebun') ||
            url.searchParams.get('id') ||
            ''
        );
    } catch {
        return '';
    }
});

const currentKebun = computed(() => {
    if (!kebunParam.value) return undefined;
    return kebunList.value.find((item) => item.id === kebunParam.value);
});

const currentKebunName = computed(() => {
    if (!kebunParam.value) return '';
    if (currentKebun.value) return currentKebun.value.name;
    const match = activities.value.find(
        (item) => item.kebunId === kebunParam.value,
    );
    return match?.kebunName ?? '';
});

const backHref = computed(() => {
    if (kebunParam.value) {
        return `/kebun/detail?id=${encodeURIComponent(kebunParam.value)}`;
    }
    return '/kebun';
});

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

function thumbnailFor(item: ActivityLog): string {
    return item.photos[0]?.image ?? '/images/kebun-placeholder.svg';
}

async function loadActivities(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const [activityData, kebunData] = await Promise.all([
            listActivityLogs(),
            listKebun(),
        ]);
        activities.value = activityData;
        kebunList.value = kebunData;
    } catch {
        errorMessage.value = 'Data aktivitas belum dapat dimuat. Coba lagi.';
    } finally {
        isLoading.value = false;
    }
}

async function resetDemoData(): Promise<void> {
    clearDemoStorage();
    searchQuery.value = '';
    selectedType.value = '';
    startDate.value = '';
    endDate.value = '';
    resetMessage.value = 'Data demo aktivitas telah direset.';
    await loadActivities();
}

onMounted(() => void loadActivities());
</script>

<template>
    <Head :title="currentKebunName ? `Aktivitas - ${currentKebunName}` : 'Aktivitas'" />

    <FarmerMobileShell :active-key="kebunParam ? 'garden' : 'activity'">
        <div data-aktivitas-list class="px-4 pt-16 pb-5">
            <div
                class="flex justify-between gap-3"
                :class="currentKebunName ? 'items-start' : 'items-center'"
            >
                <div class="flex min-w-0 items-center gap-2.5">
                    <Link
                        v-if="kebunParam"
                        :href="backHref"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-secondary transition-colors hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        aria-label="Kembali ke Detail Kebun"
                    >
                        <ArrowLeft :size="18" :stroke-width="1.8" />
                    </Link>
                    <div class="min-w-0">
                        <h1
                            class="truncate text-[20px] leading-7 font-semibold text-foreground"
                        >
                            Aktivitas
                        </h1>
                        <p
                            v-if="currentKebunName"
                            class="mt-0.5 truncate text-[15px] leading-5 font-semibold text-secondary"
                        >
                            {{ currentKebunName }}
                        </p>
                    </div>
                </div>

                <div
                    class="flex shrink-0 items-center gap-2"
                    :class="currentKebunName ? 'pt-1' : ''"
                >
                    <button
                        type="button"
                        class="text-xs font-medium text-success underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        @click="void resetDemoData()"
                    >
                        Reset demo
                    </button>

                    <!-- Filter rentang tanggal -->
                    <ActivityDateRangeFilter
                        v-model:start-date="startDate"
                        v-model:end-date="endDate"
                    />
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">Cari aktivitas</span>
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

                <ActivityTypeFilterDropdown v-model="selectedType" />
            </div>

            <div
                v-if="resetMessage"
                class="mt-4 flex items-center justify-between gap-3 rounded-lg bg-muted px-3 py-2 text-sm text-success"
                role="status"
            >
                <span>{{ resetMessage }}</span>
                <button
                    type="button"
                    class="shrink-0 rounded px-1 text-lg leading-none text-success/80 hover:text-success focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    aria-label="Tutup notifikasi"
                    @click="resetMessage = ''"
                >
                    &times;
                </button>
            </div>

            <div
                v-if="isLoading"
                class="mt-6 space-y-6"
                aria-live="polite"
                aria-label="Memuat data aktivitas"
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

            <p
                v-else-if="errorMessage"
                class="mt-4 rounded-lg bg-error/10 px-3 py-2 text-sm text-error"
            >
                {{ errorMessage }}
            </p>

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
                    Coba ubah kata kunci atau filter aktivitas.
                </p>
            </div>

            <ul v-else class="mt-6 space-y-8">
                <li v-for="item in filteredActivities" :key="item.id">
                    <Link
                        :href="
                            kebunParam
                                ? `/rincian-aktivitas?id=${item.id}&kebun=${encodeURIComponent(kebunParam)}`
                                : `/rincian-aktivitas?id=${item.id}`
                        "
                        class="group -m-1 flex gap-5 rounded-lg p-1 transition-colors hover:bg-muted/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        <img
                            :src="thumbnailFor(item)"
                            :alt="`Foto ${item.typeLabel} di ${item.kebunName}`"
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
                                    <MapPin
                                        :size="16"
                                        :stroke-width="1.8"
                                        class="shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        item.kebunName
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
[data-aktivitas-list] {
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

main:has(> [data-aktivitas-list]) {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

main:has(> [data-aktivitas-list])::-webkit-scrollbar {
    display: none;
}
</style>
