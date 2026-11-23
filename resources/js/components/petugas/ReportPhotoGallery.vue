<script setup lang="ts">
import {
    Camera,
    ChevronLeft,
    ChevronRight,
    Expand,
    ImageOff,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { DailyReportActivity } from '@/types/daily-activity';

type Photo = DailyReportActivity['photos'][number];
const props = defineProps<{ photos: Photo[]; activityName: string }>();
const selectedIndex = ref(0);
const open = ref(false);
const failedIds = ref<string[]>([]);
const selected = computed(() => props.photos[selectedIndex.value]);

watch(
    () => props.photos,
    () => {
        failedIds.value = [];
        open.value = false;
        selectedIndex.value = 0;
    },
);
function available(photo: Photo): boolean {
    return photo.available !== false && !failedIds.value.includes(photo.id);
}
function markFailed(photo: Photo): void {
    if (!failedIds.value.includes(photo.id)) failedIds.value.push(photo.id);
}
function show(index: number): void {
    selectedIndex.value = index;
    open.value = true;
}
function move(amount: -1 | 1): void {
    if (props.photos.length < 2) return;
    selectedIndex.value =
        (selectedIndex.value + amount + props.photos.length) %
        props.photos.length;
}
</script>

<template>
    <section aria-labelledby="report-photos-heading" class="petugas-card p-4">
        <div
            class="flex items-center justify-between gap-3 border-b border-border pb-3"
        >
            <div class="flex min-w-0 items-center gap-2.5">
                <span
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <Camera class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h2
                        id="report-photos-heading"
                        class="text-base font-semibold"
                    >
                        Foto dokumentasi
                    </h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Bukti visual kegiatan lapangan
                    </p>
                </div>
            </div>
            <span
                class="shrink-0 rounded-full bg-muted px-2.5 py-1 text-xs font-semibold text-muted-foreground"
            >
                {{ photos.length }} foto
            </span>
        </div>

        <div v-if="photos.length" class="mt-4 grid grid-cols-2 gap-2.5">
            <div
                v-for="(photo, index) in photos"
                :key="photo.id"
                class="overflow-hidden rounded-xl bg-muted"
                :class="
                    index === 0 ? 'col-span-2 aspect-video' : 'aspect-[4/3]'
                "
            >
                <button
                    v-if="available(photo)"
                    type="button"
                    class="group relative size-full text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    :aria-label="`Perbesar foto ${index + 1} ${activityName}`"
                    @click="show(index)"
                >
                    <img
                        :src="photo.url"
                        :alt="`Dokumentasi ${activityName}, foto ${index + 1}`"
                        class="size-full object-cover transition-transform group-hover:scale-105 motion-reduce:transition-none"
                        loading="lazy"
                        @error="markFailed(photo)"
                    />
                    <span
                        class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 bg-gradient-to-t from-foreground/80 to-transparent px-3 pt-8 pb-3 text-background"
                    >
                        <span class="text-sm font-semibold"
                            >Foto {{ index + 1 }}</span
                        >
                        <span
                            class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-background/90 text-foreground"
                        >
                            <Expand class="size-4" aria-hidden="true" />
                        </span>
                    </span>
                </button>
                <div
                    v-else
                    class="flex size-full flex-col items-center justify-center gap-2 p-3 text-center text-muted-foreground"
                >
                    <ImageOff class="size-7" aria-hidden="true" />
                    <p class="text-xs">Foto {{ index + 1 }} tidak tersedia</p>
                </div>
            </div>
        </div>
        <div
            v-else
            class="mt-4 rounded-xl border border-dashed border-border bg-muted/40 px-4 py-7 text-center"
        >
            <Camera
                class="mx-auto size-7 text-muted-foreground"
                aria-hidden="true"
            />
            <p class="mt-2 text-sm font-medium">Belum ada foto dokumentasi</p>
            <p class="mt-1 text-xs text-muted-foreground">
                Tidak ada foto yang tercatat untuk aktivitas ini.
            </p>
        </div>

        <Dialog v-model:open="open">
            <DialogContent
                :show-close-button="false"
                class="admin-dashboard-theme petugas-dashboard-theme max-h-[90dvh] max-w-md overflow-y-auto"
            >
                <DialogHeader class="pr-10">
                    <DialogTitle>Foto dokumentasi</DialogTitle>
                    <DialogDescription>
                        Foto {{ selectedIndex + 1 }} dari {{ photos.length }} ·
                        {{ activityName }}
                    </DialogDescription>
                </DialogHeader>
                <DialogClose as-child>
                    <button
                        type="button"
                        aria-label="Tutup pratinjau foto"
                        class="absolute top-2 right-2 inline-flex size-11 items-center justify-center rounded-xl text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <X class="size-5" aria-hidden="true" />
                    </button>
                </DialogClose>
                <div v-if="selected" class="space-y-3">
                    <div
                        class="relative flex min-h-48 items-center justify-center overflow-hidden rounded-xl bg-muted"
                    >
                        <img
                            v-if="available(selected)"
                            :src="selected.url"
                            :alt="`Dokumentasi ${activityName}, foto ${selectedIndex + 1}`"
                            class="max-h-[60dvh] w-full object-contain"
                            @error="markFailed(selected)"
                        />
                        <p
                            v-else
                            class="p-6 text-center text-sm text-muted-foreground"
                        >
                            Foto tidak tersedia.
                        </p>
                    </div>
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <button
                            v-if="photos.length > 1"
                            type="button"
                            class="inline-flex min-h-11 items-center gap-1 rounded-xl px-2 text-sm font-medium text-primary hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                            @click="move(-1)"
                        >
                            <ChevronLeft
                                class="size-4"
                                aria-hidden="true"
                            />Sebelumnya
                        </button>
                        <a
                            v-if="available(selected)"
                            :href="selected.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 items-center rounded-xl px-2 text-sm font-medium text-primary underline focus-visible:outline-2 focus-visible:outline-ring"
                            >Buka gambar asli</a
                        >
                        <button
                            v-if="photos.length > 1"
                            type="button"
                            class="inline-flex min-h-11 items-center gap-1 rounded-xl px-2 text-sm font-medium text-primary hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                            @click="move(1)"
                        >
                            Berikutnya<ChevronRight
                                class="size-4"
                                aria-hidden="true"
                            />
                        </button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </section>
</template>
