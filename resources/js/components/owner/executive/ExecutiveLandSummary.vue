<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import OwnerDistributionChart from '@/components/owner/OwnerDistributionChart.vue';
import ExecutivePanel from './ExecutivePanel.vue';
import { executiveNumber as number } from '@/services/ownerExecutiveSummary';

const props = defineProps<{
    partners: { id: string; name: string; area: number; farmCount: number }[];
    categories: { id: string; name: string; value: number }[];
}>();
const items = computed(() =>
    props.partners.map((partner) => ({
        id: partner.id,
        name: partner.name,
        value: partner.area,
        detail: `${partner.farmCount} lahan`,
    })),
);
const monitoringUrl = '/monitoring-lahan';
function focusDialogHeading(event: Event): void {
    if (!(event.target instanceof HTMLElement)) return;
    const heading = event.target.querySelector<HTMLElement>(
        '[data-slot="dialog-title"]',
    );
    if (!heading) return;

    event.preventDefault();
    heading.focus();
}
</script>

<template>
    <ExecutivePanel id="kerja-sama" title="Kerja sama & luas lahan">
        <template #actions>
            <Button as-child variant="outline" size="sm" class="min-h-11"
                ><Link
                    :href="monitoringUrl"
                    aria-label="Lihat detail kerja sama dan luas lahan"
                    >Lihat detail<ArrowUpRight aria-hidden="true" /></Link
            ></Button>
        </template>
        <div class="grid min-w-0 gap-5 xl:grid-cols-2 xl:gap-x-10">
            <div class="min-w-0">
                <h3 class="mb-4 text-sm font-semibold">Per kategori</h3>
                <OwnerDistributionChart
                    class="land-distribution"
                    title="Luas lahan per kategori"
                    :items="categories"
                    unit="Ha"
                    total-label="Luas lahan"
                    category-label="kategori"
                    embedded
                    compact
                    :show-footer="false"
                />
            </div>
            <div
                class="min-w-0 border-t border-border pt-5 xl:relative xl:border-t-0 xl:pt-0 xl:before:absolute xl:before:inset-y-0 xl:before:-left-5 xl:before:w-px xl:before:bg-border"
            >
                <h3 class="mb-4 text-sm font-semibold">Per mitra lahan</h3>
                <OwnerDistributionChart
                    class="land-distribution"
                    title="Luas lahan per mitra"
                    :items="items"
                    unit="Ha"
                    total-label="Luas lahan"
                    category-label="mitra"
                    :legend-limit="3"
                    embedded
                    compact
                    :show-footer="false"
                >
                    <template #item="{ item }">
                        <span class="min-w-0 flex-1">
                            <span
                                class="flex items-start justify-between gap-2"
                            >
                                <span class="text-sm font-semibold">{{
                                    item.name
                                }}</span>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                                    >{{ item.percentage }}</span
                                >
                            </span>
                            <span
                                class="mt-1 block text-xs text-muted-foreground"
                                ><strong class="font-medium text-foreground"
                                    >{{ number(item.value) }} Ha</strong
                                >
                                · {{ item.detail }}</span
                            >
                        </span>
                    </template>
                    <template #legend-overflow="{ count, items: allPartners }">
                        <Dialog>
                            <DialogTrigger as-child>
                                <button
                                    type="button"
                                    class="flex min-h-[60px] w-full flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-xl border border-border border-b-transparent bg-linear-to-b from-primary/8 to-transparent px-3 py-2 text-left text-sm hover:from-primary/14 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    <span class="text-xs text-muted-foreground"
                                        >{{ count }} mitra lainnya</span
                                    >
                                    <span
                                        class="inline-flex items-center gap-1.5 font-semibold text-primary"
                                    >
                                        Lihat lainnya
                                        <ChevronRight
                                            class="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </button>
                            </DialogTrigger>
                            <DialogContent
                                :show-close-button="false"
                                class="admin-dashboard-theme max-h-[calc(100dvh-2rem)] grid-rows-[auto_minmax(0,1fr)_auto] rounded-xl bg-card p-4 text-foreground motion-reduce:animate-none sm:max-w-3xl sm:p-6"
                                @open-auto-focus="focusDialogHeading"
                            >
                                <DialogHeader class="text-left">
                                    <DialogTitle as-child>
                                        <h2 tabindex="-1" class="outline-none">
                                            Daftar mitra lahan
                                        </h2>
                                    </DialogTitle>
                                    <DialogDescription
                                        >{{ allPartners.length }} mitra pada
                                        filter ini.</DialogDescription
                                    >
                                </DialogHeader>
                                <div
                                    class="min-h-0 min-w-0 overflow-y-auto overscroll-contain px-1 py-1"
                                >
                                    <OwnerDistributionChart
                                        class="partner-dialog-chart"
                                        title="Distribusi luas lahan per mitra"
                                        :items="allPartners"
                                        unit="ha"
                                        total-label="Luas lahan"
                                        category-label="mitra"
                                        embedded
                                        :show-footer="false"
                                    >
                                        <template #item="{ item }">
                                            <span class="min-w-0 flex-1">
                                                <span
                                                    class="flex items-start justify-between gap-2"
                                                >
                                                    <span
                                                        class="min-w-0 text-sm font-semibold break-words"
                                                        >{{ item.name }}</span
                                                    >
                                                    <span
                                                        class="shrink-0 text-xs leading-5 text-muted-foreground tabular-nums"
                                                        >{{
                                                            item.percentage
                                                        }}</span
                                                    >
                                                </span>
                                                <span
                                                    class="mt-1 block text-xs text-muted-foreground"
                                                >
                                                    <strong
                                                        class="font-medium text-foreground"
                                                        >{{
                                                            item.valueLabel
                                                        }}
                                                        ha</strong
                                                    >
                                                    · {{ item.detail }}
                                                </span>
                                            </span>
                                        </template>
                                    </OwnerDistributionChart>
                                </div>
                                <DialogFooter
                                    class="flex-row flex-wrap justify-end border-t border-border pt-3"
                                >
                                    <DialogClose as-child>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="min-h-11"
                                            >Tutup</Button
                                        >
                                    </DialogClose>
                                    <Button as-child class="min-h-11">
                                        <Link :href="monitoringUrl">
                                            Lihat detail
                                            <ArrowUpRight aria-hidden="true" />
                                        </Link>
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </template>
                </OwnerDistributionChart>
            </div>
        </div>
    </ExecutivePanel>
</template>

<style scoped>
.land-distribution :deep(.owner-commodity-center > p:first-child) {
    flex-shrink: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
@container (400px <= width < 480px) {
    .land-distribution :deep(.owner-commodity-body) {
        grid-template-columns: 170px minmax(0, 1fr);
        gap: 12px;
    }
    .land-distribution :deep(.owner-commodity-plot) {
        width: 170px;
    }
}
@container (min-width: 480px) {
    .partner-dialog-chart :deep(.owner-commodity-body > ul) {
        max-block-size: min(48dvh, 24rem);
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 4px;
    }
}
</style>
