<script setup lang="ts">
import InertiaPagination from '@/components/InertiaPagination.vue';
import { Head, Link } from '@inertiajs/vue3';
import { LandPlot, Layers, Plus, Sprout } from '@lucide/vue';
import { ref } from 'vue';
import GardenFarmCard from '@/components/petugas/GardenFarmCard.vue';
import GardenBatchCard from '@/components/petugas/GardenBatchCard.vue';
import SubblokCreateDialog from '@/components/petugas/SubblokCreateDialog.vue';
import FarmCycleSummary from '@/components/blocks/FarmCycleSummary.vue';
import { Button } from '@/components/ui/button';
import type { PetugasGarden } from '@/types/petugas-garden';
import { gardenArea, gardenDate } from '@/types/petugas-garden';

defineOptions({ inheritAttrs: false });
defineProps<{ garden: PetugasGarden }>();
const createOpen = ref(false);
</script>

<template>
    <Head title="Lahan · Petugas" />
    <div class="space-y-[var(--admin-content-gap)]">
        <h1 class="text-2xl leading-8 font-semibold tracking-tight">
            Lahan saya
        </h1>
        <section
            v-if="!garden.farm"
            class="petugas-card petugas-empty px-5 py-9 text-center"
        >
            <LandPlot
                class="mx-auto size-9 text-secondary dark:text-primary"
                aria-hidden="true"
            />
            <h2 class="mt-3 text-base font-semibold">
                Belum ada lahan penugasan
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Hubungi admin untuk penugasan lahan.
            </p>
        </section>
        <template v-else>
            <FarmCycleSummary />
            <GardenFarmCard :farm="garden.farm" :summary="garden.summary" />
            <section aria-labelledby="garden-blocks" class="space-y-4">
                <h2 id="garden-blocks" class="admin-section-title">
                    Blok & subblok
                </h2>
                <div
                    v-if="!garden.blocks.length"
                    class="petugas-card petugas-empty px-5 py-8 text-center"
                >
                    <Layers
                        class="mx-auto size-8 text-secondary dark:text-primary"
                        aria-hidden="true"
                    />
                    <p class="mt-3 text-sm font-medium">
                        Belum ada blok pada lahan ini
                    </p>
                </div>
                <template v-else>
                    <nav
                        aria-label="Pilih blok lahan"
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
                            :aria-label="`${block.name}, ${block.batches_count} subblok${block.is_active ? '' : ', nonaktif'}`"
                            >{{ block.name
                            }}<span
                                class="rounded-full bg-muted px-1.5 py-0.5 text-[11px] text-foreground"
                                >{{ block.batches_count }}</span
                            ></Link
                        >
                    </nav>
                    <template v-if="garden.selected_block">
                        <div class="petugas-card space-y-3 p-4">
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
                                    class="shrink-0 text-xl font-bold tabular-nums"
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
                                        ? `Laporan terakhir ${gardenDate(garden.selected_block.land_clearing_completed_at)}`
                                        : 'Belum ada laporan'
                                }}
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <h3 class="text-base font-semibold">Subblok</h3>
                                <p class="text-xs text-muted-foreground">
                                    {{ garden.batches.total }} batch · Sisa
                                    lahan
                                    {{
                                        gardenArea(
                                            garden.selected_block.available_ha,
                                        )
                                    }}
                                    ha
                                </p>
                            </div>
                            <Button
                                v-if="
                                    garden.selected_block.can_create_batch &&
                                    garden.selected_block.is_active
                                "
                                type="button"
                                variant="outline"
                                size="sm"
                                class="min-h-11 rounded-xl"
                                :disabled="
                                    Number(
                                        garden.selected_block.available_ha,
                                    ) <= 0
                                "
                                :aria-label="`Tambah subblok di ${garden.selected_block.name}`"
                                @click="createOpen = true"
                            >
                                <Plus class="size-4" aria-hidden="true" />
                                Tambah subblok
                            </Button>
                        </div>
                        <p
                            v-if="!garden.selected_block.is_active"
                            class="text-xs text-muted-foreground"
                        >
                            Blok nonaktif. Subblok baru tidak dapat ditambahkan.
                        </p>
                        <p
                            v-else-if="
                                garden.selected_block.can_create_batch &&
                                Number(garden.selected_block.available_ha) <= 0
                            "
                            class="text-xs text-muted-foreground"
                        >
                            Kapasitas blok sudah penuh. Subblok baru dapat
                            ditambahkan setelah siklus tanam berjalan selesai.
                        </p>
                        <div
                            v-if="!garden.batches.total"
                            class="petugas-card petugas-empty px-5 py-8 text-center"
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
                        <InertiaPagination
                            v-if="garden.batches.last_page > 1"
                            :paginator="garden.batches"
                            label="Pagination subblok"
                            class="ml-auto justify-end"
                        />
                        <SubblokCreateDialog
                            v-model:open="createOpen"
                            :blocks="[garden.selected_block]"
                            :selected-block-id="garden.selected_block.id"
                            lock-block
                            return-to="kebun"
                        />
                    </template>
                </template>
            </section>
        </template>
    </div>
</template>
