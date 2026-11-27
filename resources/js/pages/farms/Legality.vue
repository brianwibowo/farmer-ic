<script setup lang="ts">
import PageBackButton from '@/components/PageBackButton.vue';
import { Head } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import { computed } from 'vue';
import FarmLegalityPanel from '@/components/farms/FarmLegalityPanel.vue';
import type {
    FarmLegalityDetail,
    FarmLegalityLimits,
    FarmLegalityPermissions,
    FarmLegalityUrls,
} from '@/types/farm-legality';
import { hectares, landTenureLabel, type ManagedFarm } from '@/types/farms';

const props = defineProps<{
    farm: Pick<
        ManagedFarm,
        | 'id'
        | 'name'
        | 'land_tenure'
        | 'land_tenure_party'
        | 'total_area_ha'
        | 'city'
        | 'district'
        | 'province'
    >;
    legality: FarmLegalityDetail | null;
    legalityPermissions: FarmLegalityPermissions;
    legalityUrls: FarmLegalityUrls;
    legalityLimits: FarmLegalityLimits;
    backUrl: string;
}>();
const location = computed(() =>
    [props.farm.district, props.farm.city, props.farm.province]
        .filter(Boolean)
        .join(', '),
);
</script>

<template>
    <Head :title="`Legalitas · ${farm.name}`" />
    <div class="min-w-0 space-y-[18px]">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 flex-1 items-start gap-2">
                <PageBackButton
                    :href="backUrl"
                    label="Kembali ke detail lahan"
                />
                <div class="min-w-0">
                    <h1 class="admin-page-title">Detail legalitas</h1>
                    <p
                        class="mt-1 text-base font-semibold [overflow-wrap:anywhere]"
                    >
                        {{ farm.name }}
                    </p>
                    <p
                        class="mt-2 flex items-start gap-1.5 text-xs leading-5 text-muted-foreground"
                    >
                        <MapPin
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span
                            >{{ location || 'Lokasi belum tercatat' }} ·
                            {{ hectares(farm.total_area_ha) }} ha</span
                        >
                    </p>
                </div>
            </div>
            <span
                class="rounded-lg bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary"
                >{{ landTenureLabel(farm.land_tenure) }}</span
            >
        </header>
        <FarmLegalityPanel
            :farm="farm"
            :legality="legality"
            :permissions="legalityPermissions"
            :urls="legalityUrls"
            :limits="legalityLimits"
        />
    </div>
</template>
