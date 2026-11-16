<script setup lang="ts">
import { Check, ChevronDown, Circle, LoaderCircle } from "@lucide/vue";
import { computed, ref, useId, useSlots } from "vue";
import { blockDate, stageLabels, type BatchGrowth, type BatchStage } from "@/types/blocks";
const props = defineProps<{ stages: BatchStage[]; growth?: BatchGrowth }>();
const preparationCodes = ["land_clearing", "soil_preparation", "basic_fertilizer"];
const stageGroups = computed(() => [
    {
        key: "preparation",
        label: "Tahapan pra-tanam",
        stages: props.stages.filter((stage) => preparationCodes.includes(stage.code)),
    },
    {
        key: "cultivation",
        label: "Tahapan penanaman sampai panen",
        stages: props.stages.filter((stage) => !preparationCodes.includes(stage.code)),
    },
]);
const slots = useSlots();
const growthExpanded = ref(false);
const growthId = useId();
</script>
<template>
    <div class="space-y-5">
        <div
            role="group"
            aria-label="Tahap dan tanggal progres"
            class="grid gap-3"
            :class="growth ? 'xl:grid-cols-[minmax(0,3fr)_minmax(0,4fr)]' : 'xl:grid-cols-2'"
        >
            <ol
                v-for="group in stageGroups"
                :key="group.key"
                :aria-label="group.label"
                class="grid min-w-0 grid-cols-2 gap-3 rounded-xl p-3 sm:p-4"
                :class="
                    group.key === 'preparation'
                        ? 'bg-stage-preparation sm:grid-cols-3'
                        : ['bg-stage-cultivation', growth ? 'xl:grid-cols-4' : 'sm:grid-cols-3']
                "
            >
                <template v-for="stage in group.stages" :key="stage.code">
                    <li
                        class="min-w-0 border-t-2 pt-3"
                        :class="
                            stage.status === 'completed'
                                ? 'border-success'
                                : stage.status === 'in_progress'
                                  ? 'border-primary'
                                  : 'border-border'
                        "
                        :aria-current="stage.status === 'in_progress' ? 'step' : undefined"
                    >
                        <div
                            class="mb-2 flex flex-wrap items-center gap-2"
                            :class="
                                stage.status === 'completed'
                                    ? 'text-stage-completed'
                                    : stage.status === 'in_progress'
                                      ? 'text-stage-current'
                                      : 'text-stage-upcoming'
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
                                stage.status === "completed" ? "Selesai" : "Belum dimulai"
                            }}</span>
                        </div>
                        <div class="min-w-0">
                            <p
                                class="text-sm"
                                :class="
                                    stage.status === 'in_progress' ? 'font-semibold' : 'font-medium'
                                "
                            >
                                {{
                                    stage.code === "land_clearing"
                                        ? "Pembersihan lahan"
                                        : (stageLabels[stage.code] ?? stage.code)
                                }}
                            </p>
                            <p
                                v-if="stage.started_at || stage.completed_at"
                                class="mt-1 text-xs leading-relaxed text-muted-foreground"
                            >
                                {{
                                    stage.started_at
                                        ? `${stage.status === "in_progress" ? "Laporan Pertama" : "Mulai"} ${blockDate(stage.started_at)}`
                                        : "Tanggal belum tersedia"
                                }}<span v-if="stage.completed_at" class="block"
                                    >{{
                                        stage.status === "completed"
                                            ? "Selesai"
                                            : "Laporan Terakhir"
                                    }}
                                    {{ blockDate(stage.completed_at) }}</span
                                >
                            </p>
                            <p
                                v-else-if="stage.status === 'completed'"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Tidak ada laporan pada tahap ini
                            </p>
                        </div>
                    </li>
                    <li
                        v-if="stage.code === 'planting' && growth"
                        class="min-w-0 border-t-2 pt-3"
                        :class="growth.available ? 'border-primary' : 'border-border'"
                    >
                        <button
                            type="button"
                            class="flex min-h-11 w-full flex-col items-start rounded-lg bg-transparent text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed"
                            :aria-expanded="growthExpanded"
                            :aria-controls="growth.available ? growthId : undefined"
                            :disabled="!growth.available"
                            @click="growthExpanded = !growthExpanded"
                        >
                            <span
                                class="mb-2 flex w-full items-center justify-between gap-1 text-xs font-semibold"
                                :class="growth.available ? 'text-info' : 'text-muted-foreground'"
                                ><span class="min-w-0 break-words">Pengamatan</span><ChevronDown
                                    v-if="growth.available"
                                    class="hidden size-3.5 shrink-0 text-stage-growth transition-transform sm:block"
                                    :class="{ 'rotate-180': growthExpanded }"
                                    aria-hidden="true"
                            /></span>
                            <span
                                class="max-w-full text-sm font-semibold break-words text-stage-growth"
                                >Perkembangan tanaman</span
                            >
                            <span class="mt-1 text-xs leading-relaxed text-muted-foreground">{{
                                growth.available ? `${growth.hst} HST` : "Belum memasuki pengamatan"
                            }}</span>
                        </button>
                    </li>
                </template>
            </ol>
        </div>
        <div v-if="slots.growth" v-show="growthExpanded" :id="growthId">
            <slot name="growth" />
        </div>
    </div>
</template>
