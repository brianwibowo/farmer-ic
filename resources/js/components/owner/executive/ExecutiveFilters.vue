<script setup lang="ts">
import { RefreshCw, SlidersHorizontal, X } from "@lucide/vue";
import { computed, ref, useId } from "vue";
import { Button } from "@/components/ui/button";
import AppSelect from "@/components/selects/AppSelect.vue";
import AppSelectOption from "@/components/selects/AppSelectOption.vue";
import type {
    ExecutiveSummaryData,
    ExecutiveFilters,
} from "@/types/owner-executive";

const props = defineProps<{
    filters: ExecutiveFilters;
    data: ExecutiveSummaryData;
    years: number[];
    cycles: { id: string; name: string }[];
    regions: string[];
    farmOptions: ExecutiveSummaryData["farms"];
    loading: boolean;
    count: number;
}>();
const emit = defineEmits<{
    change: [filters: ExecutiveFilters];
    reset: [];
    refresh: [];
}>();
const expanded = ref(false);
const panelId = useId();
const chips = computed(() =>
    [
        {
            key: "partner",
            label: props.data.partners.find(
                (item) => item.id === props.filters.partner,
            )?.name,
        },
        { key: "region", label: props.filters.region },
        {
            key: "farm",
            label: props.data.farms.find(
                (item) => item.id === props.filters.farm,
            )?.name,
        },
        {
            key: "commodity",
            label: props.data.commodities.find(
                (item) => item.id === props.filters.commodity,
            )?.name,
        },
    ].filter((item) => item.label),
);
const changed = computed(
    () =>
        chips.value.length > 0 ||
        props.filters.year !== props.data.defaultYear ||
        props.filters.cycle !== "all",
);
function set<K extends keyof ExecutiveFilters>(
    key: K,
    value: ExecutiveFilters[K],
) {
    emit("change", {
        ...props.filters,
        [key]: value,
    });
}
function clear(key: string) {
    emit("change", { ...props.filters, [key]: "" });
}
</script>

<template>
    <section
        aria-label="Filter global"
        class="rounded-xl border border-border bg-card px-4 py-3"
    >
        <div
            class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-end gap-2 sm:flex sm:flex-wrap sm:gap-3"
        >
            <fieldset
                :disabled="loading"
                class="col-span-3 grid min-w-0 grid-cols-2 items-end gap-2 sm:w-auto sm:grid-cols-[7rem_10rem]"
            >
                <legend class="sr-only">Periode ringkasan</legend>
                <div class="exec-label">
                    <label :for="`${panelId}-year`">Tahun</label>
                    <AppSelect
                        :id="`${panelId}-year`"
                        :model-value="filters.year"
                        :disabled="loading"
                        class="admin-field min-h-11!"
                        @update:model-value="set('year', $event)"
                    >
                        <AppSelectOption
                            v-for="year in years"
                            :key="year"
                            :value="year"
                        >
                            {{ year }}
                        </AppSelectOption>
                    </AppSelect>
                </div>
                <div class="exec-label">
                    <label :for="`${panelId}-cycle`">Cycle</label>
                    <AppSelect
                        :id="`${panelId}-cycle`"
                        :model-value="filters.cycle"
                        :disabled="loading"
                        class="admin-field min-h-11!"
                        @update:model-value="set('cycle', $event)"
                    >
                        <AppSelectOption value="all"
                            >Semua cycle</AppSelectOption
                        >
                        <AppSelectOption
                            v-for="cycle in cycles"
                            :key="cycle.id"
                            :value="cycle.id"
                        >
                            {{ cycle.name }}
                        </AppSelectOption>
                    </AppSelect>
                </div>
            </fieldset>
            <Button
                type="button"
                variant="outline"
                class="min-h-11 justify-self-start"
                :disabled="loading"
                :aria-expanded="expanded"
                :aria-controls="panelId"
                @click="expanded = !expanded"
            >
                <SlidersHorizontal class="size-4" aria-hidden="true" />Filter
                <span
                    v-if="chips.length"
                    class="rounded bg-primary/15 px-1.5 text-xs"
                    >{{ chips.length }}</span
                >
            </Button>
            <div
                class="flex min-h-11 min-w-0 flex-wrap items-center justify-end gap-x-3 self-center text-xs text-muted-foreground sm:ml-auto"
            >
                <span aria-live="polite">{{ count }} lahan terpilih</span>
                <button
                    v-if="changed"
                    type="button"
                    :disabled="loading"
                    class="min-h-11 font-medium text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                    @click="emit('reset')"
                >
                    Reset filter
                </button>
            </div>
            <Button
                type="button"
                size="icon"
                class="min-h-11 min-w-11 shrink-0 self-center"
                :disabled="loading"
                aria-label="Perbarui ringkasan eksekutif"
                title="Refresh"
                @click="emit('refresh')"
            >
                <RefreshCw
                    class="size-4"
                    :class="loading ? 'motion-safe:animate-spin' : ''"
                    aria-hidden="true"
                />
            </Button>
        </div>
        <fieldset
            v-if="expanded"
            :id="panelId"
            :disabled="loading"
            class="mt-3 grid gap-3 border-t border-border pt-3 sm:grid-cols-2 xl:grid-cols-4"
        >
            <legend class="sr-only">Filter tambahan</legend>
            <div class="exec-label">
                <label :for="`${panelId}-partner`">Mitra kerja sama</label>
                <AppSelect
                    :id="`${panelId}-partner`"
                    :model-value="filters.partner"
                    :disabled="loading"
                    class="admin-field min-h-11!"
                    @update:model-value="set('partner', $event)"
                >
                    <AppSelectOption value="">Semua mitra</AppSelectOption>
                    <AppSelectOption
                        v-for="partner in data.partners"
                        :key="partner.id"
                        :value="partner.id"
                    >
                        {{ partner.name }}
                    </AppSelectOption>
                </AppSelect>
            </div>
            <div class="exec-label">
                <label :for="`${panelId}-region`">Wilayah</label>
                <AppSelect
                    :id="`${panelId}-region`"
                    :model-value="filters.region"
                    :disabled="loading"
                    class="admin-field min-h-11!"
                    @update:model-value="set('region', $event)"
                >
                    <AppSelectOption value="">Seluruh wilayah</AppSelectOption>
                    <AppSelectOption
                        v-for="region in regions"
                        :key="region"
                        :value="region"
                    >
                        {{ region }}
                    </AppSelectOption>
                </AppSelect>
            </div>
            <div class="exec-label">
                <label :for="`${panelId}-farm`">Lahan</label>
                <AppSelect
                    :id="`${panelId}-farm`"
                    :model-value="filters.farm"
                    :disabled="loading"
                    class="admin-field min-h-11!"
                    @update:model-value="set('farm', $event)"
                >
                    <AppSelectOption value="">Semua lahan</AppSelectOption>
                    <AppSelectOption
                        v-for="farm in farmOptions"
                        :key="farm.id"
                        :value="farm.id"
                    >
                        {{ farm.name }}
                    </AppSelectOption>
                </AppSelect>
            </div>
            <div class="exec-label">
                <label :for="`${panelId}-commodity`">Komoditas</label>
                <AppSelect
                    :id="`${panelId}-commodity`"
                    :model-value="filters.commodity"
                    :disabled="loading"
                    class="admin-field min-h-11!"
                    @update:model-value="set('commodity', $event)"
                >
                    <AppSelectOption value="">Semua komoditas</AppSelectOption>
                    <AppSelectOption
                        v-for="crop in data.commodities"
                        :key="crop.id"
                        :value="crop.id"
                    >
                        {{ crop.name }}
                    </AppSelectOption>
                </AppSelect>
            </div>
        </fieldset>
        <div
            v-if="chips.length"
            class="mt-2 flex flex-wrap gap-1.5"
            aria-label="Filter aktif"
        >
            <button
                v-for="chip in chips"
                :key="chip.key"
                type="button"
                :aria-label="`Hapus filter ${chip.label}`"
                class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary/10 px-3 text-xs font-medium text-secondary hover:bg-primary/20 focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                @click="clear(chip.key)"
            >
                {{ chip.label }}<X class="size-3.5" aria-hidden="true" />
            </button>
        </div>
    </section>
</template>
