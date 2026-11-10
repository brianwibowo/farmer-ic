<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    FileText,
    MapPin,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import OwnerReadEmpty from '@/components/owner/OwnerReadEmpty.vue';
import {
    ownerFarmOrigin,
    withOwnerFarmOrigin,
} from '@/lib/ownerFarmNavigation';
import type { OwnerFarmRow } from '@/types/owner';
import { ownerArea, ownerDate } from '@/types/owner';

type LegalityStatus =
    | 'active'
    | 'upcoming'
    | 'expired'
    | 'not_applicable'
    | 'incomplete';
const props = defineProps<{
    farm: OwnerFarmRow;
    legality: {
        id: string;
        pks_started_at: string | null;
        pks_ends_at: string | null;
        institution: string | null;
        summary: string | null;
        status: LegalityStatus;
    } | null;
    urls: { farm: string; home: string; farms: string };
}>();
const page = usePage();
const origin = computed(() => ownerFarmOrigin(page.url));
const returnToHome = computed(() => {
    const query = page.url.split('#')[0].split('?')[1] ?? '';
    return (
        origin.value === 'home' &&
        new URLSearchParams(query).get('via') !== 'farm'
    );
});
const backHref = computed(() =>
    returnToHome.value
        ? props.urls.home
        : withOwnerFarmOrigin(props.urls.farm, origin.value),
);
const location = computed(() =>
    [props.farm.district, props.farm.city, props.farm.province]
        .filter(Boolean)
        .join(', '),
);
const tenure: Record<string, string> = {
    owned: 'Milik sendiri',
    rented: 'Sewa',
    partnership: 'Kerja sama',
};
const statuses: Record<LegalityStatus, { label: string; style: string }> = {
    active: { label: 'PKS aktif', style: 'bg-success/10 text-success' },
    upcoming: {
        label: 'PKS belum dimulai',
        style: 'bg-muted text-muted-foreground',
    },
    expired: { label: 'PKS berakhir', style: 'bg-warning/15 text-foreground' },
    not_applicable: {
        label: 'Tanpa PKS',
        style: 'bg-muted text-muted-foreground',
    },
    incomplete: {
        label: 'Periode belum lengkap',
        style: 'bg-warning/15 text-foreground',
    },
};
const status = computed(() =>
    props.legality ? statuses[props.legality.status] : null,
);
</script>

<template>
    <Head :title="`Legalitas · ${farm.name}`" />
    <OwnerPageHeader
        title="Detail legalitas"
        :back-href="backHref"
        :back-label="returnToHome ? 'Kembali ke Beranda' : 'Kembali ke kebun'"
    />
    <section
        class="min-w-0 rounded-xl border border-border bg-card p-4 sm:p-6"
        aria-labelledby="legality-farm"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 flex-1 items-start gap-3">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-secondary dark:text-primary"
                >
                    <ShieldCheck class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-muted-foreground">
                        Site / lahan
                    </p>
                    <h2
                        id="legality-farm"
                        class="mt-1 text-lg font-semibold [overflow-wrap:anywhere] sm:text-xl"
                    >
                        {{ farm.name }}
                    </h2>
                    <p
                        class="mt-2 flex items-start gap-1.5 text-sm leading-5 text-muted-foreground"
                    >
                        <MapPin
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span
                            >{{ location || 'Lokasi belum tercatat' }} ·
                            {{ ownerArea(farm.total_area_ha) }} ha</span
                        >
                    </p>
                </div>
            </div>
            <span
                class="rounded-lg bg-primary/10 px-3 py-1.5 text-xs font-semibold text-secondary dark:text-primary"
            >
                {{
                    tenure[farm.land_tenure ?? ''] ??
                    'Status lahan belum tercatat'
                }}
            </span>
        </div>
        <dl
            v-if="farm.land_tenure_party && farm.land_tenure !== 'owned'"
            class="mt-5 border-t border-border pt-4 text-sm"
        >
            <dt class="text-xs text-muted-foreground">
                {{
                    farm.land_tenure === 'rented'
                        ? 'Pemilik lahan'
                        : 'Mitra kerja sama'
                }}
            </dt>
            <dd class="mt-1 font-medium [overflow-wrap:anywhere]">
                {{ farm.land_tenure_party }}
            </dd>
        </dl>
    </section>

    <template v-if="legality">
        <section
            class="rounded-xl border border-border bg-card p-4 sm:p-6"
            aria-labelledby="pks-heading"
        >
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h2
                    id="pks-heading"
                    class="flex items-center gap-2 text-base font-semibold"
                >
                    <CalendarDays
                        class="size-5 text-primary"
                        aria-hidden="true"
                    />Perjanjian Kerja Sama (PKS)
                </h2>
                <span
                    v-if="status"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                    :class="status.style"
                    >{{ status.label }}</span
                >
            </header>
            <p
                v-if="
                    farm.land_tenure === 'owned' &&
                    !legality.pks_started_at &&
                    !legality.pks_ends_at
                "
                class="mt-4 text-sm leading-6 text-muted-foreground"
            >
                Lahan milik sendiri; periode PKS tidak berlaku.
            </p>
            <dl
                v-else
                class="mt-5 grid grid-cols-1 gap-4 min-[400px]:grid-cols-2 sm:gap-6"
            >
                <div class="rounded-xl bg-muted/50 p-4">
                    <dt class="text-xs text-muted-foreground">
                        Tanggal mulai PKS
                    </dt>
                    <dd class="mt-2 text-base font-semibold sm:text-lg">
                        {{ ownerDate(legality.pks_started_at) }}
                    </dd>
                </div>
                <div class="rounded-xl bg-muted/50 p-4">
                    <dt class="text-xs text-muted-foreground">
                        Tanggal berakhir PKS
                    </dt>
                    <dd class="mt-2 text-base font-semibold sm:text-lg">
                        {{ ownerDate(legality.pks_ends_at) }}
                    </dd>
                </div>
            </dl>
        </section>

        <div
            class="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"
        >
            <section
                class="min-w-0 rounded-xl border border-border bg-card p-4 sm:p-6"
                aria-labelledby="institution-heading"
            >
                <h2
                    id="institution-heading"
                    class="flex items-center gap-2 text-base font-semibold"
                >
                    <Building2
                        class="size-5 shrink-0 text-primary"
                        aria-hidden="true"
                    />Instansi terkait
                </h2>
                <p class="mt-4 text-sm leading-6 [overflow-wrap:anywhere]">
                    {{ legality.institution || 'Belum tercatat' }}
                </p>
            </section>
            <section
                class="min-w-0 rounded-xl border border-border bg-card p-4 sm:p-6"
                aria-labelledby="summary-heading"
            >
                <h2
                    id="summary-heading"
                    class="flex items-center gap-2 text-base font-semibold"
                >
                    <FileText
                        class="size-5 text-primary"
                        aria-hidden="true"
                    />Ringkasan legalitas
                </h2>
                <p
                    class="mt-4 text-sm leading-7 [overflow-wrap:anywhere] whitespace-pre-line"
                >
                    {{
                        legality.summary ||
                        'Ringkasan legalitas belum tercatat.'
                    }}
                </p>
            </section>
        </div>
    </template>
    <OwnerReadEmpty
        v-else
        title="Detail legalitas belum tercatat"
        description="Tanggal PKS, instansi terkait, dan ringkasan belum tersedia untuk lahan ini."
    />
</template>
