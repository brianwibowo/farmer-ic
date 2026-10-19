<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, SquareActivity } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import ActivityMetadataCard from '@/components/farmer/ActivityMetadataCard.vue';
import ActivityPhotoGallery from '@/components/farmer/ActivityPhotoGallery.vue';
import ActivityReportSection from '@/components/farmer/ActivityReportSection.vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import { clearDemoStorage } from '@/composables/useDemoStorage';
import type { ActivityLog } from '@/data/activityLogs';
import { listActivityLogs } from '@/services/activityLogService';

const activeLog = ref<ActivityLog | undefined>(undefined);
const isLoading = ref(true);
const errorMessage = ref('');
const statusMessage = ref('');
const backHref = ref('/aktivitas');
const shellActiveKey = ref<'activity' | 'garden'>('activity');

async function loadActivityDetail(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const data = await listActivityLogs();
        const params = new URLSearchParams(window.location.search);
        const idParam = params.get('id');
        const typeParam = params.get('type');
        const kebunParam = params.get('kebun');
        const fromParam = params.get('from');

        if (fromParam === 'kebun') {
            shellActiveKey.value = 'garden';
            backHref.value = kebunParam
                ? `/kebun/aktivitas?kebun=${encodeURIComponent(kebunParam)}`
                : '/kebun/aktivitas';
        } else {
            shellActiveKey.value = kebunParam ? 'garden' : 'activity';
            backHref.value = kebunParam
                ? `/aktivitas?kebun=${encodeURIComponent(kebunParam)}`
                : '/aktivitas';
        }

        if (idParam) {
            activeLog.value = data.find((item) => item.id === idParam);
        } else if (typeParam) {
            activeLog.value = data.find((item) => item.type === typeParam);
        } else if (kebunParam) {
            activeLog.value = data.find((item) => item.kebunId === kebunParam);
        } else {
            activeLog.value = data[0];
        }
    } catch {
        errorMessage.value =
            'Data rincian aktivitas belum dapat dimuat. Coba lagi.';
    } finally {
        isLoading.value = false;
    }
}

async function resetDemoData(): Promise<void> {
    clearDemoStorage();
    statusMessage.value = 'Data demo rincian aktivitas telah direset.';
    await loadActivityDetail();
}

onMounted(() => void loadActivityDetail());
</script>

<template>
    <Head title="Rincian Aktivitas" />

    <FarmerMobileShell :active-key="shellActiveKey">
        <div data-rincian-aktivitas class="px-4 pt-16 pb-6">
            <!-- Top Header -->
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2">
                    <Link
                        :href="backHref"
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-foreground transition-colors hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        aria-label="Kembali"
                    >
                        <ArrowLeft :size="18" :stroke-width="1.8" />
                    </Link>
                    <h1
                        class="truncate text-[20px] leading-7 font-semibold text-foreground"
                    >
                        Rincian Aktivitas
                    </h1>
                </div>

                <button
                    type="button"
                    class="shrink-0 text-xs font-medium text-success underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="void resetDemoData()"
                >
                    Reset demo
                </button>
            </div>

            <!-- Reset / Status Feedback Banner -->
            <div
                v-if="statusMessage"
                class="mt-4 flex items-center justify-between gap-3 rounded-lg bg-muted px-3 py-2 text-sm text-success"
                role="status"
            >
                <span>{{ statusMessage }}</span>
                <button
                    type="button"
                    class="shrink-0 rounded px-1 text-lg leading-none text-success/80 hover:text-success focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    aria-label="Tutup notifikasi"
                    @click="statusMessage = ''"
                >
                    &times;
                </button>
            </div>

            <!-- Loading Skeleton -->
            <div
                v-if="isLoading"
                class="mt-5 space-y-4"
                aria-live="polite"
                aria-label="Memuat rincian aktivitas"
            >
                <div class="h-32 animate-pulse rounded-xl bg-muted" />
                <div class="h-48 animate-pulse rounded-xl bg-muted" />
                <div class="grid grid-cols-2 gap-3">
                    <div class="h-36 animate-pulse rounded-xl bg-muted" />
                    <div class="h-36 animate-pulse rounded-xl bg-muted" />
                </div>
            </div>

            <!-- Error State -->
            <p
                v-else-if="errorMessage"
                class="mt-5 rounded-lg bg-error/10 px-3 py-2 text-sm text-error"
            >
                {{ errorMessage }}
            </p>

            <!-- Empty / Not Found State -->
            <div
                v-else-if="!activeLog"
                class="mt-10 rounded-xl bg-muted px-5 py-8 text-center"
            >
                <SquareActivity
                    :size="34"
                    class="mx-auto text-primary"
                    aria-hidden="true"
                />
                <h2 class="mt-3 text-base font-semibold text-foreground">
                    Rincian aktivitas tidak ditemukan
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Laporan aktivitas yang dipilih tidak tersedia.
                </p>
                <Link
                    :href="backHref"
                    class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    Kembali
                </Link>
            </div>

            <!-- Main Activity Detail Content -->
            <div v-else class="mt-5 space-y-4">
                <!-- 1 & 2. Timestamp, HST & Nama Petugas -->
                <ActivityMetadataCard :activity="activeLog" />

                <!-- 3. Laporan -->
                <ActivityReportSection :report="activeLog.report" />

                <!-- 4. Foto Dokumentasi -->
                <ActivityPhotoGallery :photos="activeLog.photos" />
            </div>
        </div>
    </FarmerMobileShell>
</template>

<style>
[data-rincian-aktivitas] {
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

main:has(> [data-rincian-aktivitas]) {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

main:has(> [data-rincian-aktivitas])::-webkit-scrollbar {
    display: none;
}
</style>
