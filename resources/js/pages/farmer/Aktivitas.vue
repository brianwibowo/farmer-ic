<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronDown,
    Clock,
    MapPin,
    Search,
    SquareActivity,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import { clearDemoStorage } from '@/composables/useDemoStorage';
import {
    activityTypeOptions,
    type ActivityLog,
    type ActivityType,
} from '@/data/activityLogs';
import { listActivityLogs } from '@/services/activityLogService';

const activities = ref<ActivityLog[]>([]);
const searchQuery = ref('');
const selectedType = ref<'' | 'all' | ActivityType>('');
const isFilterOpen = ref(false);
const filterDropdownRef = ref<HTMLElement | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');
const resetMessage = ref('');

const selectedTypeLabel = computed(() => {
    if (selectedType.value === '' || selectedType.value === 'all') {
        return 'Aktivitas';
    }

    return (
        activityTypeOptions.find((opt) => opt.value === selectedType.value)
            ?.shortLabel ?? 'Aktivitas'
    );
});

function selectTypeOption(value: 'all' | ActivityType): void {
    selectedType.value = value;
    isFilterOpen.value = false;
}

function handleDocumentClick(event: MouseEvent): void {
    if (!isFilterOpen.value) return;
    const target = event.target as Node | null;
    if (
        filterDropdownRef.value &&
        target &&
        !filterDropdownRef.value.contains(target)
    ) {
        isFilterOpen.value = false;
    }
}

const filteredActivities = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase();

    return activities.value.filter((item) => {
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

        return matchesSearch && matchesType;
    });
});

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

async function resetDemoData(): Promise<void> {
    clearDemoStorage();
    searchQuery.value = '';
    selectedType.value = '';
    isFilterOpen.value = false;
    resetMessage.value = 'Data demo aktivitas telah direset.';
    await loadActivities();
}

onMounted(() => {
    document.addEventListener('pointerdown', handleDocumentClick);
    void loadActivities();
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleDocumentClick);
});
</script>

<template>
    <Head title="Aktivitas" />

    <FarmerMobileShell active-key="activity">
        <div data-aktivitas-list class="px-4 pt-16 pb-5">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-[20px] leading-7 font-semibold text-foreground">
                    Aktivitas
                </h1>
                <button
                    type="button"
                    class="text-xs font-medium text-success underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="void resetDemoData()"
                >
                    Reset demo
                </button>
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
                        :href="`/rincian-aktivitas?id=${item.id}`"
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
