<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ClipboardCheck,
    ClipboardList,
    NotebookPen,
    Search,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import ActivityAddButton from '@/components/petugas/ActivityAddButton.vue';
import ReportCard from '@/components/petugas/ReportCard.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import type { PetugasActivityCategory } from '@/types/petugas-activity';
import type { PetugasReportSummary } from '@/types/petugas-report';

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    farm: { id: string; name: string } | null;
    reports: {
        data: PetugasReportSummary[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    } | null;
    stats: { sop: number; mandiri: number };
    filters: {
        q?: string;
        type?: string;
        date?: string;
        source?: string;
        mine?: boolean;
    };
    types: { code: string; name: string }[];
    categories: PetugasActivityCategory[];
}>();
const page = usePage();
const filter = reactive({
    q: props.filters.q ?? '',
    type: props.filters.type ?? '',
    date: props.filters.date ?? '',
    source: props.filters.source ?? '',
    mine: !!props.filters.mine,
});
const loading = ref(false);
const emptyDescription = computed(() => {
    if (!props.farm) return 'Hubungi Admin untuk penugasan kebun.';
    return props.categories.length
        ? 'Coba ubah filter atau ketuk tombol + untuk mencatat aktivitas mandiri.'
        : 'Coba ubah filter untuk melihat laporan lain.';
});
const listUrl = computed(() =>
    /^\/petugas\/laporan(?:\?|$)/.test(page.url)
        ? page.url
        : '/petugas/laporan',
);
const reportGroups = computed(() => {
    const groups = new Map<string, PetugasReportSummary[]>();

    for (const report of props.reports?.data ?? []) {
        const reports = groups.get(report.date) ?? [];
        reports.push(report);
        groups.set(report.date, reports);
    }

    return Array.from(groups, ([date, reports]) => ({ date, reports })).sort(
        (a, b) => b.date.localeCompare(a.date),
    );
});
function detailHref(id: string): string {
    return `/petugas/laporan/${id}?${new URLSearchParams({ back: listUrl.value })}`;
}
function apply(): void {
    router.get(
        '/petugas/laporan',
        { ...filter, mine: filter.mine ? 1 : 0 },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}
function reset(): void {
    Object.assign(filter, {
        q: '',
        type: '',
        date: '',
        source: '',
        mine: false,
    });
    apply();
}
</script>

<template>
    <Head title="Laporan" />
    <h1 class="sr-only">Laporan</h1>
    <div class="min-w-0 space-y-4 pb-28 text-foreground" :aria-busy="loading">
        <section
            class="grid grid-cols-3 gap-2"
            aria-label="Ringkasan laporan sesuai filter"
        >
            <div
                class="petugas-card flex min-w-0 flex-col items-center gap-1 px-2 py-3 text-center"
            >
                <span
                    class="inline-flex size-8 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    ><Activity class="size-4" aria-hidden="true"
                /></span>
                <p class="text-lg font-semibold">{{ reports?.total ?? 0 }}</p>
                <p class="text-xs text-muted-foreground">Aktivitas</p>
            </div>
            <div
                class="petugas-card flex min-w-0 flex-col items-center gap-1 px-2 py-3 text-center"
            >
                <span
                    class="inline-flex size-8 items-center justify-center rounded-xl bg-info/10 text-info"
                    ><ClipboardCheck class="size-4" aria-hidden="true"
                /></span>
                <p class="text-lg font-semibold">{{ stats.sop }}</p>
                <p class="text-xs text-muted-foreground">SOP</p>
            </div>
            <div
                class="petugas-card flex min-w-0 flex-col items-center gap-1 px-2 py-3 text-center"
            >
                <span
                    class="inline-flex size-8 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    ><NotebookPen class="size-4" aria-hidden="true"
                /></span>
                <p class="text-lg font-semibold">{{ stats.mandiri }}</p>
                <p class="text-xs text-muted-foreground">Mandiri</p>
            </div>
        </section>

        <form v-if="farm" class="space-y-3" @submit.prevent="apply">
            <label class="relative block">
                <span class="sr-only">Cari laporan</span>
                <input
                    v-model="filter.q"
                    type="search"
                    maxlength="100"
                    placeholder="Cari aktivitas atau subblok"
                    class="petugas-field min-h-11 w-full rounded-xl border border-border px-3 pr-10 text-foreground"
                />
                <Search
                    class="pointer-events-none absolute top-1/2 right-3 size-5 -translate-y-1/2 text-primary"
                    aria-hidden="true"
                />
            </label>
            <div class="grid grid-cols-2 gap-2">
                <label class="min-w-0 space-y-1 text-xs">
                    <span>Tanggal kerja</span>
                    <input
                        v-model="filter.date"
                        type="date"
                        class="petugas-field min-h-11 w-full min-w-0 rounded-xl border border-border px-2 text-foreground [color-scheme:light] dark:[color-scheme:dark]"
                    />
                </label>
                <div class="min-w-0 space-y-1 text-xs">
                    <label for="report-type">Kategori</label>
                    <AppSelect
                        id="report-type"
                        v-model="filter.type"
                        aria-label="Kategori laporan"
                        class="min-h-11"
                    >
                        <AppSelectOption value=""
                            >Semua kategori</AppSelectOption
                        >
                        <AppSelectOption
                            v-for="type in types"
                            :key="type.code"
                            :value="type.code"
                            >{{ type.name }}</AppSelectOption
                        >
                    </AppSelect>
                </div>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-36 flex-1 space-y-1 text-xs">
                    <label for="report-source">Sumber laporan</label>
                    <AppSelect
                        id="report-source"
                        v-model="filter.source"
                        aria-label="Asal aktivitas"
                        class="min-h-11"
                    >
                        <AppSelectOption value="">Semua sumber</AppSelectOption>
                        <AppSelectOption value="sop">Dari SOP</AppSelectOption>
                        <AppSelectOption value="mandiri"
                            >Mandiri</AppSelectOption
                        >
                    </AppSelect>
                </div>
                <label class="flex min-h-11 items-center gap-2 text-xs">
                    <input
                        v-model="filter.mine"
                        type="checkbox"
                        class="size-4 accent-primary"
                    />Input saya
                </label>
            </div>
            <div class="flex gap-2">
                <button
                    type="submit"
                    :disabled="loading"
                    class="min-h-11 flex-1 rounded-xl bg-primary px-3 text-sm font-semibold text-primary-foreground hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50"
                >
                    {{ loading ? 'Memuat…' : 'Terapkan filter' }}
                </button>
                <button
                    type="button"
                    :disabled="loading"
                    class="min-h-11 rounded-xl border border-border bg-card px-4 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50"
                    @click="reset"
                >
                    Reset
                </button>
            </div>
        </form>

        <p v-if="loading" role="status" class="text-sm text-muted-foreground">
            Memuat laporan…
        </p>
        <div
            v-if="!reports?.data.length && !loading"
            class="petugas-card petugas-empty bg-muted/40 px-5 py-10 text-center"
        >
            <ClipboardList
                class="mx-auto size-9 text-primary"
                aria-hidden="true"
            />
            <h2 class="mt-3 font-semibold">
                {{
                    farm
                        ? 'Belum ada laporan yang ditampilkan'
                        : 'Belum ada penugasan kebun'
                }}
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ emptyDescription }}
            </p>
        </div>
        <div
            v-else-if="reportGroups.length"
            class="space-y-5"
            role="group"
            aria-label="Riwayat aktivitas"
        >
            <section
                v-for="group in reportGroups"
                :key="group.date"
                :aria-labelledby="`report-date-${group.date}`"
                class="space-y-2"
            >
                <h2
                    :id="`report-date-${group.date}`"
                    class="text-xs font-semibold text-muted-foreground"
                >
                    <time :datetime="group.date">{{ group.date }}</time>
                </h2>
                <ul class="space-y-3">
                    <li v-for="report in group.reports" :key="report.id">
                        <ReportCard
                            :report="report"
                            :href="detailHref(report.id)"
                        />
                    </li>
                </ul>
            </section>
        </div>
        <nav
            v-if="reports && reports.last_page > 1"
            class="flex items-center justify-between gap-2 text-sm"
            aria-label="Halaman laporan"
        >
            <Link
                v-if="reports.prev_page_url"
                :href="reports.prev_page_url"
                class="inline-flex min-h-11 items-center rounded-xl px-2 text-primary focus-visible:outline-2 focus-visible:outline-ring"
                >← Sebelumnya</Link
            >
            <span v-else class="min-h-11" aria-hidden="true" />
            <span
                >Halaman {{ reports.current_page }} /
                {{ reports.last_page }}</span
            >
            <Link
                v-if="reports.next_page_url"
                :href="reports.next_page_url"
                class="inline-flex min-h-11 items-center rounded-xl px-2 text-primary focus-visible:outline-2 focus-visible:outline-ring"
                >Berikutnya →</Link
            >
            <span v-else class="min-h-11" aria-hidden="true" />
        </nav>
        <ActivityAddButton :categories="categories" />
    </div>
</template>
