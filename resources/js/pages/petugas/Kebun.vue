<script setup lang="ts">
import InertiaPagination from '@/components/InertiaPagination.vue';
import { Head, Link } from '@inertiajs/vue3';
import { LandPlot, Layers, Plus, Sprout } from '@lucide/vue';
import { ref } from 'vue';
import GardenFarmCard from '@/components/petugas/GardenFarmCard.vue';
import GardenBatchCard from '@/components/petugas/GardenBatchCard.vue';
import GardenBlockCapacity from '@/components/petugas/GardenBlockCapacity.vue';
import SubblokCreateDialog from '@/components/petugas/SubblokCreateDialog.vue';
import FarmCycleSummary from '@/components/blocks/FarmCycleSummary.vue';
import { Button } from '@/components/ui/button';
import type { PetugasGarden } from '@/types/petugas-garden';

defineOptions({ inheritAttrs: false });
defineProps<{ garden: PetugasGarden }>();
const createOpen = ref(false);
function gardenHref(view: PetugasGarden['view'], blockId?: string): string {
    const params = new URLSearchParams({ view });
    if (blockId) params.set('block', blockId);
    return `/petugas/kebun?${params.toString()}`;
}
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
            <FarmCycleSummary :limit="2" />
            <GardenFarmCard
                :farm="garden.farm"
                :summary="garden.summary"
                :blocks="garden.blocks"
                :selected-block-id="garden.selected_block?.id ?? null"
            />
            <section aria-labelledby="garden-blocks" class="space-y-4">
                <h2 id="garden-blocks" class="admin-section-title">Blok</h2>
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
                            :href="gardenHref(garden.view, block.id)"
                            preserve-scroll
                            class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                            :class="
                                block.id === garden.selected_block?.id
                                    ? 'border-secondary bg-secondary text-secondary-foreground hover:bg-secondary/90'
                                    : 'border-border bg-card text-foreground hover:bg-muted'
                            "
                            :aria-current="
                                block.id === garden.selected_block?.id
                                    ? 'page'
                                    : undefined
                            "
                            :aria-label="`${block.name}, ${block.batches_count} subblok${block.is_active ? '' : ', nonaktif'}`"
                            >{{ block.name }}</Link
                        >
                    </nav>
                    <template v-if="garden.selected_block">
                        <GardenBlockCapacity :block="garden.selected_block" />
                        <nav
                            aria-label="Tampilan subblok"
                            class="flex gap-2 overflow-x-auto"
                        >
                            <Link
                                v-for="view in [
                                    { value: 'running', label: 'Berjalan' },
                                    { value: 'history', label: 'Riwayat' },
                                ] as const"
                                :key="view.value"
                                :href="
                                    gardenHref(
                                        view.value,
                                        garden.selected_block.id,
                                    )
                                "
                                preserve-scroll
                                :aria-current="
                                    garden.view === view.value
                                        ? 'page'
                                        : undefined
                                "
                                class="inline-flex min-h-11 shrink-0 items-center rounded-xl border px-4 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                                :class="
                                    garden.view === view.value
                                        ? 'border-secondary bg-secondary text-secondary-foreground'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted'
                                "
                                >{{ view.label }}</Link
                            >
                        </nav>
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <h3 class="text-base font-semibold">Subblok</h3>
                                <p class="text-xs text-muted-foreground">
                                    {{ garden.batches.total }} batch
                                </p>
                            </div>
                            <Button
                                v-if="
                                    garden.selected_block.can_create_batch &&
                                    garden.selected_block.is_active
                                "
                                type="button"
                                variant="default"
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
                                {{
                                    garden.view === 'history'
                                        ? 'Belum ada riwayat subblok pada blok ini'
                                        : 'Belum ada subblok berjalan pada blok ini'
                                }}
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
