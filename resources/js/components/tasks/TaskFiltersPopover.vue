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
import {
    adminTaskCategoryOptions,
    adminTaskStatusOptions,
    type AdminTaskBlockOption,
    type AdminTaskFarmOption,
    type AdminTaskFilters,
    type AdminTaskSubblokOption,
} from '@/types/tasks';

const props = defineProps<{
    farms: AdminTaskFarmOption[];
    blocks: AdminTaskBlockOption[];
    subbloks: AdminTaskSubblokOption[];
    processing: boolean;
    count: number;
    errors: Partial<Record<keyof AdminTaskFilters, string>>;
}>();

const open = defineModel<boolean>('open', { required: true });
const farmId = defineModel<string>('farmId', { required: true });
const blockId = defineModel<string>('blockId', { required: true });
const subblokId = defineModel<string>('subblokId', { required: true });
const status = defineModel<string>('status', { required: true });
const category = defineModel<string>('category', { required: true });

const emit = defineEmits<{
    apply: [];
    reset: [];
    statusChange: [];
}>();

const blockOptions = computed(() =>
    farmId.value
        ? props.blocks.filter((block) => block.farm_id === farmId.value)
        : [],
);

const subblokOptions = computed(() =>
    blockId.value
        ? props.subbloks.filter((subblok) => subblok.block_id === blockId.value)
        : [],
);

watch(farmId, (next, prev) => {
    if (next !== prev) {
        if (
            blockId.value &&
            !blockOptions.value.some((block) => block.id === blockId.value)
        ) {
            blockId.value = '';
            subblokId.value = '';
        }
        if (!next) {
            blockId.value = '';
            subblokId.value = '';
        }
    }
});

watch(blockId, (next, prev) => {
    if (next !== prev) {
        if (
            subblokId.value &&
            !subblokOptions.value.some(
                (subblok) => subblok.id === subblokId.value,
            )
        ) {
            subblokId.value = '';
        }
        if (!next) {
            subblokId.value = '';
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
                aria-label="Filter tugas"
                title="Filter tugas"
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
                aria-label="Filter tugas"
                class="admin-dashboard-theme z-50 flex max-h-[min(640px,var(--reka-popover-content-available-height))] w-[360px] max-w-[calc(100vw-32px)] flex-col overflow-hidden rounded-xl border border-border bg-card text-foreground shadow-lg outline-none"
            >
                <header
                    class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-5 py-4"
                >
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <SlidersHorizontal class="size-4 text-primary" />Filter
                        tugas
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
                            id="task-filter-farm"
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
                            id="task-filter-block"
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
                                    >{{ block.code }} · {{ block.name }}</AppSelectOption
                                >
                            </AppSelect>
                        </FarmField>

                        <FarmField
                            id="task-filter-subblok"
                            label="Subblok"
                            :error="errors.subblok_id"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="subblokId"
                                class="admin-field"
                                :disabled="processing || !blockId"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value="">{{
                                    blockId
                                        ? 'Semua subblok'
                                        : farmId
                                          ? 'Pilih blok terlebih dahulu'
                                          : 'Pilih lahan terlebih dahulu'
                                }}</AppSelectOption>
                                <AppSelectOption
                                    v-for="subblok in subblokOptions"
                                    :key="subblok.id"
                                    :value="subblok.id"
                                    >{{
                                        subblok.label ??
                                        `Subblok ${subblok.number}`
                                    }}</AppSelectOption
                                >
                            </AppSelect>
                        </FarmField>

                        <FarmField
                            id="task-filter-status"
                            label="Status"
                            :error="errors.status"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="status"
                                class="admin-field"
                                :disabled="processing"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                                @change="emit('statusChange')"
                            >
                                <AppSelectOption value=""
                                    >Semua status</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="[
                                        value,
                                        label,
                                    ] in adminTaskStatusOptions"
                                    :key="value"
                                    :value="value"
                                    >{{ label }}</AppSelectOption
                                >
                            </AppSelect>
                        </FarmField>

                        <FarmField
                            id="task-filter-category"
                            label="Kategori"
                            :error="errors.category"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="category"
                                class="admin-field"
                                :disabled="processing"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value=""
                                    >Semua kategori</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="[
                                        value,
                                        label,
                                    ] in adminTaskCategoryOptions"
                                    :key="value"
                                    :value="value"
                                    >{{ label }}</AppSelectOption
                                >
                            </AppSelect>
                        </FarmField>
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
