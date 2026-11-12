<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { ChevronDown, MapPin } from '@lucide/vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import type { WeeklyOwnerContent } from '@/types/notifications';
import HarvestSummaryTable from './HarvestSummaryTable.vue';

const props = defineProps<{ content: WeeklyOwnerContent }>();
const selected = ref('all');
const selectId = useId();
const regions = computed(() =>
    props.content.regions.filter(
        (region) => selected.value === 'all' || region.key === selected.value,
    ),
);
const reportCount = (region: WeeklyOwnerContent['regions'][number]) =>
    region.commodities.reduce((sum, crop) => sum + crop.harvest_count, 0);
</script>

<template>
    <div class="space-y-5" data-notification-content="weekly_owner">
        <section
            class="rounded-xl border border-border bg-muted/20 p-3 @md:p-4"
            aria-label="Total seluruh kebun"
        >
            <h3 class="mb-1 text-sm font-semibold">Total seluruh kebun</h3>
            <p class="mb-4 text-xs leading-5 text-muted-foreground">
                Gabungan seluruh tenant. Target dihitung per siklus, bukan per
                minggu.
            </p>
            <HarvestSummaryTable
                :summary="content.totals"
                label="Total target dan panen seluruh kebun"
            />
        </section>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h3 class="text-sm font-semibold">Rincian kabupaten / kota</h3>
            <div
                v-if="content.regions.length > 1"
                class="w-full min-w-0 @md:w-auto @md:min-w-48"
            >
                <label :for="selectId" class="sr-only"
                    >Pilih kabupaten atau kota</label
                >
                <AppSelect
                    :id="selectId"
                    v-model="selected"
                    class="w-full rounded-lg border border-border bg-background text-sm"
                >
                    <AppSelectOption value="all"
                        >Semua kabupaten / kota</AppSelectOption
                    >
                    <AppSelectOption
                        v-for="region in content.regions"
                        :key="region.key"
                        :value="region.key"
                        >{{ region.name }}</AppSelectOption
                    >
                </AppSelect>
            </div>
        </div>
        <div class="space-y-2">
            <details
                v-for="region in regions"
                :key="region.key"
                :open="regions.length === 1"
                class="group/region rounded-xl border border-border"
            >
                <summary
                    class="flex min-h-12 cursor-pointer list-none items-center gap-2 rounded-xl px-3 py-3 focus-visible:outline-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
                >
                    <MapPin
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium">{{
                            region.name
                        }}</span>
                        <span
                            class="mt-0.5 block text-xs text-muted-foreground"
                            >{{
                                reportCount(region)
                                    ? reportCount(region) + ' laporan panen'
                                    : 'Belum ada panen'
                            }}</span
                        >
                    </span>
                    <ChevronDown
                        class="size-4 shrink-0 text-muted-foreground transition-transform group-open/region:rotate-180 motion-reduce:transition-none"
                        aria-hidden="true"
                    />
                </summary>
                <div class="border-t border-border p-3 @md:p-4">
                    <HarvestSummaryTable
                        :summary="region"
                        :label="'Target dan panen ' + region.name"
                    />
                </div>
            </details>
        </div>
    </div>
</template>
