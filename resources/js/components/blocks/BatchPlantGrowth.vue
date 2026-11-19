<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowUpRight,
    Camera,
    ClipboardList,
    Expand,
    Eye,
    ImageOff,
    Sprout,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    findObservedGrowthStage,
    getGrowthStageAtHst,
    resolveGrowthProfile,
} from '@/data/growthStages';
import {
    blockDate,
    type BatchGrowth,
    type BatchObservationItem,
    type BatchObservationPhoto,
} from '@/types/blocks';
import { adminTaskStatusClass, adminTaskStatusLabels } from '@/types/tasks';

const props = defineProps<{
    growth: BatchGrowth;
    plantedAt: string | null;
    theme: string;
}>();
const profile = computed(() =>
    resolveGrowthProfile(props.growth.commodity, props.growth.variety),
);
const previewOpen = ref(false);
const selectedFieldPhoto = ref<{
    photo: BatchObservationPhoto;
    observation: BatchObservationItem;
} | null>(null);
const failedImages = ref(new Set<string>());
const failedFieldPhotoIds = ref(new Set<string>());
const selectedStage = computed(() =>
    profile.value ? getGrowthStageAtHst(profile.value, props.growth.hst) : null,
);
const hstPercentage = computed(() => {
    if (props.growth.hst_percentage != null) {
        return Math.min(100, Math.max(0, props.growth.hst_percentage));
    }
    const hst = props.growth.hst;
    const targetHst = profile.value?.stages.at(-1)?.throughHst;
    if (hst == null || !targetHst || targetHst <= 0) {
        return null;
    }

    return Math.min(
        100,
        Math.max(0, Math.round((hst / targetHst) * 1000) / 10),
    );
});
const observations = computed(() => props.growth.observations ?? []);
const tasks = computed(() => props.growth.tasks ?? []);
const completedTasksCount = computed(
    () => tasks.value.filter((task) => task.status === 'completed').length,
);
const latestObservation = computed(() => observations.value[0] ?? null);
const latestObservedPhase = computed(
    () =>
        latestObservation.value?.phase ??
        props.growth.observation?.phase ??
        null,
);
const latestObservedAt = computed(
    () =>
        latestObservation.value?.observed_at ??
        props.growth.observation?.observed_at ??
        null,
);
const matchedObservedStage = computed(() =>
    findObservedGrowthStage(profile.value, latestObservedPhase.value),
);
function observedDateTime(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }).format(date);
}
function isFieldPhotoAvailable(photo: BatchObservationPhoto): boolean {
    return photo.available && !failedFieldPhotoIds.value.has(photo.id);
}
function markFieldPhotoFailed(id: string): void {
    failedFieldPhotoIds.value.add(id);
}
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
                v-if="hstPercentage !== null"
                class="text-2xl font-bold text-primary tabular-nums"
                >{{
                    new Intl.NumberFormat('id-ID', {
                        maximumFractionDigits: 1,
                    }).format(hstPercentage)
                }}<span class="ml-0.5 text-sm">%</span
                ><span
                    class="block text-right text-[10px] font-medium text-muted-foreground"
                    >Progres umur (HST)</span
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
        <div class="space-y-3 rounded-xl bg-growth-summary p-4 text-white">
            <div class="space-y-2">
                <div
                    class="flex flex-wrap items-center justify-between gap-2 text-sm"
                >
                    <span class="flex items-center gap-2 text-white/75"
                        ><Activity class="size-4" aria-hidden="true" />Fase
                        berdasarkan HST</span
                    >
                    <strong class="font-semibold text-white">{{
                        selectedStage?.label || 'Referensi fase belum tersedia'
                    }}</strong>
                </div>
                <div
                    v-if="latestObservedPhase"
                    class="flex flex-wrap items-center justify-between gap-2 text-sm"
                >
                    <span class="flex items-center gap-2 text-white/75"
                        ><Eye class="size-4" aria-hidden="true" />Fase hasil
                        pengamatan</span
                    >
                    <strong class="font-semibold text-white">{{
                        latestObservedPhase
                    }}</strong>
                </div>
            </div>
            <div
                v-if="hstPercentage !== null"
                role="progressbar"
                aria-label="Progres umur tanaman (HST)"
                :aria-valuenow="hstPercentage"
                :aria-valuemin="0"
                :aria-valuemax="100"
                class="h-2 overflow-hidden rounded-full bg-white/20"
            >
                <div
                    class="h-full rounded-full bg-stage-completed"
                    :style="{ width: `${hstPercentage}%` }"
                />
            </div>
            <dl class="flex flex-wrap gap-x-8 gap-y-2 text-xs">
                <div>
                    <dt class="inline text-white/75">Umur hari ini:</dt>
                    <dd class="inline font-semibold">
                        {{ growth.hst }} HST · {{ blockDate(growth.as_of) }}
                    </dd>
                </div>
                <div>
                    <dt class="inline text-white/75">Estimasi awal panen:</dt>
                    <dd class="inline font-semibold">
                        {{
                            growth.estimated_harvest_at
                                ? blockDate(growth.estimated_harvest_at)
                                : 'Belum ada estimasi'
                        }}
                    </dd>
                </div>
                <div v-if="latestObservedAt">
                    <dt class="inline text-white/75">Pengamatan terakhir:</dt>
                    <dd class="inline font-semibold">
                        {{ blockDate(latestObservedAt) }}
                    </dd>
                </div>
            </dl>
        </div>
        <template v-if="profile && selectedStage">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h4 class="text-sm font-semibold">Kategori pertumbuhan</h4>
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
                    <span
                        v-if="matchedObservedStage?.number === stage.number"
                        class="mt-1.5 inline-flex w-fit items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold"
                        :class="
                            stage.number === selectedStage.number
                                ? 'bg-white/20 text-white'
                                : 'bg-primary/10 text-primary'
                        "
                        >Teramati</span
                    >
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
                    : 'Pilih komoditas blok untuk menampilkan panduan fase.'
            }}
        </p>

        <div v-if="tasks.length" class="space-y-3 border-t border-border pt-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h4 class="flex items-center gap-2 text-sm font-semibold">
                    <ClipboardList
                        class="size-4 text-primary"
                        aria-hidden="true"
                    />Tugas SOP pengamatan pertumbuhan
                </h4>
                <span class="text-xs text-muted-foreground"
                    >{{ completedTasksCount }} /
                    {{ tasks.length }} selesai</span
                >
            </div>
            <ul class="space-y-2">
                <li
                    v-for="task in tasks"
                    :key="task.id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border px-3.5 py-2.5 text-sm"
                >
                    <div class="min-w-0 space-y-0.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium break-words">{{
                                task.name
                            }}</span>
                            <span
                                class="rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                                >H{{ task.offset_days >= 0 ? '+' : ''
                                }}{{ task.offset_days }}</span
                            >
                        </div>
                        <p
                            v-if="task.scheduled_date"
                            class="text-xs text-muted-foreground"
                        >
                            Jadwal {{ blockDate(task.scheduled_date)
                            }}<template v-if="task.due_date">
                                – {{ blockDate(task.due_date) }}</template
                            >
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="adminTaskStatusClass(task.status)"
                            >{{ adminTaskStatusLabels[task.status] }}</span
                        >
                        <Link
                            v-if="task.url"
                            :href="task.url"
                            class="inline-flex min-h-8 items-center gap-1 rounded-lg border border-border px-2.5 py-1 text-xs font-medium text-primary hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                            >Detail tugas
                            <ArrowUpRight class="size-3.5" aria-hidden="true"
                        /></Link>
                    </div>
                </li>
            </ul>
        </div>

        <div class="space-y-3 border-t border-border pt-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h4 class="flex items-center gap-2 text-sm font-semibold">
                    <Camera class="size-4 text-primary" aria-hidden="true" />
                    Riwayat pengamatan lapangan
                </h4>
                <span class="text-xs text-muted-foreground"
                    >{{ observations.length }} laporan</span
                >
            </div>
            <ul v-if="observations.length" class="space-y-3">
                <li
                    v-for="obs in observations"
                    :key="obs.id"
                    class="space-y-2.5 rounded-xl border border-border bg-muted/20 p-3.5 text-sm"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    v-if="obs.phase"
                                    class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary"
                                    >{{ obs.phase }}</span
                                >
                                <span class="text-xs text-muted-foreground">{{
                                    observedDateTime(obs.observed_at)
                                }}</span>
                            </div>
                            <p
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <UserRound
                                    class="size-3.5 text-primary"
                                    aria-hidden="true"
                                />
                                {{ obs.observer_name ?? 'Petugas kebun' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                v-if="obs.task_url"
                                :href="obs.task_url"
                                class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                                >{{ obs.task_name ?? 'Tugas SOP' }}
                                <ArrowUpRight
                                    class="size-3.5"
                                    aria-hidden="true"
                            /></Link>
                            <Link
                                v-if="obs.activity_url"
                                :href="obs.activity_url"
                                class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-primary hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                                >Log aktivitas
                                <ArrowUpRight
                                    class="size-3.5"
                                    aria-hidden="true"
                            /></Link>
                        </div>
                    </div>
                    <p
                        v-if="obs.notes"
                        class="text-sm leading-6 break-words whitespace-pre-line text-foreground"
                    >
                        {{ obs.notes }}
                    </p>
                    <div
                        v-if="obs.photos.length"
                        class="grid grid-cols-3 gap-2 sm:grid-cols-5"
                    >
                        <template
                            v-for="(photo, index) in obs.photos"
                            :key="photo.id"
                        >
                            <button
                                v-if="isFieldPhotoAvailable(photo)"
                                type="button"
                                class="aspect-square overflow-hidden rounded-lg border border-border bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                                :aria-label="`Perbesar foto pengamatan ${index + 1}`"
                                @click="
                                    selectedFieldPhoto = {
                                        photo,
                                        observation: obs,
                                    }
                                "
                            >
                                <img
                                    :src="photo.url"
                                    :alt="`Foto pengamatan lapangan ${index + 1}`"
                                    loading="lazy"
                                    class="size-full object-cover"
                                    @error="markFieldPhotoFailed(photo.id)"
                                />
                            </button>
                            <div
                                v-else
                                class="flex aspect-square items-center justify-center rounded-lg border border-dashed border-border bg-muted text-muted-foreground"
                            >
                                <ImageOff class="size-4" aria-hidden="true" />
                            </div>
                        </template>
                    </div>
                </li>
            </ul>
            <p
                v-else
                class="rounded-xl border border-dashed border-border px-4 py-5 text-center text-xs text-muted-foreground"
            >
                Belum ada laporan pengamatan pertumbuhan dari petugas untuk
                subblok ini.
            </p>
        </div>
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
    <Dialog
        :open="selectedFieldPhoto !== null"
        @update:open="
            (open) => {
                if (!open) selectedFieldPhoto = null;
            }
        "
    >
        <DialogContent
            :class="theme"
            class="max-h-[90dvh] overflow-y-auto bg-card sm:max-w-3xl"
        >
            <DialogHeader>
                <DialogTitle>{{
                    selectedFieldPhoto?.observation.phase ??
                    'Foto pengamatan lapangan'
                }}</DialogTitle>
                <DialogDescription v-if="selectedFieldPhoto">
                    {{
                        observedDateTime(
                            selectedFieldPhoto.observation.observed_at,
                        )
                    }}
                    ·
                    {{
                        selectedFieldPhoto.observation.observer_name ??
                        'Petugas kebun'
                    }}
                </DialogDescription>
            </DialogHeader>
            <img
                v-if="selectedFieldPhoto"
                :src="selectedFieldPhoto.photo.url"
                :alt="
                    selectedFieldPhoto.observation.phase
                        ? `Foto pengamatan ${selectedFieldPhoto.observation.phase}`
                        : 'Foto pengamatan lapangan'
                "
                class="max-h-[70dvh] w-full rounded-xl object-contain"
            />
        </DialogContent>
    </Dialog>
</template>
