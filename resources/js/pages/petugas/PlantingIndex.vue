<script setup lang="ts">
import PageBackButton from '@/components/PageBackButton.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Plus, Sprout } from '@lucide/vue';
import { computed, ref } from 'vue';
import SubblokCreateDialog, {
    type SubblokBlockOption,
} from '@/components/petugas/SubblokCreateDialog.vue';
import { Button } from '@/components/ui/button';
import { useActivityDate } from '@/composables/useActivityDate';
import {
    extractPetugasBackParam,
    petugasBackLabel,
    resolvePetugasBackHref,
    withPetugasBack,
} from '@/lib/petugasBackLink';
import type { PlantingCycleContext, PlantingWindow } from '@/types/planting';
import { gardenArea } from '@/types/petugas-garden';

const props = defineProps<{
    farm: { id: string; name: string };
    blocks: (SubblokBlockOption & { area_ha: string; cycles_count: number })[];
    selectedBlockId: string | null;
    cycles: (PlantingCycleContext & {
        canRecord: boolean;
        canSync?: boolean;
        blockedReason: string | null;
        plantingWindow: PlantingWindow | null;
    })[];
}>();
const page = usePage();
const activityDate = useActivityDate();
const explicitBack = computed(() => extractPetugasBackParam(page.url));
const backHref = computed(() =>
    resolvePetugasBackHref(page.url, '/petugas/laporan'),
);
const backLabel = computed(() =>
    petugasBackLabel(backHref.value, 'Kembali ke laporan'),
);
const createOpen = ref(false);
const workDateParam = computed(() =>
    activityDate.value ? activityDate.value.slice('?work_date='.length) : null,
);
const totalCyclesCount = computed(() =>
    props.blocks.reduce((sum, block) => sum + block.cycles_count, 0),
);
const canCreateSubblok = computed(() =>
    props.blocks.some((block) => block.is_active && block.can_create_batch),
);
const hasAvailableBlock = computed(() =>
    props.blocks.some(
        (block) =>
            block.is_active &&
            block.can_create_batch &&
            Number(block.available_ha) > 0,
    ),
);
function blockFilterHref(blockId: string | null): string {
    const params = new URLSearchParams();
    if (blockId) params.set('block', blockId);
    if (activityDate.value) {
        params.set('work_date', activityDate.value.slice('?work_date='.length));
    }
    if (explicitBack.value) params.set('back', explicitBack.value);
    const query = params.toString();
    return query ? `/petugas/penanaman?${query}` : '/petugas/penanaman';
}
function formatWindowDate(value: string): string {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
    const date = new Date(`${value}T00:00:00Z`);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeZone: 'UTC',
          }).format(date);
}
function formatWindowRange(window: PlantingWindow): string {
    const start = formatWindowDate(window.starts_at);
    const end = formatWindowDate(window.ends_at);
    return start === end ? start : `${start} – ${end}`;
}
</script>

<template>
    <Head title="Penanaman Subblok" />
    <div class="min-w-0 space-y-5 text-foreground">
        <header>
            <div class="flex min-w-0 items-start gap-2">
                <PageBackButton :href="backHref" :label="backLabel" />
                <div
                    class="min-w-0 flex-1 flex flex-wrap items-start justify-between gap-3"
                >
                    <div>
                        <h1 class="text-xl leading-7 font-semibold">
                            Catat penanaman
                        </h1>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ farm.name }} · Catat tanggal tanam yang
                            benar-benar terjadi, bukan tanggal rencana.
                        </p>
                    </div>
                    <Button
                        v-if="canCreateSubblok"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="min-h-11 rounded-xl"
                        :disabled="!hasAvailableBlock"
                        @click="createOpen = true"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        Tambah subblok
                    </Button>
                </div>
            </div>
        </header>
        <!-- <p
            class="rounded-xl border border-border bg-muted/50 p-4 text-sm leading-6"
        >
            Pilih subblok yang tersedia atau tambah subblok baru pada blok
            aktif. Penanaman lanjutan dibatasi tiga hari kalender sejak tanggal
            pelaksanaan pertama.
        </p> -->
        <p
            v-if="canCreateSubblok && !hasAvailableBlock"
            class="text-xs text-muted-foreground"
        >
            Kapasitas seluruh blok sudah penuh. Subblok baru dapat ditambahkan
            setelah siklus tanam berjalan selesai.
        </p>
        <nav
            v-if="blocks.length"
            aria-label="Filter blok kebun"
            class="flex gap-2 overflow-x-auto pb-1"
        >
            <Link
                :href="blockFilterHref(null)"
                preserve-scroll
                class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                :class="
                    !selectedBlockId
                        ? 'border-primary/40 bg-primary/10 text-secondary dark:text-primary'
                        : 'border-border bg-card text-muted-foreground hover:bg-muted'
                "
                :aria-current="!selectedBlockId ? 'page' : undefined"
                :aria-label="`Semua blok, ${totalCyclesCount} subblok`"
                >Semua<span
                    class="rounded-full bg-muted px-1.5 py-0.5 text-[11px] text-foreground"
                    >{{ totalCyclesCount }}</span
                ></Link
            ><Link
                v-for="block in blocks"
                :key="block.id"
                :href="blockFilterHref(block.id)"
                preserve-scroll
                class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                :class="
                    block.id === selectedBlockId
                        ? 'border-primary/40 bg-primary/10 text-secondary dark:text-primary'
                        : 'border-border bg-card text-muted-foreground hover:bg-muted'
                "
                :aria-current="
                    block.id === selectedBlockId ? 'page' : undefined
                "
                :aria-label="`${block.name}, ${block.cycles_count} subblok${block.is_active ? '' : ', nonaktif'}`"
                >{{ block.name
                }}<span
                    class="rounded-full bg-muted px-1.5 py-0.5 text-[11px] text-foreground"
                    >{{ block.cycles_count }}</span
                ></Link
            >
        </nav>
        <p
            v-if="!cycles.length"
            class="rounded-xl border border-dashed border-border p-5 text-sm leading-6 text-muted-foreground"
        >
            {{
                selectedBlockId
                    ? 'Belum ada subblok pada blok ini.'
                    : hasAvailableBlock
                      ? 'Belum ada subblok untuk lahan penugasan Anda. Tambah subblok baru pada blok aktif untuk mulai mencatat penanaman.'
                      : canCreateSubblok
                        ? 'Belum ada subblok dan seluruh kapasitas blok aktif sudah penuh.'
                        : 'Belum ada subblok atau blok aktif untuk lahan penugasan Anda. Hubungi Admin atau PIC lahan untuk menyiapkannya.'
            }}
        </p>
        <article
            v-for="cycle in cycles"
            :key="cycle.id"
            class="space-y-3 rounded-xl border p-4"
            :class="
                cycle.plantingWindow?.expired
                    ? 'border-muted-foreground/40 bg-muted/80 dark:border-muted-foreground/35 dark:bg-card/40'
                    : 'border-border bg-card'
            "
        >
            <div class="flex items-start gap-3">
                <Sprout
                    class="mt-1 size-5 shrink-0"
                    :class="
                        cycle.plantingWindow?.expired
                            ? 'text-muted-foreground'
                            : 'text-primary'
                    "
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <h2
                        class="text-base leading-6 font-semibold"
                        :class="{
                            'text-foreground/75': cycle.plantingWindow?.expired,
                        }"
                    >
                        {{ cycle.block.name }} · {{ cycle.subblock_label }}
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Subblok {{ cycle.batch_number }} ·
                        {{
                            cycle.planted_at
                                ? 'Sudah ditanam'
                                : cycle.status === 'planned'
                                  ? 'Belum dicatat ditanam'
                                  : 'Subblok ditutup'
                        }}
                    </p>
                    <p
                        v-if="cycle.plantingWindow?.expired"
                        class="mt-1 text-xs leading-5 text-muted-foreground"
                    >
                        Lewat 3 hari sejak tanam pertama ·
                        {{
                            cycle.canRecord
                                ? `Tambahan hanya untuk tanggal ${formatWindowRange(cycle.plantingWindow)}.`
                                : `Rentang tanam ${formatWindowRange(cycle.plantingWindow)}.`
                        }}
                    </p>
                </div>
            </div>
            <p
                v-if="cycle.blockedReason"
                class="rounded-xl border border-warning/40 bg-warning/10 p-3 text-sm"
            >
                {{ cycle.blockedReason }}
            </p>
            <details>
                <summary
                    class="cursor-pointer py-2 text-xs text-muted-foreground"
                >
                    Rencana, realisasi, dan luas
                </summary>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Rencana tanam
                        </dt>
                        <dd>
                            {{
                                cycle.planned_planting_at ?? 'Belum ditentukan'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Tanam aktual
                        </dt>
                        <dd>{{ cycle.planted_at ?? 'Belum dicatat' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Luas</dt>
                        <dd>
                            Tertanam {{ gardenArea(cycle.planted_area_ha) }} ha
                        </dd>
                    </div>
                </dl>
            </details>
            <div class="flex flex-wrap gap-2">
                <Button
                    as-child
                    :variant="
                        cycle.plantingWindow?.expired || !cycle.canRecord
                            ? 'outline'
                            : 'default'
                    "
                    class="min-h-11 rounded-xl"
                    ><Link
                        :href="
                            withPetugasBack(
                                `/petugas/subbloks/${cycle.id}/penanaman${activityDate}`,
                                explicitBack,
                            )
                        "
                        >{{
                            cycle.canRecord
                                ? 'Catat penanaman'
                                : 'Lihat realisasi'
                        }}</Link
                    ></Button
                >
            </div>
        </article>
        <Button as-child variant="outline" class="min-h-11 w-full rounded-xl"
            ><Link href="/petugas/aktivitas">Lihat to-do lahan</Link></Button
        >
        <SubblokCreateDialog
            v-model:open="createOpen"
            :blocks="blocks"
            :selected-block-id="selectedBlockId"
            return-to="penanaman"
            :work-date="workDateParam"
        />
    </div>
</template>
