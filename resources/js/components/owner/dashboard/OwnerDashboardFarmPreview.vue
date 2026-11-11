<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, MapPin, Sprout, UserRound, X } from '@lucide/vue';
import { computed } from 'vue';
import { withOwnerFarmOrigin } from '@/lib/ownerFarmNavigation';
import DashboardFarmMap from '@/components/dashboard/DashboardFarmMap.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { OwnerDashboardMapFarm } from '@/types/owner-dashboard';

const props = defineProps<{ farm: OwnerDashboardMapFarm }>();
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ closed: [] }>();
const mapFarms = computed(() => [props.farm]);
const hasCoordinates = computed(
    () =>
        Number.isFinite(props.farm.latitude) &&
        Number.isFinite(props.farm.longitude),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            :show-close-button="false"
            class="owner-farm-preview flex max-h-[calc(100dvh-2rem)] w-full max-w-[calc(100%-2rem)] flex-col gap-0 overflow-hidden rounded-3xl border-border bg-card p-0 text-card-foreground sm:max-w-[560px]"
            @close-auto-focus="emit('closed')"
        >
            <header class="relative shrink-0 px-5 pt-5 pr-16 pb-4">
                <DialogTitle
                    class="text-xl leading-7 font-semibold break-words"
                >
                    {{ farm.name }}
                </DialogTitle>
                <DialogDescription
                    class="mt-2 flex items-start gap-1.5 text-sm leading-5 text-muted-foreground"
                >
                    <MapPin class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <span class="min-w-0 break-words"
                        >{{ farm.location }} · {{ farm.area }} ha</span
                    >
                </DialogDescription>
                <DialogClose
                    class="absolute top-4 right-4 flex size-11 items-center justify-center rounded-full bg-muted text-foreground transition-colors hover:bg-muted/70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    aria-label="Tutup ringkasan kebun"
                >
                    <X class="size-5" aria-hidden="true" />
                </DialogClose>
            </header>

            <div
                class="owner-preview-body min-h-0 overflow-y-auto overscroll-contain focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                role="region"
                aria-label="Informasi lahan"
                tabindex="0"
            >
                <DashboardFarmMap
                    v-if="hasCoordinates"
                    :key="farm.id"
                    :farms="mapFarms"
                    fit-on-resize
                    class="owner-preview-map mt-0! rounded-none! border-x-0!"
                />
                <div
                    v-else
                    role="status"
                    class="flex min-h-40 items-center justify-center gap-2 bg-muted/40 px-5 py-8 text-center text-sm leading-6 text-muted-foreground"
                >
                    <MapPin class="size-5 shrink-0" aria-hidden="true" />
                    Koordinat lokasi lahan belum tercatat.
                </div>

                <div class="space-y-4 p-5">
                    <section aria-label="Jenis tanaman">
                        <h3
                            class="flex items-center gap-2 text-base font-semibold"
                        >
                            <Sprout
                                class="size-5 text-secondary dark:text-primary"
                                aria-hidden="true"
                            />
                            Jenis tanaman
                        </h3>
                        <ul v-if="farm.crops.length" class="mt-3 space-y-2">
                            <li
                                v-for="crop in farm.crops"
                                :key="crop.id"
                                class="rounded-xl border border-border bg-muted/20 px-3 py-2.5"
                            >
                                <p class="text-sm leading-5 font-medium">
                                    {{ crop.name }}
                                </p>
                                <p
                                    v-if="crop.varieties.length"
                                    class="mt-1 text-xs leading-5 text-muted-foreground"
                                >
                                    {{ crop.varieties.join(', ') }}
                                </p>
                            </li>
                        </ul>
                        <p
                            v-else
                            class="mt-2 text-sm leading-6 text-muted-foreground"
                        >
                            Jenis tanaman belum tercatat.
                        </p>
                    </section>

                    <slot name="summary"
                        ><section
                            class="rounded-xl bg-muted/60 p-4"
                            aria-label="PIC yang ditugaskan"
                        >
                            <h3
                                class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                            >
                                <UserRound
                                    class="size-4 shrink-0 text-secondary dark:text-primary"
                                    aria-hidden="true"
                                />
                                PIC yang ditugaskan
                            </h3>
                            <p
                                v-if="farm.pic"
                                class="mt-2 text-base leading-6 font-semibold break-words"
                            >
                                {{ farm.pic.name }}
                            </p>
                            <p
                                v-else
                                class="mt-2 text-sm leading-6 text-muted-foreground"
                            >
                                Belum ada PIC ditugaskan.
                            </p>
                        </section></slot
                    >
                </div>
            </div>

            <footer class="shrink-0 border-t border-border bg-card p-4 sm:p-5">
                <slot name="footer"
                    ><Link
                        :href="withOwnerFarmOrigin(farm.detail_url, 'home')"
                        class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        @click="open = false"
                    >
                        Detail kebun
                        <ArrowRight class="size-4" aria-hidden="true" /> </Link
                ></slot>
            </footer>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.owner-preview-map {
    height: clamp(10rem, 30dvh, 14rem) !important;
}

@media (min-width: 640px) {
    .owner-preview-map {
        height: clamp(10rem, 35dvh, 18rem) !important;
    }
}
</style>
