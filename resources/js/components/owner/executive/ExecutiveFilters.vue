<script setup lang="ts">
import { SlidersHorizontal, X } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { Button } from '@/components/ui/button';
import type {
    ExecutiveDemoData,
    ExecutiveFilters,
} from '@/types/owner-executive';

const props = defineProps<{
    filters: ExecutiveFilters;
    data: ExecutiveDemoData;
    years: number[];
    cycles: number[];
    regions: string[];
    farmOptions: ExecutiveDemoData['farms'];
    loading: boolean;
    count: number;
}>();
const emit = defineEmits<{ change: [filters: ExecutiveFilters]; reset: [] }>();
const expanded = ref(false);
const panelId = useId();
const chips = computed(() =>
    [
        {
            key: 'partner',
            label: props.data.partners.find(
                (item) => item.id === props.filters.partner,
            )?.name,
        },
        { key: 'region', label: props.filters.region },
        {
            key: 'farm',
            label: props.data.farms.find(
                (item) => item.id === props.filters.farm,
            )?.name,
        },
        {
            key: 'commodity',
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
        props.filters.cycle !== props.data.defaultCycle,
);
function set(key: keyof ExecutiveFilters, event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    emit('change', {
        ...props.filters,
        [key]: key === 'year' || key === 'cycle' ? Number(value) : value,
    });
}
function clear(key: string) {
    emit('change', { ...props.filters, [key]: '' });
}
</script>

<template>
    <section
        aria-label="Filter global"
        class="rounded-xl border border-border bg-card px-4 py-3"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <fieldset
                :disabled="loading"
                class="grid w-full grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-end gap-2 sm:w-auto sm:grid-cols-[7rem_8rem_auto]"
            >
                <legend class="sr-only">Periode ringkasan</legend>
                <label class="exec-label"
                    >Tahun<select
                        :value="filters.year"
                        class="exec-field"
                        @change="set('year', $event)"
                    >
                        <option v-for="year in years" :key="year" :value="year">
                            {{ year }}
                        </option>
                    </select></label
                >
                <label class="exec-label"
                    >Cycle<select
                        :value="filters.cycle"
                        class="exec-field"
                        @change="set('cycle', $event)"
                    >
                        <option
                            v-for="cycle in cycles"
                            :key="cycle"
                            :value="cycle"
                        >
                            Cycle {{ cycle }}
                        </option>
                    </select></label
                >
                <Button
                    variant="outline"
                    class="min-h-11"
                    :aria-expanded="expanded"
                    :aria-controls="panelId"
                    @click="expanded = !expanded"
                    ><SlidersHorizontal
                        class="size-4"
                        aria-hidden="true"
                    />Filter<span
                        v-if="chips.length"
                        class="rounded bg-primary/15 px-1.5 text-xs"
                        >{{ chips.length }}</span
                    ></Button
                >
            </fieldset>
            <div class="flex items-center gap-3 text-xs text-muted-foreground">
                <span aria-live="polite">{{ count }} kebun terpilih</span
                ><button
                    v-if="changed"
                    type="button"
                    class="min-h-11 font-medium text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                    @click="emit('reset')"
                >
                    Reset filter
                </button>
            </div>
        </div>
        <fieldset
            v-if="expanded"
            :id="panelId"
            :disabled="loading"
            class="mt-3 grid gap-3 border-t border-border pt-3 sm:grid-cols-2 xl:grid-cols-4"
        >
            <legend class="sr-only">Filter tambahan</legend>
            <label class="exec-label"
                >Mitra kerja sama<select
                    :value="filters.partner"
                    class="exec-field"
                    @change="set('partner', $event)"
                >
                    <option value="">Semua mitra</option>
                    <option
                        v-for="partner in data.partners"
                        :key="partner.id"
                        :value="partner.id"
                    >
                        {{ partner.name }}
                    </option>
                </select></label
            >
            <label class="exec-label"
                >Wilayah<select
                    :value="filters.region"
                    class="exec-field"
                    @change="set('region', $event)"
                >
                    <option value="">Seluruh wilayah</option>
                    <option v-for="region in regions" :key="region">
                        {{ region }}
                    </option>
                </select></label
            >
            <label class="exec-label"
                >Kebun<select
                    :value="filters.farm"
                    class="exec-field"
                    @change="set('farm', $event)"
                >
                    <option value="">Semua kebun</option>
                    <option
                        v-for="farm in farmOptions"
                        :key="farm.id"
                        :value="farm.id"
                    >
                        {{ farm.name }}
                    </option>
                </select></label
            >
            <label class="exec-label"
                >Komoditas<select
                    :value="filters.commodity"
                    class="exec-field"
                    @change="set('commodity', $event)"
                >
                    <option value="">Semua komoditas</option>
                    <option
                        v-for="crop in data.commodities"
                        :key="crop.id"
                        :value="crop.id"
                    >
                        {{ crop.name }}
                    </option>
                </select></label
            >
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
