<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { LandPlot, Layers, Sprout } from '@lucide/vue';
import GardenFarmCard from '@/components/petugas/GardenFarmCard.vue';
import GardenBatchCard from '@/components/petugas/GardenBatchCard.vue';
import { Button } from '@/components/ui/button';
import type { PetugasGarden } from '@/types/petugas-garden';
import { gardenArea, gardenDate } from '@/types/petugas-garden';

defineOptions({ inheritAttrs: false });
defineProps<{ garden: PetugasGarden }>();
</script>

<template>
    <Head title="Kebun · Petugas" />
    <div class="space-y-5">
        <h1 class="text-2xl leading-8 font-semibold tracking-tight">
            Kebun saya
        </h1>
        <section
            v-if="!garden.farm"
            class="rounded-xl border border-dashed border-border bg-card px-5 py-9 text-center"
        >
            <LandPlot
                class="mx-auto size-9 text-secondary dark:text-primary"
                aria-hidden="true"
            />
            <h2 class="mt-3 text-base font-semibold">
                Belum ada kebun penugasan
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Hubungi admin untuk penugasan kebun.
            </p>
        </section>
        <template v-else>
            <GardenFarmCard :farm="garden.farm" :summary="garden.summary" />
            <section aria-labelledby="garden-blocks" class="space-y-4">
                <h2 id="garden-blocks" class="text-lg font-semibold">
                    Blok & subblok
                </h2>
                <div
                    v-if="!garden.blocks.length"
                    class="rounded-xl border border-dashed border-border bg-card px-5 py-8 text-center"
                >
                    <Layers
                        class="mx-auto size-8 text-secondary dark:text-primary"
                        aria-hidden="true"
                    />
                    <p class="mt-3 text-sm font-medium">
                        Belum ada blok pada kebun ini
                    </p>
                </div>
                <template v-else>
                    <nav
                        aria-label="Pilih blok kebun"
                        class="flex gap-2 overflow-x-auto pb-1"
                    >
                        <Link
                            v-for="block in garden.blocks"
                            :key="block.id"
                            :href="`/petugas/kebun?block=${encodeURIComponent(block.id)}`"
                            preserve-scroll
                            class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                            :class="
                                block.id === garden.selected_block?.id
                                    ? 'border-primary/40 bg-primary/10 text-secondary dark:text-primary'
                                    : 'border-border bg-card text-muted-foreground hover:bg-muted'
                            "
                            :aria-current="
                                block.id === garden.selected_block?.id
                                    ? 'page'
                                    : undefined
                            "
                            :aria-label="`${block.code}, ${block.name}, ${block.batches_count} subblok${block.is_active ? '' : ', nonaktif'}`"
                            >{{ block.code
                            }}<span
                                class="rounded-full bg-muted px-1.5 py-0.5 text-[11px] text-foreground"
                                >{{ block.batches_count }}</span
                            ></Link
                        >
                    </nav>
                    <template v-if="garden.selected_block">
                        <div class="space-y-3 rounded-xl bg-muted/40 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3
                                        class="text-base leading-6 font-semibold break-words"
                                    >
                                        {{ garden.selected_block.name }}
                                    </h3>
                                    <span
                                        v-if="!garden.selected_block.is_active"
                                        class="mt-1 inline-block text-xs text-muted-foreground"
                                        >Nonaktif</span
                                    >
                                </div>
                                <p
                                    class="shrink-0 text-lg font-semibold tabular-nums"
                                >
                                    {{
                                        gardenArea(
                                            garden.selected_block.area_ha,
                                        )
                                    }}
                                    <span
                                        class="text-xs font-normal text-muted-foreground"
                                        >ha</span
                                    >
                                </p>
                            </div>
                            <p
                                v-if="garden.selected_block.description"
                                class="text-xs leading-5 text-muted-foreground"
                            >
                                {{ garden.selected_block.description }}
                            </p>
                            <p
                                class="text-xs leading-5 text-secondary dark:text-primary"
                            >
                                Land clearing ·
                                {{
                                    garden.selected_block
                                        .land_clearing_completed_at
                                        ? `Selesai ${gardenDate(garden.selected_block.land_clearing_completed_at)}`
                                        : `Dimulai ${gardenDate(garden.selected_block.land_clearing_started_at)}`
                                }}
                            </p>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-base font-semibold">Subblok</h3>
                            <span class="text-xs text-muted-foreground"
                                >{{ garden.batches.total }} batch</span
                            >
                        </div>
                        <div
                            v-if="!garden.batches.total"
                            class="rounded-xl border border-dashed border-border bg-card px-5 py-8 text-center"
                        >
                            <Sprout
                                class="mx-auto size-8 text-secondary dark:text-primary"
                                aria-hidden="true"
                            />
                            <p class="mt-3 text-sm font-medium">
                                Belum ada subblok pada blok ini
                            </p>
                        </div>
                        <ul
                            v-else
                            class="space-y-3"
                            aria-label="Subblok dan batch pada blok terpilih"
                        >
                            <li
                                v-for="batch in garden.batches.data"
                                :key="batch.id"
                            >
                                <GardenBatchCard :batch="batch" />
                            </li>
                        </ul>
                        <nav
                            v-if="garden.batches.last_page > 1"
                            aria-label="Halaman subblok"
                            class="flex flex-wrap items-center justify-between gap-2 [&_a]:h-11 [&_a]:rounded-xl [&_button]:h-11 [&_button]:rounded-xl"
                        >
                            <Button
                                v-if="garden.batches.prev_page_url"
                                as-child
                                variant="outline"
                                size="sm"
                                ><Link
                                    :href="garden.batches.prev_page_url"
                                    preserve-scroll
                                    >Sebelumnya</Link
                                ></Button
                            ><Button v-else variant="outline" size="sm" disabled
                                >Sebelumnya</Button
                            ><span
                                class="text-xs text-muted-foreground"
                                aria-current="page"
                                >{{ garden.batches.current_page }} /
                                {{ garden.batches.last_page }}</span
                            ><Button
                                v-if="garden.batches.next_page_url"
                                as-child
                                variant="outline"
                                size="sm"
                                ><Link
                                    :href="garden.batches.next_page_url"
                                    preserve-scroll
                                    >Berikutnya</Link
                                ></Button
                            ><Button v-else variant="outline" size="sm" disabled
                                >Berikutnya</Button
                            >
                        </nav>
                    </template>
                </template>
            </section>
        </template>
    </div>
</template>
