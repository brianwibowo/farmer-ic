<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    ArrowLeft,
    ClipboardList,
    ExternalLink,
    LandPlot,
    MapPin,
    Target,
    Sprout,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import FarmBlocksTable from '@/components/blocks/FarmBlocksTable.vue';
import DeleteFarmDialog from '@/components/farms/DeleteFarmDialog.vue';
import FarmLocationMap from '@/components/farms/FarmLocationMap.vue';
import {
    hectares,
    landTenureLabel,
    type ManagedFarm,
    type FarmPermissions,
} from '@/types/farms';
import type { ManagedBlock, BlockFormCapacity } from '@/types/blocks';
defineOptions({ inheritAttrs: false });
const props = defineProps<{
    farm: ManagedFarm;
    blocks: ManagedBlock[];
    batchPermissions: Record<string, boolean>;
    blockCapacity: BlockFormCapacity | null;
    deletionBlockers: string[];
    legalityUrl: string | null;
    can: FarmPermissions & {
        dailyLogs: boolean;
        blocks: boolean;
        createBlock: boolean;
    };
}>();
const deleting = ref(false);
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
            block.batches.some(
                (batch) => batch.growth && batch.growth.as_of !== today,
            ),
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
const progressDescriptions: Record<string, string> = {
    land_clearing: 'sedang dibersihkan',
    soil_preparation: 'sedang dalam pengolahan tanah',
    basic_fertilizer: 'sedang dalam pemupukan dasar',
    planting: 'sedang ditanami',
    maintenance: 'sedang dalam pemeliharaan',
    harvest: 'berada di tahap panen',
    not_started: 'belum memulai pekerjaan',
};
const progressStages = computed(() =>
    props.farm.progress_overview.stages.filter((stage) => stage.count > 0),
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
                        href="/admin/farm-management"
                        aria-label="Daftar kebun"
                        title="Daftar kebun"
                        ><ArrowLeft class="size-5" /></Link
                ></Button>
                <h1 class="admin-page-title min-w-0 break-words">
                    {{ farm.name }}
                </h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a
                    v-if="can.dailyLogs"
                    :href="`/kebun/${farm.id}/daily-logs`"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm font-medium text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring"
                    ><ClipboardList class="size-4" />Logbook</a
                >
                <Button v-if="can.update" as-child variant="outline"
                    ><Link :href="`/admin/farm-management/${farm.id}/edit`"
                        ><Pencil />Ubah kebun</Link
                    ></Button
                >
                <Button
                    v-if="can.delete"
                    variant="destructive-ghost"
                    size="icon"
                    aria-label="Hapus kebun"
                    @click="deleting = true"
                    ><Trash2
                /></Button>
            </div>
        </header>
        <section class="overflow-hidden rounded-xl bg-card">
            <header class="px-5 pt-4 sm:px-6 sm:pt-5">
                <h2 class="admin-section-title text-primary">
                    Ringkasan kebun
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
                        />Luas kebun
                    </dt>
                    <dd class="admin-metric-value mt-2 text-foreground">
                        {{ hectares(farm.total_area_ha) }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >ha</span
                        >
                    </dd>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Total luas lahan terdaftar.
                    </p>
                </div>
                <div>
                    <dt
                        class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                    >
                        <MapPin
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />Lokasi kebun
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
                    <p class="mt-1 text-xs text-muted-foreground">
                        Target hasil panen yang ditetapkan.
                    </p>
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
                    <ul
                        v-if="progressStages.length"
                        class="mt-2 space-y-2 text-xs leading-5 text-muted-foreground"
                        aria-label="Tahap pekerjaan sub blok saat ini"
                    >
                        <li v-for="stage in progressStages" :key="stage.code">
                            <strong
                                class="font-semibold text-foreground tabular-nums"
                                >{{ stage.count }} sub blok</strong
                            >
                            {{
                                progressDescriptions[stage.code] ??
                                'belum memulai pekerjaan'
                            }}.
                        </li>
                    </ul>
                    <p v-else class="mt-1 text-xs text-muted-foreground">
                        Belum ada progres.
                    </p>
                </div>
            </dl>
            <dl
                class="grid gap-x-6 gap-y-4 border-t border-border bg-muted/20 px-5 py-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4"
            >
                <div>
                    <dt class="text-xs text-muted-foreground">PIC kebun</dt>
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
                        Komoditas yang ditanam
                    </dt>
                    <dd class="mt-1 text-sm font-semibold">
                        {{
                            farm.commodity?.name ??
                            farm.default_sop?.commodity?.name ??
                            'Belum dipilih'
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Legalitas</dt>
                    <dd class="mt-1 text-sm font-semibold">
                        <Link
                            v-if="legalityUrl"
                            :href="legalityUrl"
                            :aria-label="`Lihat legalitas ${farm.name}`"
                            class="block min-h-11 rounded-md focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            <span
                                class="block text-primary underline underline-offset-4"
                                >{{ landTenureLabel(farm.land_tenure) }}</span
                            >
                            <span
                                v-if="
                                    farm.land_tenure === 'rented' ||
                                    farm.land_tenure === 'partnership'
                                "
                                class="mt-1 block text-xs font-normal [overflow-wrap:anywhere] text-muted-foreground"
                                >{{
                                    farm.land_tenure_party ||
                                    'Pihak terkait belum diisi'
                                }}</span
                            >
                        </Link>
                        <template v-else>
                            {{ landTenureLabel(farm.land_tenure) }}
                            <span
                                v-if="
                                    farm.land_tenure === 'rented' ||
                                    farm.land_tenure === 'partnership'
                                "
                                class="mt-1 block text-xs font-normal [overflow-wrap:anywhere] text-muted-foreground"
                                >{{
                                    farm.land_tenure_party ||
                                    'Pihak terkait belum diisi'
                                }}</span
                            >
                        </template>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Panduan budidaya (SOP)
                    </dt>
                    <dd class="mt-1 text-sm font-semibold">
                        {{ farm.default_sop?.name ?? 'Belum dipilih' }}
                    </dd>
                    <p
                        v-if="farm.default_sop && !farm.default_sop.is_usable"
                        class="mt-1 text-xs text-warning"
                    >
                        SOP belum siap digunakan.
                    </p>
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
                <a
                    v-if="farm.image_url"
                    :href="farm.image_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-3 inline-flex min-h-11 items-center gap-2 text-sm text-primary underline underline-offset-4"
                    >Lihat foto kebun<ExternalLink class="size-4"
                /></a>
            </div>
        </section>
        <FarmBlocksTable
            v-if="can.blocks"
            :farm="farm"
            :blocks="blocks"
            :batch-permissions="batchPermissions"
            :capacity="blockCapacity"
            :can-create="can.createBlock"
            return-to="farm"
        />
        <div v-if="can.forceDelete" class="flex justify-end">
            <Link
                :href="`/admin/farm-management/${farm.id}/force-delete`"
                class="inline-flex min-h-11 items-center text-xs text-error underline underline-offset-4"
                >Hapus permanen kebun dan data terkait</Link
            >
        </div>
    </div>
    <DeleteFarmDialog
        :farm="deleting ? farm : null"
        @close="deleting = false"
    />
</template>
