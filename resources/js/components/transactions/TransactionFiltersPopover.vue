<script setup lang="ts">
import { LoaderCircle, SlidersHorizontal, X } from '@lucide/vue';
import {
    PopoverClose,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, watch } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Button } from '@/components/ui/button';

export type TransactionFarmOption = { id: string; name: string };
export type TransactionBlockOption = {
    id: string;
    farm_id: string;
    name: string;
};
export type TransactionVarietyOption = {
    id: string;
    name: string;
    commodity: { id: string; name: string } | null;
};

const props = withDefaults(
    defineProps<{
        idPrefix?: string;
        farms: TransactionFarmOption[];
        blocks: TransactionBlockOption[];
        varieties: TransactionVarietyOption[];
        processing: boolean;
        count: number;
        errors: Partial<
            Record<
                'farm_id' | 'block_id' | 'variety_id' | 'from_date' | 'to_date',
                string
            >
        >;
    }>(),
    { idPrefix: 'transaction' },
);

const open = defineModel<boolean>('open', { required: true });
const farmId = defineModel<string>('farmId', { required: true });
const blockId = defineModel<string>('blockId', { required: true });
const varietyId = defineModel<string>('varietyId', { required: true });
const fromDate = defineModel<string>('fromDate', { required: true });
const toDate = defineModel<string>('toDate', { required: true });

const emit = defineEmits<{
    apply: [];
    reset: [];
}>();

const blockOptions = computed(() =>
    farmId.value
        ? props.blocks.filter((block) => block.farm_id === farmId.value)
        : [],
);

function varietyLabel(variety: TransactionVarietyOption): string {
    return variety.commodity
        ? `${variety.name} · ${variety.commodity.name}`
        : variety.name;
}

watch(farmId, (next, prev) => {
    if (next !== prev) {
        if (
            blockId.value &&
            !blockOptions.value.some((block) => block.id === blockId.value)
        ) {
            blockId.value = '';
        }
        if (!next) {
            blockId.value = '';
        }
    }
});
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :variant="count ? 'secondary' : 'outline'"
                type="button"
                class="h-10 px-2.5! sm:px-4! pointer-coarse:min-h-11"
                :disabled="processing"
                aria-label="Filter transaksi"
                title="Filter transaksi"
            >
                <SlidersHorizontal aria-hidden="true" />Filter
                <span
                    v-if="count"
                    class="rounded-full bg-accent px-1.5 text-xs text-accent-foreground"
                    :aria-label="count + ' filter aktif'"
                    >{{ count }}</span
                >
            </Button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                side="bottom"
                align="end"
                :side-offset="10"
                :collision-padding="16"
                :side-flip="false"
                :align-flip="false"
                aria-label="Filter transaksi"
                class="admin-dashboard-theme z-50 flex max-h-[min(640px,var(--reka-popover-content-available-height))] w-[360px] max-w-[calc(100vw-32px)] flex-col overflow-hidden rounded-xl border border-border bg-card text-foreground shadow-lg outline-none"
            >
                <header
                    class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-5 py-4"
                >
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <SlidersHorizontal class="size-4 text-primary" />Filter
                        transaksi
                    </h2>
                    <PopoverClose as-child>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Tutup filter"
                            ><X
                        /></Button>
                    </PopoverClose>
                </header>
                <form
                    class="flex min-h-0 flex-col"
                    @submit.prevent="emit('apply')"
                >
                    <div class="min-h-0 space-y-4 overflow-y-auto p-5">
                        <FarmField
                            :id="`${idPrefix}-filter-farm`"
                            label="Lahan"
                            :error="errors.farm_id"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="farmId"
                                class="admin-field"
                                :disabled="processing"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value=""
                                    >Semua lahan</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="farm in farms"
                                    :key="farm.id"
                                    :value="farm.id"
                                    >{{ farm.name }}</AppSelectOption
                                >
                            </AppSelect>
                        </FarmField>

                        <FarmField
                            :id="`${idPrefix}-filter-block`"
                            label="Blok"
                            :error="errors.block_id"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="blockId"
                                class="admin-field"
                                :disabled="processing || !farmId"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value="">{{
                                    farmId
                                        ? 'Semua blok'
                                        : 'Pilih lahan terlebih dahulu'
                                }}</AppSelectOption>
                                <AppSelectOption
                                    v-for="block in blockOptions"
                                    :key="block.id"
                                    :value="block.id"
                                    >{{ block.name }}</AppSelectOption
                                >
                            </AppSelect>
                        </FarmField>

                        <FarmField
                            :id="`${idPrefix}-filter-variety`"
                            label="Varietas"
                            :error="errors.variety_id"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="varietyId"
                                class="admin-field"
                                :disabled="processing"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value=""
                                    >Semua varietas</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="variety in varieties"
                                    :key="variety.id"
                                    :value="variety.id"
                                    >{{ varietyLabel(variety) }}</AppSelectOption
                                >
                            </AppSelect>
                        </FarmField>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <FarmField
                                :id="`${idPrefix}-filter-from`"
                                label="Dari tanggal"
                                :error="errors.from_date"
                                v-slot="field"
                            >
                                <input
                                    :id="field.id"
                                    v-model="fromDate"
                                    type="date"
                                    class="admin-field"
                                    :disabled="processing"
                                    :aria-invalid="field.invalid"
                                    :aria-describedby="field.description"
                                />
                            </FarmField>

                            <FarmField
                                :id="`${idPrefix}-filter-to`"
                                label="Sampai tanggal"
                                :error="errors.to_date"
                                v-slot="field"
                            >
                                <input
                                    :id="field.id"
                                    v-model="toDate"
                                    type="date"
                                    class="admin-field"
                                    :disabled="processing"
                                    :aria-invalid="field.invalid"
                                    :aria-describedby="field.description"
                                />
                            </FarmField>
                        </div>
                    </div>

                    <footer
                        class="flex shrink-0 items-center justify-between gap-2 border-t border-border bg-card px-5 py-3.5"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            :disabled="processing"
                            @click="emit('reset')"
                        >
                            Reset
                        </Button>
                        <Button
                            type="submit"
                            class="h-10 rounded-xl"
                            :disabled="processing"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="animate-spin motion-reduce:animate-none"
                            />Terapkan filter
                        </Button>
                    </footer>
                </form>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
