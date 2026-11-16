<script setup lang="ts">
import { ArrowUpRight, Check, Circle, LoaderCircle, Sprout } from "@lucide/vue";
import { computed, ref, useSlots } from "vue";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { blockDate, stageLabels, type BatchGrowth, type BatchStage } from "@/types/blocks";
const props = withDefaults(
    defineProps<{
        stages: BatchStage[];
        growth?: BatchGrowth;
        theme?: string;
        growthContext?: string;
    }>(),
    { theme: "admin-dashboard-theme" },
);
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
const growthOpen = ref(false);
</script>
<template>
    <div class="space-y-5">
        <div role="group" aria-label="Tahap dan tanggal progres" class="grid gap-3 xl:grid-cols-2">
            <ol
                v-for="group in stageGroups"
                :key="group.key"
                :aria-label="group.label"
                class="grid min-w-0 grid-cols-2 gap-3 rounded-xl p-3 sm:p-4"
                :class="
                    group.key === 'preparation'
                        ? 'bg-stage-preparation sm:grid-cols-3'
                        : 'bg-stage-cultivation sm:grid-cols-3'
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
                </template>
            </ol>
        </div>
        <Dialog v-if="growth" v-model:open="growthOpen">
            <div class="space-y-2">
                <DialogTrigger as-child>
                    <Button
                        type="button"
                        class="min-h-12 w-full gap-2 rounded-xl bg-stage-action px-4 whitespace-normal text-white hover:bg-stage-action/90 active:bg-stage-action/80"
                        :disabled="!growth.available || !slots.growth"
                    >
                        <Sprout class="size-4 shrink-0" aria-hidden="true" />
                        Pengamatan Perkembangan tanaman
                        <ArrowUpRight class="size-4 shrink-0" aria-hidden="true" />
                    </Button>
                </DialogTrigger>
                <p v-if="!growth.available" class="text-xs text-muted-foreground">
                    Belum memasuki pengamatan
                </p>
            </div>
            <DialogContent
                v-if="growth.available && slots.growth"
                :class="theme"
                class="flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden rounded-[20px] bg-card p-0 text-foreground sm:max-w-5xl"
            >
                <DialogHeader class="sr-only">
                    <DialogTitle class="text-xl leading-7 font-semibold"
                        >Pengamatan Perkembangan tanaman</DialogTitle
                    >
                    <DialogDescription class="text-sm text-muted-foreground"
                        >{{ growthContext || growth.commodity || "Perkembangan subblok"
                        }}<template v-if="growth.hst !== null">
                            · {{ growth.hst }} HST</template
                        ></DialogDescription
                    >
                </DialogHeader>
                <div class="min-h-0 overflow-y-auto overscroll-contain [&>section>header]:pr-8">
                    <slot name="growth" />
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
