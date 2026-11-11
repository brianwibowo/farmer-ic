<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, ClipboardList, MapPin, WalletCards } from '@lucide/vue';
import { computed } from 'vue';
import DashboardFarmMap from '@/components/dashboard/DashboardFarmMap.vue';
import OwnerCycleCard from '@/components/owner/OwnerCycleCard.vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import {
    ownerFarmOrigin,
    withOwnerFarmOrigin,
} from '@/lib/ownerFarmNavigation';
import type { OwnerBlock, OwnerCycle, OwnerFarmRow } from '@/types/owner';
import { ownerArea, ownerDate, ownerStatus } from '@/types/owner';

const props = defineProps<{
    farm: OwnerFarmRow & {
        address: string | null;
        legality_url: string;
        owner_name: string | null;
        team_name: string;
        latitude: string | null;
        longitude: string | null;
        active_area_ha: string;
        active_cycles: number;
        harvest_weight_ton: string;
        capacity: { block_area_ha: string; reserved_area_ha: string };
        default_sop: { id: string; name: string; is_active: boolean } | null;
    };
    blocks: OwnerBlock[];
    legacyCycles: OwnerCycle[];
    observations: Array<{
        id: string;
        observed_at: string;
        growth_phase: string | null;
        health_status: string | null;
        growth_percentage: string | null;
        average_height_cm: string | null;
        plant_condition: string | null;
        disease_indication: string | null;
        pest_indication: string | null;
        notes: string | null;
        cycle: { id: string; label: string; variety: string | null } | null;
    }>;
    weather: {
        condition: string | null;
        temperature_c: string | null;
        humidity_percentage: string | null;
        data_source: string | null;
        recorded_at: string;
    } | null;
    risks: Array<{
        id: string;
        risk_description: string;
        severity: string | null;
        recommendation: string | null;
        status: string | null;
        detected_at: string;
    }>;
    officers: Array<{ id: string; name: string; is_active: boolean }>;
}>();
const location = computed(() =>
    [props.farm.district, props.farm.city, props.farm.province]
        .filter(Boolean)
        .join(', '),
);
const page = usePage();
const origin = computed(() => ownerFarmOrigin(page.url));
const backHref = computed(() => (origin.value === 'home' ? '/' : '/kebun'));
const backLabel = computed(() =>
    origin.value === 'home' ? 'Kembali ke Beranda' : 'Daftar kebun',
);
const mapFarms = computed(() => [
    {
        id: props.farm.id,
        name: props.farm.name,
        location: location.value,
        area: ownerArea(props.farm.total_area_ha),
        latitude:
            props.farm.latitude === null ? null : Number(props.farm.latitude),
        longitude:
            props.farm.longitude === null ? null : Number(props.farm.longitude),
    },
]);
const tenure: Record<string, string> = {
    owned: 'Milik sendiri',
    rented: 'Sewa',
    partnership: 'Kemitraan',
};
const severity: Record<string, string> = {
    low: 'Rendah',
    medium: 'Sedang',
    high: 'Tinggi',
    critical: 'Kritis',
};
</script>

<template>
    <Head :title="farm.name" />
    <OwnerPageHeader
        :title="farm.name"
        :description="location || 'Lokasi belum tercatat'"
        :back-href="backHref"
        :back-label="backLabel"
    />
    <section
        class="rounded-xl border border-border bg-card p-4"
        aria-labelledby="farm-information"
    >
        <h2 id="farm-information" class="text-base font-semibold">
            Informasi kebun
        </h2>
        <dl class="mt-4 space-y-3 text-sm">
            <div>
                <dt class="text-xs text-muted-foreground">Alamat</dt>
                <dd class="mt-1 leading-6">
                    {{ farm.address || 'Belum tercatat' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Penguasaan lahan</dt>
                <dd class="mt-1">
                    <Link
                        :href="
                            withOwnerFarmOrigin(
                                `${farm.legality_url}?via=farm`,
                                origin,
                            )
                        "
                        class="inline-flex min-h-11 items-center gap-1.5 rounded-md font-medium text-secondary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                    >
                        {{
                            farm.land_tenure
                                ? (tenure[farm.land_tenure] ?? farm.land_tenure)
                                : 'Lihat legalitas'
                        }}
                        <ArrowUpRight class="size-4" aria-hidden="true" />
                    </Link>
                </dd>
            </div>
            <div
                v-if="
                    farm.land_tenure === 'rented' ||
                    farm.land_tenure === 'partnership'
                "
            >
                <dt class="text-xs text-muted-foreground">
                    {{
                        farm.land_tenure === 'rented'
                            ? 'Pemilik lahan'
                            : 'Mitra kerja sama'
                    }}
                </dt>
                <dd class="mt-1 select-text [overflow-wrap:anywhere]">
                    {{ farm.land_tenure_party || 'Pihak terkait belum diisi' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Owner terdaftar</dt>
                <dd class="mt-1">{{ farm.owner_name || 'Belum tersedia' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Tenant</dt>
                <dd class="mt-1">{{ farm.team_name }}</dd>
            </div>
        </dl>
    </section>
    <div class="grid grid-cols-2 gap-3">
        <article
            v-for="item in [
                { label: 'Luas terdaftar', value: farm.total_area_ha },
                {
                    label: 'Luas blok aktif',
                    value: farm.capacity.block_area_ha,
                },
                {
                    label: 'Alokasi berjalan',
                    value: farm.capacity.reserved_area_ha,
                },
                { label: 'Realisasi tanam aktif', value: farm.active_area_ha },
            ]"
            :key="item.label"
            class="rounded-xl bg-muted p-4"
        >
            <p class="text-xs leading-5 text-muted-foreground">
                {{ item.label }}
            </p>
            <p class="mt-2 text-xl font-semibold break-words">
                {{ ownerArea(item.value) }}
                <span class="text-xs font-normal">ha</span>
            </p>
        </article>
    </div>
    <section class="rounded-xl border border-border bg-card p-4">
        <h2 class="text-base font-semibold">Panen tercatat</h2>
        <p class="mt-2 text-2xl font-semibold">
            {{ ownerArea(farm.harvest_weight_ton) }}
            <span class="text-sm font-normal">ton</span>
        </p>
        <p class="mt-2 text-xs leading-5 text-muted-foreground">
            Akumulasi berat panen dari aktivitas yang tercatat pada kebun ini.
        </p>
    </section>
    <nav aria-label="Data terkait kebun" class="grid grid-cols-2 gap-3">
        <Link
            v-if="page.props.ownerNavigation?.activities"
            :href="`/aktivitas?farm_id=${farm.id}`"
            class="flex min-h-14 items-center justify-center gap-2 rounded-xl border border-border px-3 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
            ><ClipboardList class="size-4" />Aktivitas</Link
        ><Link
            v-if="page.props.ownerNavigation?.finance"
            :href="`/finance?farm_id=${farm.id}`"
            class="flex min-h-14 items-center justify-center gap-2 rounded-xl border border-border px-3 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
            ><WalletCards class="size-4" />Keuangan</Link
        >
    </nav>
    <section aria-labelledby="farm-blocks">
        <h2 id="farm-blocks" class="text-base font-semibold">Blok & batch</h2>
        <p class="mt-1 text-xs leading-5 text-muted-foreground">
            Buka blok untuk melihat alokasi, timeline, dan jadwal setiap batch.
        </p>
        <p
            v-if="!blocks.length"
            class="mt-3 rounded-xl bg-muted p-4 text-sm text-muted-foreground"
        >
            Belum ada blok tercatat pada kebun ini.
        </p>
        <div class="mt-3 space-y-3">
            <Link
                v-for="block in blocks"
                :key="block.id"
                :href="
                    withOwnerFarmOrigin(
                        `/kebun/${farm.id}/blok/${block.id}`,
                        origin,
                    )
                "
                class="block rounded-xl border border-border p-4 hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                ><div class="flex justify-between gap-3">
                    <h3 class="text-sm font-semibold">
                        {{ block.code }} · {{ block.name }}
                    </h3>
                    <ArrowUpRight
                        class="size-4 shrink-0 text-secondary dark:text-primary"
                    />
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ ownerArea(block.area_ha) }} ha ·
                    {{ block.cycles_count }} batch ·
                    {{ block.is_active ? 'Aktif' : 'Nonaktif' }}
                </p>
                <p v-if="block.description" class="mt-2 text-sm leading-6">
                    {{ block.description }}
                </p></Link
            >
        </div>
    </section>
    <section v-if="legacyCycles.length" aria-labelledby="farm-unallocated">
        <h2 id="farm-unallocated" class="text-base font-semibold">
            Siklus tanpa blok
        </h2>
        <p class="mt-1 text-xs leading-5 text-muted-foreground">
            Catatan siklus yang belum terhubung dengan ronde blok.
        </p>
        <div class="mt-3 space-y-3">
            <OwnerCycleCard
                v-for="cycle in legacyCycles"
                :key="cycle.id"
                :cycle="cycle"
            />
        </div>
    </section>
    <section class="rounded-xl border border-border p-4">
        <h2 class="text-base font-semibold">SOP default kebun</h2>
        <Link
            v-if="farm.default_sop"
            :href="`/lainnya/sop/${farm.default_sop.id}`"
            class="mt-3 flex min-h-11 items-center justify-between gap-2 rounded-xl bg-muted p-3 text-sm font-medium hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
            >{{ farm.default_sop.name }}<ArrowUpRight class="size-4 shrink-0"
        /></Link>
        <p v-else class="mt-3 text-sm text-muted-foreground">
            SOP default belum ditetapkan.
        </p>
        <p class="mt-3 text-xs leading-5 text-muted-foreground">
            Setiap batch mengikuti versi SOP yang tersimpan pada batch tersebut.
        </p>
    </section>
    <section aria-labelledby="farm-observations">
        <h2 id="farm-observations" class="text-base font-semibold">
            Pengamatan tanaman
        </h2>
        <p
            v-if="!observations.length"
            class="mt-3 rounded-xl bg-muted p-4 text-sm text-muted-foreground"
        >
            Belum ada pengamatan tanaman tercatat.
        </p>
        <div class="mt-3 space-y-3">
            <article
                v-for="observation in observations"
                :key="observation.id"
                class="space-y-2 rounded-xl border border-border p-4"
            >
                <p class="text-xs text-muted-foreground">
                    {{ ownerDate(observation.observed_at) }}
                </p>
                <p
                    v-if="observation.cycle"
                    class="text-xs font-medium text-secondary dark:text-primary"
                >
                    {{ observation.cycle.label
                    }}{{
                        observation.cycle.variety
                            ? ` · ${observation.cycle.variety}`
                            : ''
                    }}
                </p>
                <h3 class="text-sm font-semibold">
                    {{ observation.health_status || 'Kondisi belum dicatat' }}
                </h3>
                <p v-if="observation.growth_phase" class="text-sm">
                    Fase {{ observation.growth_phase }}
                </p>
                <p
                    v-if="observation.growth_percentage !== null"
                    class="text-sm"
                >
                    Pertumbuhan tercatat
                    {{ ownerArea(observation.growth_percentage) }}%
                </p>
                <p
                    v-if="observation.average_height_cm !== null"
                    class="text-sm"
                >
                    Tinggi rata-rata
                    {{ ownerArea(observation.average_height_cm) }} cm
                </p>
                <p
                    v-for="(text, index) in [
                        observation.plant_condition,
                        observation.disease_indication,
                        observation.pest_indication,
                        observation.notes,
                    ].filter(Boolean)"
                    :key="index"
                    class="text-sm leading-6 whitespace-pre-line"
                >
                    {{ text }}
                </p>
            </article>
        </div>
    </section>
    <section class="rounded-xl border border-border p-4">
        <h2 class="text-base font-semibold">Cuaca terakhir tercatat</h2>
        <template v-if="weather"
            ><p class="mt-3 text-sm font-medium">
                {{ weather.condition || 'Kondisi belum dicatat' }}
            </p>
            <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-xs text-muted-foreground">Suhu</dt>
                    <dd class="mt-1">
                        {{
                            weather.temperature_c === null
                                ? 'Belum tersedia'
                                : `${ownerArea(weather.temperature_c)} °C`
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Kelembapan</dt>
                    <dd class="mt-1">
                        {{
                            weather.humidity_percentage === null
                                ? 'Belum tersedia'
                                : `${ownerArea(weather.humidity_percentage)}%`
                        }}
                    </dd>
                </div>
            </dl>
            <p class="mt-3 text-xs leading-5 text-muted-foreground">
                {{ ownerDate(weather.recorded_at)
                }}{{ weather.data_source ? ` · ${weather.data_source}` : '' }}
            </p></template
        >
        <p v-else class="mt-3 text-sm text-muted-foreground">
            Belum ada catatan cuaca.
        </p>
    </section>
    <section aria-labelledby="farm-risks">
        <h2 id="farm-risks" class="text-base font-semibold">Catatan risiko</h2>
        <p
            v-if="!risks.length"
            class="mt-3 rounded-xl bg-muted p-4 text-sm text-muted-foreground"
        >
            Belum ada risiko tercatat.
        </p>
        <article
            v-for="risk in risks"
            :key="risk.id"
            class="mt-3 space-y-2 rounded-xl border border-border p-4"
        >
            <p class="text-xs text-muted-foreground">
                {{ ownerDate(risk.detected_at) }} ·
                {{
                    risk.status
                        ? (ownerStatus[risk.status] ?? risk.status)
                        : 'Status belum dicatat'
                }}
            </p>
            <h3 class="text-sm font-semibold">{{ risk.risk_description }}</h3>
            <p v-if="risk.severity" class="text-xs text-muted-foreground">
                Tingkat {{ severity[risk.severity] ?? risk.severity }}
            </p>
            <p v-if="risk.recommendation" class="text-sm leading-6">
                {{ risk.recommendation }}
            </p>
        </article>
    </section>
    <section>
        <h2 class="text-base font-semibold">Petugas kebun</h2>
        <p
            v-if="!officers.length"
            class="mt-3 rounded-xl bg-muted p-4 text-sm text-muted-foreground"
        >
            Belum ada petugas ditugaskan.
        </p>
        <Link
            v-for="officer in officers"
            :key="officer.id"
            :href="`/lainnya/pengguna/${officer.id}`"
            class="mt-3 flex min-h-14 items-center justify-between gap-2 rounded-xl border border-border p-4 text-sm hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
            ><span
                >{{ officer.name }}
                <span class="text-xs text-muted-foreground"
                    >· {{ officer.is_active ? 'Aktif' : 'Nonaktif' }}</span
                ></span
            ><ArrowUpRight class="size-4 shrink-0"
        /></Link>
    </section>
    <section>
        <h2 class="flex items-center gap-2 text-base font-semibold">
            <MapPin class="size-4" />Lokasi
        </h2>
        <DashboardFarmMap :farms="mapFarms" class="mt-3! h-56!" />
    </section>
</template>
