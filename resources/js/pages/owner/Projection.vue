<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Sprout } from '@lucide/vue';
import SummaryStatCard from '@/components/SummaryStatCard.vue';
import { watch } from 'vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import OwnerHarvestProjectionTable from '@/components/owner/dashboard/OwnerHarvestProjectionTable.vue';
import { Button } from '@/components/ui/button';
import { useOwnerReadFilters } from '@/composables/useOwnerReadFilters';
import type { HarvestProjection } from '@/types/harvest-projection';
import { projectionNumber } from '@/types/harvest-projection';

const props = defineProps<{
    projection: HarvestProjection;
    urls: { index: string; home: string };
}>();
const values = () => ({
    period: props.projection.period,
    farm_id: props.projection.farm_id ?? '',
});
const { filters, loading, error, feedback, submit, sync } = useOwnerReadFilters(
    props.urls.index,
    values(),
);
watch(
    () => props.projection,
    () => sync(values()),
);

function setPeriod(period: 'cycle' | 'year'): void {
    if (loading.value || period === props.projection.period) return;
    filters.value.period = period;
    submit();
}
</script>

<template>
    <Head title="Proyeksi Panen" />
    <OwnerPageHeader
        title="Total Proyeksi Panen"
        :back-href="urls.home"
        back-label="Kembali ke Ringkasan Eksekutif"
    >
        <template #heading>
            <div class="flex min-w-0 items-center justify-between gap-3">
                <h1
                    id="total-projection-heading"
                    class="admin-page-title min-w-0 break-words"
                >
                    Total Proyeksi Panen
                </h1>
                <div
                    role="group"
                    aria-label="Periode proyeksi panen"
                    class="ml-auto inline-flex shrink-0 rounded-xl bg-muted p-1"
                >
                    <button
                        v-for="option in [
                            { value: 'cycle', label: 'Per cycle' },
                            { value: 'year', label: 'Tahun' },
                        ] as const"
                        :key="option.value"
                        type="button"
                        :aria-pressed="projection.period === option.value"
                        :disabled="loading"
                        class="min-h-11 rounded-lg px-4 text-sm font-medium transition-colors hover:text-primary focus-visible:outline-2 focus-visible:outline-ring disabled:cursor-wait disabled:opacity-60 motion-reduce:transition-none"
                        :class="
                            projection.period === option.value
                                ? 'bg-card text-primary shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="setPeriod(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>
        </template>
    </OwnerPageHeader>
    <div class="min-w-0 space-y-5 lg:space-y-6" :aria-busy="loading">
        <section aria-labelledby="total-projection-heading" class="space-y-4">
            <p
                v-if="loading || error || feedback"
                :role="error ? 'alert' : 'status'"
                class="text-sm"
                :class="error ? 'text-error' : 'text-muted-foreground'"
            >
                {{ loading ? 'Memuat proyeksi panen…' : error || feedback }}
                <Button
                    v-if="error"
                    variant="outline"
                    size="sm"
                    class="ml-2"
                    @click="submit()"
                    >Coba lagi</Button
                >
            </p>
            <div
                class="grid min-w-0 gap-3 sm:grid-cols-3 lg:gap-5"
                aria-live="polite"
            >
                <SummaryStatCard
                    v-for="card in projection.cards"
                    :key="card.key"
                    :label="`${card.name} (ton)`"
                    :value="projectionNumber(card.projection_ton)"
                    :icon="Sprout"
                />
            </div>
            <p v-if="projection.cards.some((card) => card.missing_count)" role="status" class="text-xs text-muted-foreground">
                Sebagian data luas lahan belum tersedia.
            </p>
            <p
                v-if="projection.unidentified_batches"
                role="status"
                class="text-xs leading-5 text-muted-foreground"
            >
                {{ projection.unidentified_batches }} batch aktif belum dapat
                ditampilkan karena data tanamannya belum tersedia.
            </p>
        </section>
        <OwnerHarvestProjectionTable
            :projection="projection"
            :loading="loading"
        />
    </div>
</template>
