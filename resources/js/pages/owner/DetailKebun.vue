<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { watch } from "vue";
import OwnerPageHeader from "@/components/owner/OwnerPageHeader.vue";
import OwnerHarvestProjectionTable from "@/components/owner/dashboard/OwnerHarvestProjectionTable.vue";
import { Button } from "@/components/ui/button";
import { useOwnerReadFilters } from "@/composables/useOwnerReadFilters";
import type { HarvestProjection } from "@/types/harvest-projection";
import { projectionNumber } from "@/types/harvest-projection";

const props = defineProps<{ projection: HarvestProjection }>();
const values = () => ({
    period: props.projection.period,
    farm_id: props.projection.farm_id ?? "",
});
const { filters, loading, error, feedback, submit, sync } = useOwnerReadFilters(
    "/kebun/proyeksi",
    values(),
);
watch(
    () => props.projection,
    () => sync(values()),
);

function setPeriod(period: "cycle" | "year"): void {
    if (loading.value || period === props.projection.period) return;
    filters.value.period = period;
    submit();
}
</script>

<template>
    <Head title="Detail Kebun" />
    <OwnerPageHeader
        title="Total Proyeksi Panen"
        back-href="/"
        back-label="Kembali ke Beranda"
    />
    <div class="min-w-0 space-y-5 lg:space-y-6" :aria-busy="loading">
        <section aria-labelledby="total-projection-heading" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div
                    role="group"
                    aria-label="Periode proyeksi panen"
                    class="inline-flex flex-end rounded-xl bg-muted p-1"
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
                        class="min-h-11 rounded-lg px-4 text-sm font-medium transition-colors hover:text-secondary focus-visible:outline-2 focus-visible:outline-ring disabled:cursor-wait disabled:opacity-60 motion-reduce:transition-none dark:hover:text-primary"
                        :class="
                            projection.period === option.value
                                ? 'bg-card text-secondary shadow-sm dark:text-primary'
                                : 'text-muted-foreground'
                        "
                        @click="setPeriod(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>
            <p
                v-if="loading || error || feedback"
                :role="error ? 'alert' : 'status'"
                class="text-sm"
                :class="error ? 'text-error' : 'text-muted-foreground'"
            >
                {{ loading ? "Memuat proyeksi panen…" : error || feedback }}
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
                <article
                    v-for="card in projection.cards"
                    :key="card.key"
                    class="min-w-0 rounded-xl border border-border bg-card p-5 lg:p-6"
                >
                    <h3
                        class="text-sm font-medium text-muted-foreground lg:text-base"
                    >
                        {{ card.name }}
                    </h3>
                    <p
                        class="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-1"
                    >
                        <span
                            class="text-3xl leading-tight font-bold break-all text-secondary tabular-nums lg:text-4xl dark:text-primary"
                            >{{ projectionNumber(card.projection_ton) }}</span
                        >
                        <span class="text-sm text-muted-foreground">ton</span>
                    </p>
                    <p
                        v-if="card.missing_count"
                        class="mt-1 text-xs leading-5 text-muted-foreground"
                    >
                        Data luas kebun belum tersedia.
                    </p>
                </article>
            </div>
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
