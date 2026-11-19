<script setup lang="ts">
import { LoaderCircle, SlidersHorizontal, X } from '@lucide/vue';
import {
    PopoverClose,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import FarmField from '@/components/farms/FarmField.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Button } from '@/components/ui/button';
import type {
    LogbookIndexFilterOptions,
    LogbookIndexFilters,
} from '@/types/logbook';

defineProps<{
    options: LogbookIndexFilterOptions;
    processing: boolean;
    count: number;
    errors: Partial<Record<keyof LogbookIndexFilters, string>>;
}>();

const open = defineModel<boolean>('open', { required: true });
const farmId = defineModel<string>('farmId', { required: true });
const activityType = defineModel<string>('activityType', { required: true });
const source = defineModel<string>('source', { required: true });
const performedBy = defineModel<string>('performedBy', { required: true });
const fromDate = defineModel<string>('fromDate', { required: true });
const toDate = defineModel<string>('toDate', { required: true });

const emit = defineEmits<{
    apply: [];
    reset: [];
}>();
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :variant="count ? 'secondary' : 'outline'"
                type="button"
                class="h-10 px-2.5! sm:px-4! pointer-coarse:min-h-11"
                :disabled="processing"
                aria-label="Filter aktivitas logbook"
                title="Filter aktivitas logbook"
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
                aria-label="Filter aktivitas logbook"
                class="admin-dashboard-theme z-50 flex max-h-[min(640px,var(--reka-popover-content-available-height))] w-[380px] max-w-[calc(100vw-32px)] flex-col overflow-hidden rounded-xl border border-border bg-card text-foreground shadow-lg outline-none"
            >
                <header
                    class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-5 py-4"
                >
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <SlidersHorizontal class="size-4 text-primary" />Filter
                        aktivitas
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
                            id="logbook-activity-farm"
                            label="Kebun"
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
                                    >Semua kebun</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="farm in options.farms"
                                    :key="farm.id"
                                    :value="farm.id"
                                    >{{ farm.name }}</AppSelectOption
                                >
                            </AppSelect>
                        </FarmField>

                        <FarmField
                            id="logbook-activity-type"
                            label="Jenis aktivitas"
                            :error="errors.activity_type"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="activityType"
                                class="admin-field"
                                :disabled="processing"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value=""
                                    >Semua jenis aktivitas</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="type in options.activity_types"
                                    :key="type.id"
                                    :value="type.code"
                                    >{{ type.name }}</AppSelectOption
                                >
                            </AppSelect>
                        </FarmField>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <FarmField
                                id="logbook-activity-source"
                                label="Sumber"
                                :error="errors.source"
                                v-slot="field"
                            >
                                <AppSelect
                                    :id="field.id"
                                    v-model="source"
                                    class="admin-field"
                                    :disabled="processing"
                                    :aria-invalid="field.invalid"
                                    :aria-describedby="field.description"
                                >
                                    <AppSelectOption value=""
                                        >Semua sumber</AppSelectOption
                                    >
                                    <AppSelectOption value="sop"
                                        >Tugas SOP</AppSelectOption
                                    >
                                    <AppSelectOption value="mandiri"
                                        >Mandiri</AppSelectOption
                                    >
                                </AppSelect>
                            </FarmField>

                            <FarmField
                                id="logbook-activity-performer"
                                label="Pelaksana"
                                :error="errors.performed_by"
                                v-slot="field"
                            >
                                <AppSelect
                                    :id="field.id"
                                    v-model="performedBy"
                                    class="admin-field"
                                    :disabled="processing"
                                    :aria-invalid="field.invalid"
                                    :aria-describedby="field.description"
                                >
                                    <AppSelectOption value=""
                                        >Semua pelaksana</AppSelectOption
                                    >
                                    <AppSelectOption
                                        v-for="person in options.performers"
                                        :key="person.id"
                                        :value="person.id"
                                        >{{ person.name }}</AppSelectOption
                                    >
                                </AppSelect>
                            </FarmField>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <FarmField
                                id="logbook-activity-from"
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
                                id="logbook-activity-to"
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
