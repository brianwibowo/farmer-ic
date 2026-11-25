<script setup lang="ts">
import PageBackButton from '@/components/PageBackButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronRight,
    Clock,
    Plus,
    Search,
    ShieldAlert,
    Siren,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import {
    petugasBackLabel,
    resolvePetugasBackHref,
    withPetugasBack,
} from '@/lib/petugasBackLink';
import {
    formatIncidentDate,
    incidentSeverityClasses,
    incidentStatusClasses,
    incidentViewOptions,
    type IncidentTicketRow,
    type IncidentTicketSummary,
    type IncidentView,
} from '@/types/incidents';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    farm: { id: string; name: string } | null;
    tickets: IncidentTicketRow[];
    summary: IncidentTicketSummary;
    filters: { search?: string; severity?: string; view?: string };
}>();

const page = usePage();
const backHref = computed(() => resolvePetugasBackHref(page.url, '/petugas'));
const backLabel = computed(() =>
    petugasBackLabel(backHref.value, 'Kembali ke beranda'),
);
const listUrl = computed(() =>
    /^\/petugas\/incidents(?:\?|$)/.test(page.url)
        ? page.url
        : '/petugas/incidents',
);

const filter = reactive({
    search: props.filters.search ?? '',
    severity: props.filters.severity ?? '',
    view: (props.filters.view || 'unresolved') as IncidentView,
});

watch(
    () => props.filters,
    (next) => {
        filter.search = next.search ?? '';
        filter.severity = next.severity ?? '';
        filter.view = (next.view || 'unresolved') as IncidentView;
    },
);

function apply(): void {
    const query: Record<string, string> = {};
    if (filter.search.trim()) query.search = filter.search.trim();
    if (filter.severity) query.severity = filter.severity;
    if (filter.view && filter.view !== 'unresolved') query.view = filter.view;

    router.get('/petugas/incidents', query, {
        preserveState: true,
        preserveScroll: true,
    });
}

function reset(): void {
    filter.search = '';
    filter.severity = '';
    filter.view = 'unresolved';
    apply();
}
</script>

<template>
    <Head title="Tiket Insiden" />
    <div class="min-w-0 space-y-4 pb-20 text-foreground">
        <header class="space-y-2">
            <div class="flex min-w-0 items-start gap-2">
                <PageBackButton :href="backHref" :label="backLabel" />
                <div
                    class="min-w-0 flex-1 flex items-start justify-between gap-3"
                >
                    <div class="min-w-0">
                        <h1>Tiket insiden</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                farm
                                    ? `Laporan & tindak lanjut insiden · ${farm.name}`
                                    : 'Belum ada penugasan lahan'
                            }}
                        </p>
                    </div>
                    <Link
                        v-if="farm"
                        :href="
                            withPetugasBack(
                                '/petugas/aktivitas/mandiri/incident',
                                listUrl,
                            )
                        "
                        class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-xl bg-primary px-3 text-xs font-semibold text-primary-foreground hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        Laporkan
                    </Link>
                </div>
            </div>
        </header>

        <section
            class="grid grid-cols-3 gap-2"
            aria-label="Ringkasan tiket insiden"
        >
            <div
                class="petugas-card flex min-w-0 flex-col items-center gap-1 px-2 py-3 text-center"
            >
                <span
                    class="inline-flex size-8 items-center justify-center rounded-xl bg-warning/15 text-warning"
                >
                    <ShieldAlert class="size-4" aria-hidden="true" />
                </span>
                <p class="text-lg font-semibold">{{ summary.open }}</p>
                <p class="text-xs text-muted-foreground">Perlu respon</p>
            </div>
            <div
                class="petugas-card flex min-w-0 flex-col items-center gap-1 px-2 py-3 text-center"
            >
                <span
                    class="inline-flex size-8 items-center justify-center rounded-xl bg-info/10 text-info"
                >
                    <Clock class="size-4" aria-hidden="true" />
                </span>
                <p class="text-lg font-semibold">{{ summary.in_progress }}</p>
                <p class="text-xs text-muted-foreground">Ditangani</p>
            </div>
            <div
                class="petugas-card flex min-w-0 flex-col items-center gap-1 px-2 py-3 text-center"
            >
                <span
                    class="inline-flex size-8 items-center justify-center rounded-xl bg-error/10 text-error"
                >
                    <Siren class="size-4" aria-hidden="true" />
                </span>
                <p class="text-lg font-semibold">
                    {{ summary.emergency_open }}
                </p>
                <p class="text-xs text-muted-foreground">Darurat</p>
            </div>
        </section>

        <section class="space-y-2" aria-label="Filter tiket insiden">
            <form class="relative block" @submit.prevent="apply">
                <span class="sr-only">Cari tiket insiden</span>
                <input
                    v-model="filter.search"
                    type="search"
                    placeholder="Cari kategori atau deskripsi insiden"
                    class="petugas-field min-h-11 w-full rounded-xl border border-border px-3 pr-10 text-foreground"
                />
                <button
                    type="submit"
                    aria-label="Terapkan pencarian"
                    class="absolute top-1/2 right-2 inline-flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-primary"
                >
                    <Search class="size-5" aria-hidden="true" />
                </button>
            </form>
            <div class="grid grid-cols-2 gap-2">
                <AppSelect
                    v-model="filter.view"
                    aria-label="Status tiket"
                    class="min-h-11"
                    @change="apply"
                >
                    <AppSelectOption
                        v-for="[key, label] in incidentViewOptions"
                        :key="key"
                        :value="key"
                    >
                        {{ label }}
                    </AppSelectOption>
                </AppSelect>
                <AppSelect
                    v-model="filter.severity"
                    aria-label="Tingkat kedaruratan"
                    class="min-h-11"
                    @change="apply"
                >
                    <AppSelectOption value="">Semua tingkat</AppSelectOption>
                    <AppSelectOption value="emergency">Darurat</AppSelectOption>
                    <AppSelectOption value="medium">Sedang</AppSelectOption>
                    <AppSelectOption value="light">Ringan</AppSelectOption>
                </AppSelect>
            </div>
        </section>

        <div class="flex items-center justify-between gap-3">
            <p class="text-xs text-muted-foreground">
                {{ tickets.length }} tiket ditampilkan
            </p>
            <button
                v-if="
                    filter.search ||
                    filter.severity ||
                    filter.view !== 'unresolved'
                "
                type="button"
                class="min-h-11 rounded-xl px-2 text-xs font-semibold text-primary underline"
                @click="reset"
            >
                Reset filter
            </button>
        </div>

        <div
            v-if="!tickets.length"
            class="petugas-card petugas-empty bg-muted/40 px-5 py-10 text-center"
        >
            <CheckCircle2 class="mx-auto size-9 text-primary" />
            <h2 class="mt-3 font-semibold">
                {{
                    farm
                        ? 'Tidak ada tiket insiden pada pilihan ini'
                        : 'Belum ada penugasan lahan'
                }}
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                {{
                    farm
                        ? 'Gunakan filter lain atau laporkan insiden baru bila terjadi kendala di lapangan.'
                        : 'Hubungi Admin untuk mendapatkan penugasan kebun.'
                }}
            </p>
        </div>

        <ul v-else class="space-y-3" aria-label="Daftar tiket insiden">
            <li v-for="ticket in tickets" :key="ticket.id">
                <Link
                    :href="
                        withPetugasBack(
                            `/petugas/incidents/${ticket.id}`,
                            listUrl,
                        )
                    "
                    class="petugas-card block space-y-2.5 p-4 transition-colors hover:border-primary/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                :class="
                                    incidentSeverityClasses[ticket.severity]
                                "
                            >
                                {{ ticket.severity_label }}
                            </span>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                :class="incidentStatusClasses[ticket.status]"
                            >
                                {{ ticket.status_label }}
                            </span>
                        </div>
                        <ChevronRight
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </div>

                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-foreground">
                            {{ ticket.incident_category || 'Insiden lahan' }}
                        </p>
                        <p
                            v-if="ticket.description"
                            class="line-clamp-2 text-xs leading-5 text-muted-foreground"
                        >
                            {{ ticket.description }}
                        </p>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        {{
                            [ticket.block_name, ticket.subblok]
                                .filter(Boolean)
                                .join(' · ') || 'Area kebun'
                        }}
                        ·
                        {{
                            formatIncidentDate(
                                ticket.work_date || ticket.reported_at,
                            )
                        }}
                    </p>

                    <p
                        v-if="ticket.admin_note"
                        class="rounded-xl bg-info/10 px-3 py-2 text-xs leading-5 text-foreground"
                    >
                        <span class="font-semibold">Arahan Admin:</span>
                        {{ ticket.admin_note }}
                    </p>
                </Link>
            </li>
        </ul>
    </div>
</template>
