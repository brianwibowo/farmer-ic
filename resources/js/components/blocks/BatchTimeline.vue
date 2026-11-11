<script setup lang="ts">
import { Check, ChevronDown, Circle, LoaderCircle, Sprout } from '@lucide/vue';
import { ref, useId, useSlots } from 'vue';
import {
    blockDate,
    stageLabels,
    type BatchGrowth,
    type BatchStage,
} from '@/types/blocks';
defineProps<{ stages: BatchStage[]; growth?: BatchGrowth }>();
const slots = useSlots();
const growthExpanded = ref(false);
const growthId = useId();
</script>
<template>
    <div class="space-y-5">
        <ol
            aria-label="Tahap dan tanggal progres"
            class="grid grid-cols-2 gap-x-4 gap-y-4 sm:grid-cols-3"
            :class="growth ? 'xl:grid-cols-7' : 'xl:grid-cols-6'"
        >
            <template v-for="stage in stages" :key="stage.code">
                <li
                    class="min-w-0 border-t-2 pt-3"
                    :class="
                        stage.status === 'completed'
                            ? 'border-success'
                            : stage.status === 'in_progress'
                              ? 'border-primary'
                              : 'border-border'
                    "
                    :aria-current="
                        stage.status === 'in_progress' ? 'step' : undefined
                    "
                >
                    <div
                        class="mb-2 flex items-center gap-2"
                        :class="
                            stage.status === 'completed'
                                ? 'text-success'
                                : stage.status === 'in_progress'
                                  ? 'text-primary'
                                  : 'text-muted-foreground'
                        "
                    >
                        <Check
                            v-if="stage.status === 'completed'"
                            class="size-4 shrink-0"
                        /><LoaderCircle
                            v-else-if="stage.status === 'in_progress'"
                            class="size-4 shrink-0"
                        /><Circle v-else class="size-4 shrink-0" />
                        <span
                            v-if="stage.status === 'in_progress'"
                            class="text-xs font-semibold"
                            >Sekarang</span
                        >
                        <span v-else class="text-xs">{{
                            stage.status === 'completed'
                                ? 'Selesai'
                                : 'Belum dimulai'
                        }}</span>
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-sm"
                            :class="
                                stage.status === 'in_progress'
                                    ? 'font-semibold'
                                    : 'font-medium'
                            "
                        >
                            {{
                                stage.code === 'land_clearing'
                                    ? 'Pembersihan lahan'
                                    : (stageLabels[stage.code] ?? stage.code)
                            }}
                        </p>
                        <p
                            v-if="stage.started_at || stage.completed_at"
                            class="mt-1 text-xs leading-relaxed text-muted-foreground"
                        >
                            {{
                                stage.started_at
                                    ? `Mulai ${blockDate(stage.started_at)}`
                                    : 'Tanggal belum tersedia'
                            }}<span v-if="stage.completed_at" class="block"
                                >Selesai
                                {{ blockDate(stage.completed_at) }}</span
                            >
                        </p>
                    </div>
                </li>
                <li
                    v-if="stage.code === 'planting' && growth"
                    class="min-w-0 border-t-2 pt-3"
                    :class="
                        growth.available ? 'border-primary' : 'border-border'
                    "
                >
                    <button
                        type="button"
                        class="flex min-h-11 w-full flex-col items-start rounded-lg text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        :aria-expanded="growthExpanded"
                        :aria-controls="growth.available ? growthId : undefined"
                        :disabled="!growth.available"
                        @click="growthExpanded = !growthExpanded"
                    >
                        <span
                            class="mb-2 flex items-center gap-2 text-xs font-semibold"
                            :class="
                                growth.available
                                    ? 'text-primary'
                                    : 'text-muted-foreground'
                            "
                            ><Sprout
                                class="size-4 shrink-0"
                                aria-hidden="true" />Pengamatan<ChevronDown
                                v-if="growth.available"
                                class="hidden size-3.5 shrink-0 transition-transform sm:block"
                                :class="{ 'rotate-180': growthExpanded }"
                                aria-hidden="true"
                        /></span>
                        <span
                            class="max-w-full text-sm font-semibold break-words"
                            :class="
                                growth.available
                                    ? 'text-primary'
                                    : 'text-muted-foreground'
                            "
                            >Perkembangan tanaman</span
                        >
                        <span
                            class="mt-1 text-xs leading-relaxed text-muted-foreground"
                            >{{
                                growth.available
                                    ? `${growth.hst} HST`
                                    : 'Belum memasuki pengamatan'
                            }}</span
                        >
                    </button>
                </li>
            </template>
        </ol>
        <div v-if="slots.growth" v-show="growthExpanded" :id="growthId">
            <slot name="growth" />
        </div>
    </div>
</template>
