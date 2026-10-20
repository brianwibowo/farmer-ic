<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    // CheckCircle2, — NOTE (merge resolution 2026-10-19): kept for the commented-out status banner below.
    // Clock3, — NOTE (merge resolution 2026-10-19): kept for the commented-out status banner below.
    MapPin,
    RotateCcw,
    // Share2, — NOTE (merge resolution 2026-10-19): kept for the commented-out share button below.
    SquareActivity,
    X,
} from '@lucide/vue';
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
const copiedMessage = ref('');

function isIncident(log: ActivityLog): boolean {
    return log.report.kind === 'pelaporan-insiden';
}

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

// NOTE (merge resolution 2026-10-19): the share flow is parked — its button is
// commented out in the header below. Kept commented per review; delete once confirmed unused.
// async function shareActivity(): Promise<void> {
//     if (!activeLog.value) return;
//     const shareText = `${activeLog.value.typeLabel} · ${activeLog.value.kebunName}`;
//     try {
//         if (navigator.share) {
//             await navigator.share({
//                 title: 'Rincian aktivitas',
//                 text: shareText,
//             });
//         } else if (navigator.clipboard) {
//             await navigator.clipboard.writeText(window.location.href);
//             copiedMessage.value = 'Tautan rincian disalin.';
//         } else {
//             copiedMessage.value = 'Tautan rincian siap dibagikan.';
//         }
//     } catch {
//         // The share sheet can be dismissed without an error state.
//     }
// }

onMounted(() => void loadActivityDetail());
</script>

<template>
    <Head title="Rincian Aktivitas" />

    <FarmerMobileShell :active-key="shellActiveKey">
        <div data-rincian-aktivitas class="px-4 pt-4 pb-6">
            <header class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2.5">
                    <Link
                        :href="backHref"
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-foreground transition-colors hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        aria-label="Kembali ke daftar aktivitas"
                    >
                        <ArrowLeft
                            :size="19"
                            :stroke-width="1.8"
                            aria-hidden="true"
                        />
                    </Link>
                    <div class="min-w-0">
                        <!-- <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Catatan lapangan
                        </p> -->
                        <h1
                            class="truncate text-[22px] leading-7 font-semibold text-foreground"
                        >
                            Rincian aktivitas
                        </h1>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <!-- <button
                        type="button"
                        class="inline-flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                        aria-label="Bagikan rincian aktivitas"
                        :disabled="!activeLog"
                        @click="void shareActivity()"
                    >
                        <Share2 :size="17" aria-hidden="true" />
                    </button> -->
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-success hover:text-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        @click="void resetDemoData()"
                    >
                        <RotateCcw :size="13" aria-hidden="true" />
                        Reset
                    </button>
                </div>
            </header>

            <!-- NOTE (merge resolution 2026-10-19): duplicate reset button — the header
                 'Reset' button is the active one. Kept commented per review.
            <button
                type="button"
                class="shrink-0 text-xs font-medium text-success underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="void resetDemoData()"
            >
                Reset demo
            </button>
            -->

            <div
                v-if="statusMessage || copiedMessage"
                class="mt-4 flex items-center justify-between gap-3 rounded-xl border border-success/25 bg-success/10 px-3 py-2.5 text-xs text-success"
                role="status"
            >
                <span>{{ statusMessage || copiedMessage }}</span>
                <button
                    type="button"
                    class="rounded p-1 hover:bg-success/10 focus-visible:outline-2 focus-visible:outline-ring"
                    aria-label="Tutup notifikasi"
                    @click="
                        statusMessage = '';
                        copiedMessage = '';
                    "
                >
                    <X :size="15" aria-hidden="true" />
                </button>
            </div>

            <div
                v-if="isLoading"
                class="mt-5 space-y-4"
                aria-live="polite"
                aria-label="Memuat rincian aktivitas"
            >
                <div class="h-52 animate-pulse rounded-2xl bg-muted" />
                <div class="h-56 animate-pulse rounded-2xl bg-muted" />
                <div class="h-64 animate-pulse rounded-2xl bg-muted" />
            </div>

            <div
                v-else-if="errorMessage"
                class="mt-5 rounded-2xl border border-error/25 bg-error/10 px-4 py-4 text-sm text-error"
                role="alert"
            >
                {{ errorMessage }}
                <button
                    type="button"
                    class="mt-3 block rounded-lg bg-error px-3 py-2 text-xs font-semibold text-error-foreground"
                    @click="void loadActivityDetail()"
                >
                    Coba lagi
                </button>
            </div>

            <div
                v-else-if="!activeLog"
                class="mt-10 rounded-2xl border border-dashed border-border bg-muted/50 px-5 py-10 text-center"
            >
                <SquareActivity
                    :size="36"
                    class="mx-auto text-primary"
                    aria-hidden="true"
                />
                <h2 class="mt-3 text-base font-semibold text-foreground">
                    Rincian aktivitas tidak ditemukan
                </h2>
                <p class="mt-1 text-sm leading-5 text-muted-foreground">
                    Laporan aktivitas yang dipilih tidak tersedia.
                </p>
                <Link
                    :href="backHref"
                    class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >Kembali ke aktivitas</Link
                >
            </div>

            <div v-else class="mt-5 space-y-4">
                <!-- <div
                    class="flex items-center justify-between gap-3 rounded-xl border border-primary/20 bg-surface/35 px-3.5 py-3"
                >
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span
                            class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-success/15 text-success"
                        >
                            <CheckCircle2
                                v-if="!isIncident(activeLog)"
                                :size="17"
                                aria-hidden="true"
                            />
                            <Clock3 v-else :size="17" aria-hidden="true" />
                        </span>
                        <p class="truncate text-xs font-medium text-secondary">
                            {{
                                isIncident(activeLog)
                                    ? 'Perlu tindak lanjut lapangan'
                                    : 'Aktivitas tercatat'
                            }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 text-[11px] font-semibold text-muted-foreground"
                        >{{ activeLog.photos.length }} foto</span
                    >
                </div> -->

                <ActivityMetadataCard :activity="activeLog" />
                <ActivityReportSection :report="activeLog.report" />
                <ActivityPhotoGallery :photos="activeLog.photos" />

                <Link
                    :href="backHref"
                    class="flex h-11 items-center justify-center rounded-xl border border-border bg-card text-sm font-semibold text-foreground hover:border-primary hover:text-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    Kembali ke daftar aktivitas
                </Link>
            </div>

            <div class="sr-only">
                <span
                    ><MapPin aria-hidden="true" /> Activity details for
                    {{ activeLog?.kebunName }}</span
                >
            </div>
        </div>
    </FarmerMobileShell>
</template>

<style>

main:has(> [data-rincian-aktivitas]) {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

main:has(> [data-rincian-aktivitas])::-webkit-scrollbar {
    display: none;
}
</style>
