<script setup lang="ts">
import { computed, ref } from 'vue';
import { Activity, Expand, Sprout } from '@lucide/vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { getGrowthProfile, getGrowthStageAtHst } from '@/data/growthStages';
import type { SeedType } from '@/data/kebun';
import { blockDate, type BatchGrowth } from '@/types/blocks';

const props = defineProps<{
    growth: BatchGrowth;
    plantedAt: string | null;
    theme: string;
}>();
const seedType = computed<SeedType | null>(() => {
    const name = props.growth.commodity?.toLocaleLowerCase('id') ?? '';
    if (name.includes('jagung')) return 'Jagung';
    if (name.includes('kedelai')) return 'Kedelai';
    if (name.includes('rumput')) return 'Rumput';
    return null;
});
const profile = computed(() =>
    seedType.value
        ? getGrowthProfile(seedType.value, props.growth.variety ?? undefined)
        : null,
);
const previewOpen = ref(false);
const failedImages = ref(new Set<string>());
const selectedStage = computed(() =>
    profile.value ? getGrowthStageAtHst(profile.value, props.growth.hst) : null,
);
const percentage = computed(() =>
    props.growth.observation?.percentage == null
        ? null
        : Math.min(100, Math.max(0, props.growth.observation.percentage)),
);
</script>

<template>
    <section
        class="admin-card space-y-5 bg-card p-4 sm:p-5"
        aria-label="Perkembangan tanaman"
    >
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="admin-section-title flex items-center gap-2">
                <Sprout
                    class="size-5 text-primary"
                    aria-hidden="true"
                />Perkembangan tanaman
            </h3>
            <span
                v-if="percentage !== null"
                class="text-2xl font-bold text-primary tabular-nums"
                >{{
                    new Intl.NumberFormat('id-ID', {
                        maximumFractionDigits: 1,
                    }).format(percentage)
                }}<span class="ml-0.5 text-sm">%</span
                ><span
                    class="block text-right text-[10px] font-medium text-muted-foreground"
                    >Pengamatan terakhir</span
                ></span
            >
            <span
                v-else
                class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                >{{
                    plantedAt ? 'Belum ada pengamatan' : 'Belum ditanam'
                }}</span
            >
        </header>
        <div class="space-y-3 rounded-xl bg-primary/5 p-4">
            <div
                class="flex flex-wrap items-center justify-between gap-2 text-sm"
            >
                <span class="flex items-center gap-2 text-muted-foreground"
                    ><Activity class="size-4" aria-hidden="true" />Fase
                    berdasarkan HST</span
                >
                <strong class="font-semibold text-primary">{{
                    selectedStage?.label || 'Referensi fase belum tersedia'
                }}</strong>
            </div>
            <div
                v-if="percentage !== null"
                role="progressbar"
                aria-label="Pertumbuhan tercatat"
                :aria-valuenow="percentage"
                :aria-valuemin="0"
                :aria-valuemax="100"
                class="h-2 overflow-hidden rounded-full bg-border"
            >
                <div
                    class="h-full rounded-full bg-primary"
                    :style="{ width: `${percentage}%` }"
                />
            </div>
            <dl class="flex flex-wrap gap-x-8 gap-y-2 text-xs">
                <div>
                    <dt class="inline text-muted-foreground">Umur hari ini:</dt>
                    <dd class="inline font-semibold">
                        {{ growth.hst }} HST · {{ blockDate(growth.as_of) }}
                    </dd>
                </div>
                <div>
                    <dt class="inline text-muted-foreground">
                        Estimasi awal panen:
                    </dt>
                    <dd class="inline font-semibold">
                        {{
                            growth.estimated_harvest_at
                                ? blockDate(growth.estimated_harvest_at)
                                : 'Belum ada estimasi'
                        }}
                    </dd>
                </div>
                <div v-if="growth.observation">
                    <dt class="inline text-muted-foreground">
                        Pengamatan terakhir:
                    </dt>
                    <dd class="inline font-semibold">
                        {{ blockDate(growth.observation.observed_at) }}
                    </dd>
                </div>
            </dl>
        </div>
        <template v-if="profile && selectedStage">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h4 class="text-sm font-semibold">Kategori pertumbuhan</h4>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Panduan visual {{ profile.commodity }} ·
                        {{ profile.variety }}
                    </p>
                </div>
                <span
                    class="rounded-full bg-accent px-3 py-1 text-xs font-semibold text-accent-foreground"
                    >Fase {{ selectedStage.number }} dari
                    {{ profile.stages.length }}</span
                >
            </div>
            <ol
                class="flex w-0 min-w-full gap-2 overflow-x-auto pb-1 sm:grid sm:grid-cols-5"
                aria-label="Fase pertumbuhan berdasarkan HST"
            >
                <li
                    v-for="stage in profile.stages"
                    :key="stage.number"
                    class="flex w-28 shrink-0 flex-col rounded-xl border p-2 text-left sm:w-auto sm:min-w-0"
                    :class="
                        stage.number === selectedStage.number
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-card text-foreground'
                    "
                    :aria-current="
                        stage.number === selectedStage.number
                            ? 'step'
                            : undefined
                    "
                >
                    <img
                        v-if="!failedImages.has(stage.image)"
                        :src="stage.image"
                        :alt="stage.imageAlt"
                        loading="lazy"
                        class="mb-2 aspect-[2/1] w-full rounded-lg object-cover"
                        @error="failedImages.add(stage.image)"
                    />
                    <div
                        v-else
                        class="mb-2 flex aspect-[2/1] w-full items-center justify-center rounded-lg bg-muted text-muted-foreground"
                    >
                        <Sprout class="size-6" aria-hidden="true" /><span
                            class="sr-only"
                            >Gambar belum tersedia</span
                        >
                    </div>
                    <span class="text-xs font-semibold"
                        >{{ stage.number }}. {{ stage.shortLabel }}</span
                    ><span class="mt-1 text-[11px] leading-4 opacity-80">{{
                        stage.range
                    }}</span
                    ><span class="mt-1 text-[10px] font-medium opacity-80">{{
                        stage.code
                    }}</span>
                </li>
            </ol>
            <div
                class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]"
            >
                <button
                    type="button"
                    class="group relative min-w-0 overflow-hidden rounded-xl bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    :aria-label="`Perbesar gambar fase ${selectedStage.label}`"
                    @click="previewOpen = true"
                >
                    <img
                        v-if="!failedImages.has(selectedStage.image)"
                        :src="selectedStage.image"
                        :alt="selectedStage.imageAlt"
                        loading="lazy"
                        class="h-52 w-full object-cover transition-transform motion-safe:group-hover:scale-105"
                        @error="failedImages.add(selectedStage.image)"
                    />
                    <div
                        v-else
                        class="flex h-52 items-center justify-center text-sm text-muted-foreground"
                    >
                        Gambar fase belum tersedia
                    </div>
                    <span
                        class="absolute right-3 bottom-3 inline-flex items-center gap-2 rounded-lg bg-card px-3 py-2 text-xs font-semibold text-primary"
                        ><Expand class="size-3.5" aria-hidden="true" />Lihat
                        gambar fase</span
                    >
                </button>
                <div class="min-w-0 space-y-3">
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Fase hari ini · {{ growth.hst }} HST
                        </p>
                        <h4 class="mt-1 text-lg font-bold">
                            {{ selectedStage.label }}
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >· {{ selectedStage.range }}</span
                            >
                        </h4>
                    </div>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ selectedStage.description }}
                    </p>
                    <details class="rounded-xl border border-border px-3 py-2">
                        <summary
                            class="cursor-pointer py-1 text-xs font-semibold text-primary focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            Ciri fase dan panduan perawatan
                        </summary>
                        <ul
                            class="mt-2 list-disc space-y-1 pl-4 text-xs leading-5 text-muted-foreground"
                        >
                            <li
                                v-for="indicator in selectedStage.indicators"
                                :key="indicator"
                            >
                                {{ indicator }}
                            </li>
                        </ul>
                        <p class="mt-3 text-xs leading-5">
                            {{ selectedStage.care }}
                        </p>
                        <p class="mt-2 text-xs leading-5 text-muted-foreground">
                            <strong class="text-foreground"
                                >Risiko fase:</strong
                            >
                            {{ selectedStage.risk }}
                        </p>
                    </details>
                </div>
            </div>
        </template>
        <p
            v-else
            class="rounded-xl border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
        >
            {{
                growth.commodity
                    ? `Panduan visual ${growth.commodity} belum tersedia.`
                    : 'Pilih komoditas kebun untuk menampilkan panduan fase.'
            }}
        </p>
    </section>
    <Dialog v-model:open="previewOpen"
        ><DialogContent
            :class="theme"
            class="max-h-[90dvh] overflow-y-auto bg-card sm:max-w-3xl"
            ><DialogHeader
                ><DialogTitle>{{
                    selectedStage?.label ?? 'Gambar fase'
                }}</DialogTitle
                ><DialogDescription
                    >{{ profile?.commodity }} · {{ selectedStage?.range }} ·
                    Gambar referensi, bukan foto pengamatan
                    batch.</DialogDescription
                ></DialogHeader
            ><img
                v-if="selectedStage && !failedImages.has(selectedStage.image)"
                :src="selectedStage.image"
                :alt="selectedStage.imageAlt"
                class="max-h-[65dvh] w-full rounded-xl object-contain"
                @error="failedImages.add(selectedStage.image)"
            />
            <p v-else class="py-12 text-center text-sm text-muted-foreground">
                Gambar fase belum tersedia.
            </p>
            <p
                v-if="selectedStage?.credit"
                class="text-xs text-muted-foreground"
            >
                {{ selectedStage.credit }}
            </p></DialogContent
        ></Dialog
    >
</template>
