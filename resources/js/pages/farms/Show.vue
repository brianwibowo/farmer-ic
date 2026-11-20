<script setup lang="ts">
import { useFarmManagementNavigation } from '@/composables/useFarmManagementNavigation';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { ownerFarmOrigin } from '@/lib/ownerFarmNavigation';
import { ArrowLeft, LandPlot, MapPin, Target, Sprout } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import FarmBlocksTable from '@/components/blocks/FarmBlocksTable.vue';
import FarmCycleSummary from '@/components/blocks/FarmCycleSummary.vue';
import FarmLocationMap from '@/components/farms/FarmLocationMap.vue';
import {
    hectares,
    landTenureLabel,
    type ManagedFarm,
    type FarmPermissions,
} from '@/types/farms';
import type { ManagedBlock, BlockFormCapacity } from '@/types/blocks';
defineOptions({ inheritAttrs: false });
const { indexUrl } = useFarmManagementNavigation();
const page = usePage();
const fromMonitoring = computed(
    () =>
        !!page.props.navigation?.owner && ownerFarmOrigin(page.url) === 'home',
);
const backUrl = computed(() =>
    fromMonitoring.value ? '/monitoring-lahan' : indexUrl.value,
);
const backLabel = computed(() =>
    fromMonitoring.value ? 'Kembali ke Monitoring Lahan' : 'Daftar lahan',
);
const props = defineProps<{
    farm: ManagedFarm;
    blocks: ManagedBlock[];
    batchPermissions: Record<string, boolean>;
    harvestPermissions: Record<string, boolean>;
    blockCapacity: BlockFormCapacity | null;
    deletionBlockers: string[];
    legalityUrl: string | null;
    ownerSummary?: {
        contract: {
            starts_at: string | null;
            ends_at: string | null;
            not_applicable: boolean;
        } | null;
    };
    can: FarmPermissions & {
        dailyLogs: boolean;
        blocks: boolean;
        createBlock: boolean;
    };
}>();
const contractLabel = computed(() => {
    const contract = props.ownerSummary?.contract;
    if (!contract) return 'Tidak tersedia';
    if (contract.not_applicable) return 'Tidak berlaku';
    if (!contract.starts_at && !contract.ends_at) return 'Belum diisi';

    const formatter = new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
        timeZone: 'Asia/Jakarta',
    });
    const dateLabel = (value: string | null) =>
        value ? formatter.format(new Date(value)) : 'Belum diisi';

    return `${dateLabel(contract.starts_at)} – ${dateLabel(contract.ends_at)}`;
});
let growthRefreshTimer: ReturnType<typeof setInterval> | undefined;
let refreshingGrowth = false;
function refreshGrowthDate(): void {
    if (document.hidden || refreshingGrowth) return;
    const today = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
    if (
        !props.blocks.some((block) =>
            block.batches.some((batch) => {
                const asOf = batch.age?.as_of ?? batch.growth?.as_of;
                return asOf !== undefined && asOf !== today;
            }),
        )
    )
        return;
    refreshingGrowth = true;
    router.reload({
        only: ['blocks'],
        onFinish: () => {
            refreshingGrowth = false;
        },
    });
}
onMounted(() => {
    growthRefreshTimer = setInterval(refreshGrowthDate, 60_000);
    document.addEventListener('visibilitychange', refreshGrowthDate);
    refreshGrowthDate();
});
onBeforeUnmount(() => {
    clearInterval(growthRefreshTimer);
    document.removeEventListener('visibilitychange', refreshGrowthDate);
});
const coordinates = computed<[number, number] | null>(() =>
    props.farm.latitude !== null && props.farm.longitude !== null
        ? [Number(props.farm.latitude), Number(props.farm.longitude)]
        : null,
);
</script>
<template>
    <Head :title="farm.name" />
    <div class="space-y-[18px]">
        <header
            class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3"
        >
            <div class="flex min-w-0 items-center gap-2">
                <Button as-child variant="ghost" size="icon" class="shrink-0"
                    ><Link
                        :href="backUrl"
                        :aria-label="backLabel"
                        :title="backLabel"
                        ><ArrowLeft class="size-5" /></Link
                ></Button>
                <h1 class="admin-page-title min-w-0 break-words">
                    {{ farm.name }}
                </h1>
            </div>
        </header>
        <section class="overflow-hidden rounded-xl bg-card">
            <header class="px-5 pt-4 sm:px-6 sm:pt-5">
                <h2 class="admin-section-title text-primary">
                    Ringkasan lahan
                </h2>
            </header>
            <dl
                class="grid grid-cols-2 gap-x-6 gap-y-6 p-5 sm:px-6 sm:pt-4 sm:pb-6 lg:grid-cols-4"
            >
                <div>
                    <dt
                        class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                    >
                        <LandPlot
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />Luas lahan
                    </dt>
                    <dd class="admin-metric-value mt-2 text-foreground">
                        {{ hectares(farm.total_area_ha) }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >ha</span
                        >
                    </dd>
                </div>
                <div>
                    <dt
                        class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                    >
                        <MapPin
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />Lokasi lahan
                    </dt>
                    <dd
                        class="mt-2 text-xl leading-snug font-bold text-foreground"
                    >
                        {{ farm.district ?? farm.city ?? 'Belum diisi' }}
                    </dd>
                    <p class="mt-1 text-sm leading-5 text-muted-foreground">
                        {{
                            [farm.district ? farm.city : null, farm.province]
                                .filter(Boolean)
                                .join(', ')
                        }}
                    </p>
                </div>
                <div>
                    <dt
                        class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                    >
                        <Target
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />Target volume panen
                    </dt>
                    <dd class="admin-metric-value mt-2 text-foreground">
                        <template v-if="farm.target_volume_ton"
                            >{{ hectares(farm.target_volume_ton) }}
                            <span
                                class="text-sm font-normal text-muted-foreground"
                                >ton</span
                            ></template
                        ><span
                            v-else
                            class="text-sm font-normal text-muted-foreground"
                            >Belum diisi</span
                        >
                    </dd>
                </div>
                <div>
                    <dt
                        class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                    >
                        <Sprout
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />Progres pekerjaan
                    </dt>
                    <dd class="admin-metric-value mt-2 text-foreground">
                        {{ farm.progress_overview.total }}
                        <span class="text-sm font-medium text-muted-foreground"
                            >sub blok</span
                        >
                    </dd>
                </div>
            </dl>
            <dl
                class="grid gap-x-6 gap-y-4 border-t border-border bg-muted/20 px-5 py-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-3"
            >
                <div>
                    <dt class="text-xs text-muted-foreground">PIC lahan</dt>
                    <dd class="mt-1 text-sm font-semibold">
                        {{ farm.pic_name ?? 'Belum ditentukan' }}
                    </dd>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{
                            farm.pic_name
                                ? farm.pic_phone_number ||
                                  'Nomor telepon belum diisi'
                                : ''
                        }}
                    </p>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Legalitas / Kontrak
                    </dt>
                    <dd class="mt-1 text-sm font-semibold">
                        <div>
                            <Link
                                v-if="legalityUrl"
                                :href="legalityUrl"
                                :aria-label="`Lihat legalitas ${farm.name}`"
                                class="inline-block rounded-md text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring"
                            >
                                {{ landTenureLabel(farm.land_tenure) }}
                            </Link>
                            <template v-else>
                                {{ landTenureLabel(farm.land_tenure) }}
                            </template>
                        </div>
                        <p
                            v-if="
                                farm.land_tenure === 'rented' ||
                                farm.land_tenure === 'partnership'
                            "
                            class="mt-1 text-xs font-normal [overflow-wrap:anywhere] text-muted-foreground select-text"
                        >
                            {{
                                farm.land_tenure_party ||
                                'Pihak terkait belum diisi'
                            }}
                        </p>
                    </dd>
                </div>
                <div v-if="ownerSummary">
                    <dt class="text-xs text-muted-foreground">
                        Periode kontrak
                    </dt>
                    <dd class="mt-1 text-sm font-semibold">
                        {{ contractLabel }}
                    </dd>
                </div>
            </dl>
            <div class="border-t border-border p-4 sm:p-5">
                <h2 class="mb-4 text-base font-bold text-primary">
                    Peta lokasi
                </h2>
                <FarmLocationMap :model-value="coordinates" prominent />
                <p
                    v-if="farm.address"
                    class="mt-3 text-xs text-muted-foreground"
                >
                    {{ farm.address }}
                </p>
            </div>
        </section>
        <FarmCycleSummary />
        <FarmBlocksTable
            v-if="can.blocks"
            :farm="farm"
            :blocks="blocks"
            :batch-permissions="batchPermissions"
            :harvest-permissions="harvestPermissions"
            :capacity="blockCapacity"
            :can-create="can.createBlock"
            :enable-batch-filter="!ownerSummary"
            return-to="farm"
        />
        <div v-if="can.forceDelete" class="flex justify-end">
            <Link
                :href="`/admin/farm-management/${farm.id}/force-delete`"
                class="inline-flex min-h-11 items-center text-xs text-error underline underline-offset-4"
                >Hapus permanen lahan dan data terkait</Link
            >
        </div>
    </div>
</template>
