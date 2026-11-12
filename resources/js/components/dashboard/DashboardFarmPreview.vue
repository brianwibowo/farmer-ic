<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { ArrowRight, LandPlot, MapPin, Sprout, UserRound, X } from "@lucide/vue";
import { computed } from "vue";
import DashboardFarmMap from "@/components/dashboard/DashboardFarmMap.vue";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from "@/components/ui/dialog";
import type { DashboardMapFarmPreview } from "@/types/dashboard";

const props = defineProps<{
    farm: DashboardMapFarmPreview;
    detailUrl?: string;
    adminTheme?: boolean;
}>();
const open = defineModel<boolean>("open", { default: false });
const emit = defineEmits<{ closed: [] }>();
const mapFarms = computed(() => [props.farm]);
const picInitials = computed(() =>
    props.farm.pic?.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase("id-ID"),
);
const hasCoordinates = computed(
    () => Number.isFinite(props.farm.latitude) && Number.isFinite(props.farm.longitude),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            :show-close-button="false"
            :class="adminTheme ? 'admin-dashboard-theme font-sans' : undefined"
            class="owner-farm-preview flex max-h-[calc(100dvh-2rem)] w-full max-w-[calc(100%-2rem)] flex-col gap-0 overflow-hidden rounded-xl border-border bg-card p-0 text-card-foreground sm:max-w-[560px]"
            @close-auto-focus="emit('closed')"
        >
            <header class="flex shrink-0 items-start gap-3 px-4 pt-4 pb-4 sm:px-5 sm:pt-5">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-secondary dark:text-primary"
                >
                    <LandPlot class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0 flex-1">
                    <DialogTitle class="text-xl leading-7 font-semibold [overflow-wrap:anywhere]">
                        {{ farm.name }}
                    </DialogTitle>
                    <DialogDescription
                        class="mt-1 flex items-start gap-1.5 text-sm leading-5 text-muted-foreground"
                    >
                        <MapPin class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                        <span class="min-w-0 [overflow-wrap:anywhere]">{{ farm.location }}</span>
                    </DialogDescription>
                </div>
                <Button
                    as-child
                    variant="ghost"
                    size="icon"
                    class="size-11 rounded-xl text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <DialogClose aria-label="Tutup ringkasan kebun">
                        <X class="size-5" aria-hidden="true" />
                    </DialogClose>
                </Button>
            </header>

            <div
                class="owner-preview-body min-h-0 space-y-5 overflow-y-auto overscroll-contain px-4 pb-5 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring sm:px-5"
                role="region"
                aria-label="Informasi lahan"
                tabindex="0"
            >
                <div class="overflow-hidden rounded-xl border border-border">
                    <DashboardFarmMap
                        v-if="hasCoordinates"
                        :key="farm.id"
                        :farms="mapFarms"
                        :single-farm-zoom="18"
                        fit-on-resize
                        class="owner-preview-map mt-0! rounded-none! border-0!"
                    />
                    <div
                        v-else
                        role="status"
                        class="flex min-h-40 items-center justify-center gap-2 bg-muted/40 px-4 py-8 text-center text-sm leading-6 text-muted-foreground"
                    >
                        <MapPin class="size-5 shrink-0" aria-hidden="true" />
                        Koordinat lokasi lahan belum tercatat.
                    </div>
                </div>

                <section aria-label="Jenis tanaman">
                    <h3 class="flex items-center gap-2 text-sm font-semibold">
                        <Sprout
                            class="size-4 text-secondary dark:text-primary"
                            aria-hidden="true"
                        />
                        Jenis tanaman
                    </h3>
                    <ul v-if="farm.crops.length" class="mt-2 divide-y divide-border">
                        <li
                            v-for="crop in farm.crops"
                            :key="crop.id"
                            class="py-3 first:pt-1 last:pb-0"
                        >
                            <p class="text-base leading-6 font-semibold [overflow-wrap:anywhere]">
                                {{ crop.name }}
                            </p>
                            <p
                                v-if="crop.varieties.length"
                                class="mt-1 text-sm leading-5 text-muted-foreground [overflow-wrap:anywhere]"
                            >
                                {{ crop.varieties.join(", ") }}
                            </p>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm leading-6 text-muted-foreground">
                        Jenis tanaman belum tercatat.
                    </p>
                </section>

                <slot name="summary">
                    <section
                        class="flex items-center gap-3 rounded-xl border border-border bg-muted/30 p-3.5"
                        aria-label="PIC yang ditugaskan"
                    >
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-secondary dark:text-primary"
                            aria-hidden="true"
                        >
                            <template v-if="farm.pic">{{ picInitials }}</template>
                            <UserRound v-else class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-xs leading-5 text-muted-foreground">
                                PIC yang ditugaskan
                            </h3>
                            <p
                                v-if="farm.pic"
                                class="mt-0.5 text-base leading-6 font-semibold [overflow-wrap:anywhere]"
                            >
                                {{ farm.pic.name }}
                            </p>
                            <p v-else class="mt-0.5 text-sm leading-6 text-muted-foreground">
                                Belum ada PIC ditugaskan.
                            </p>
                        </div>
                    </section>
                </slot>
            </div>

            <footer class="shrink-0 border-0 bg-transparent p-4 shadow-none sm:px-5">
                <slot name="footer">
                    <Button
                        as-child
                        variant="secondary"
                        size="lg"
                        class="min-h-12 w-full justify-between rounded-xl px-4 text-sm font-semibold"
                    >
                        <Link :href="detailUrl ?? farm.detail_url" @click="open = false">
                            Detail kebun
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                    </Button>
                </slot>
            </footer>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.owner-preview-map {
    height: clamp(10rem, 28dvh, 14rem) !important;
}

@media (min-width: 640px) {
    .owner-preview-map {
        height: clamp(10rem, 30dvh, 16rem) !important;
    }
}
</style>
