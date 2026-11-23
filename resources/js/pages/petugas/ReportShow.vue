<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Activity,
    CalendarDays,
    ClipboardList,
    FileText,
    MapPin,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import PetugasDetailHeader from '@/components/petugas/PetugasDetailHeader.vue';
import ReportPhotoGallery from '@/components/petugas/ReportPhotoGallery.vue';
import {
    extractPetugasBackParam,
    petugasBackLabel,
} from '@/lib/petugasBackLink';
import { formatReportDate, formatReportDetails } from '@/lib/petugasReport';
import type { DailyReport, DailyReportActivity } from '@/types/daily-activity';

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    farm: { id: string; name: string };
    date: string;
    log: DailyReport | null;
    legacyActivity?: DailyReportActivity | null;
}>();
const page = usePage();
const activity = computed(
    () => props.legacyActivity ?? props.log?.activities[0] ?? null,
);
const backHref = computed(
    () => extractPetugasBackParam(page.url) ?? '/petugas/laporan',
);
const backLabel = computed(() =>
    backHref.value === '/petugas/laporan' ||
    backHref.value.startsWith('/petugas/laporan?')
        ? 'Kembali ke daftar laporan'
        : petugasBackLabel(backHref.value, 'Kembali ke daftar laporan'),
);
const location = computed(() => {
    if (!activity.value) return '';
    return (
        [
            activity.value.block,
            activity.value.subblok ? `Subblok ${activity.value.subblok}` : null,
        ]
            .filter(Boolean)
            .join(' · ') || 'Lokasi spesifik tidak tercatat'
    );
});
const indicators = computed(
    () =>
        activity.value?.details.filter((detail) =>
            ['Hama', 'Penyakit'].includes(detail.label),
        ) ?? [],
);
const details = computed(() =>
    formatReportDetails(
        activity.value?.details.filter(
            (detail) => !['Hama', 'Penyakit'].includes(detail.label),
        ) ?? [],
    ),
);
</script>

<template>
    <Head title="Detail Laporan" />
    <div
        class="min-w-0 space-y-[var(--admin-content-gap)] pb-8 text-foreground"
    >
        <PetugasDetailHeader
            title="Detail laporan"
            :back-href="backHref"
            :back-label="backLabel"
        />

        <template v-if="activity">
            <section
                class="petugas-card overflow-hidden"
                aria-label="Informasi aktivitas"
            >
                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-full bg-primary/10 text-primary"
                        >
                            <Activity class="size-5" aria-hidden="true" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2
                                class="text-lg leading-6 font-semibold break-words"
                            >
                                {{ activity.name }}
                            </h2>
                            <span
                                class="mt-1 inline-flex rounded-full bg-primary/10 px-2.5 py-1 text-[11px] leading-4 font-semibold text-primary"
                            >
                                {{ activity.task_id ? 'SOP' : 'Mandiri' }}
                            </span>
                        </div>
                    </div>
                    <p
                        class="mt-2 flex items-start gap-1.5 text-xs leading-5 text-muted-foreground"
                    >
                        <MapPin
                            class="mt-0.5 size-3.5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 break-words">{{ location }}</span>
                    </p>
                </div>
                <dl
                    class="grid grid-cols-2 divide-x divide-border border-t border-border"
                >
                    <div class="min-w-0 p-4">
                        <dt
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <CalendarDays
                                class="size-3.5 shrink-0 text-primary"
                                aria-hidden="true"
                            />Waktu aktivitas
                        </dt>
                        <dd class="mt-1.5 text-sm font-semibold">
                            {{ formatReportDate(date) }}
                        </dd>
                    </div>
                    <div class="min-w-0 p-4">
                        <dt
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <UserRound
                                class="size-3.5 shrink-0 text-primary"
                                aria-hidden="true"
                            />Petugas lapangan
                        </dt>
                        <dd class="mt-1.5 text-sm font-semibold break-words">
                            {{ activity.performer_name ?? 'Tidak tercatat' }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="!log"
                    class="border-t border-border px-4 py-3 text-xs leading-5 text-muted-foreground"
                >
                    Ringkasan logbook harian tidak tersedia untuk aktivitas ini.
                </p>
            </section>

            <section
                aria-labelledby="report-details-heading"
                class="petugas-card p-4"
            >
                <div
                    class="flex items-center gap-2.5 border-b border-border pb-3"
                >
                    <span
                        class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <ClipboardList class="size-5" aria-hidden="true" />
                    </span>
                    <h2
                        id="report-details-heading"
                        class="text-base font-semibold"
                    >
                        Laporan aktivitas
                    </h2>
                </div>
                <dl
                    v-if="details.length || activity.worker_count !== null"
                    class="mt-2 divide-y divide-border"
                >
                    <div
                        v-if="activity.worker_count !== null"
                        class="flex items-start justify-between gap-4 py-3"
                    >
                        <dt class="text-sm text-muted-foreground">
                            Jumlah pekerja
                        </dt>
                        <dd class="text-right text-sm font-semibold">
                            {{ activity.worker_count }} orang
                        </dd>
                    </div>
                    <div
                        v-for="(detail, index) in details"
                        :key="`${detail.label}-${index}`"
                        class="flex items-start justify-between gap-4 py-3"
                    >
                        <dt class="min-w-0 text-sm text-muted-foreground">
                            {{ detail.label }}
                        </dt>
                        <dd
                            class="max-w-[60%] min-w-0 text-right text-sm font-medium break-words whitespace-pre-line"
                        >
                            {{ detail.value }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-else-if="!indicators.length"
                    class="pt-4 text-sm text-muted-foreground"
                >
                    Tidak ada rincian tambahan yang tercatat.
                </p>
                <div
                    v-if="indicators.length"
                    class="mt-3 rounded-xl bg-muted/50 p-3"
                >
                    <h3
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Indikasi gangguan
                    </h3>
                    <dl class="mt-2 grid grid-cols-2 gap-2">
                        <div
                            v-for="(detail, index) in indicators"
                            :key="`${detail.label}-${index}`"
                            class="min-w-0 rounded-lg border border-border bg-card p-2.5"
                        >
                            <dt class="text-xs text-muted-foreground">
                                {{ detail.label }}
                            </dt>
                            <dd class="mt-1 text-sm font-medium break-words">
                                {{ detail.value }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div
                    v-if="activity.notes"
                    class="mt-4 rounded-xl bg-muted/50 p-3"
                >
                    <h3
                        class="flex items-center gap-2 text-xs font-semibold text-muted-foreground"
                    >
                        <FileText
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />Catatan
                    </h3>
                    <p
                        class="mt-2 text-sm leading-6 break-words whitespace-pre-line"
                    >
                        {{ activity.notes }}
                    </p>
                </div>
            </section>

            <ReportPhotoGallery
                :photos="activity.photos"
                :activity-name="activity.name"
            />
        </template>
        <div v-else class="petugas-card petugas-empty px-5 py-9 text-center">
            <ClipboardList
                class="mx-auto size-8 text-muted-foreground"
                aria-hidden="true"
            />
            <h2 class="mt-3 text-base font-semibold">
                Rincian aktivitas tidak tersedia
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Laporan yang dipilih tidak memiliki rincian yang dapat
                ditampilkan.
            </p>
        </div>
    </div>
</template>
