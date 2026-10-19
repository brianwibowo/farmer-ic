<script setup lang="ts">
import { Camera, ChevronLeft, ChevronRight, Expand, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { ActivityPhoto } from '@/data/activityLogs';

const props = defineProps<{
    photos: ActivityPhoto[];
}>();

const selectedPhoto = ref<ActivityPhoto | null>(null);
const selectedIndex = computed(() =>
    selectedPhoto.value
        ? props.photos.findIndex(
              (photo) => photo.id === selectedPhoto.value?.id,
          )
        : -1,
);

function openPhoto(photo: ActivityPhoto): void {
    selectedPhoto.value = photo;
}

function closePhoto(): void {
    selectedPhoto.value = null;
}

function showPrevious(): void {
    if (selectedIndex.value < 0 || props.photos.length < 2) return;
    const index =
        (selectedIndex.value - 1 + props.photos.length) % props.photos.length;
    selectedPhoto.value = props.photos[index];
}

function showNext(): void {
    if (selectedIndex.value < 0 || props.photos.length < 2) return;
    selectedPhoto.value =
        props.photos[(selectedIndex.value + 1) % props.photos.length];
}
</script>

<template>
    <section
        aria-labelledby="activity-photos-heading"
        class="rounded-2xl border border-border bg-card p-4"
    >
        <div
            class="flex items-center justify-between gap-3 border-b border-border pb-3"
        >
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex size-8 items-center justify-center rounded-lg bg-surface text-secondary"
                >
                    <Camera :size="17" :stroke-width="1.8" aria-hidden="true" />
                </span>
                <div>
                    <h2
                        id="activity-photos-heading"
                        class="text-base leading-5 font-semibold text-foreground"
                    >
                        Foto dokumentasi
                    </h2>
                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                        Bukti visual kegiatan lapangan
                    </p>
                </div>
            </div>
            <span
                class="rounded-full bg-muted px-2.5 py-1 text-[11px] font-semibold text-muted-foreground"
                >{{ photos.length }} foto</span
            >
        </div>

        <div v-if="photos.length" class="mt-4 grid grid-cols-2 gap-2.5">
            <button
                type="button"
                class="group relative col-span-2 aspect-[16/9] overflow-hidden rounded-xl border border-border bg-muted text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="openPhoto(photos[0])"
            >
                <img
                    :src="photos[0].image"
                    :alt="photos[0].label"
                    class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                />
                <span
                    class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 bg-gradient-to-t from-foreground/80 to-transparent px-3 pt-8 pb-3 text-background"
                >
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold">{{
                            photos[0].label
                        }}</span>
                        <span
                            class="mt-0.5 block truncate text-[11px] opacity-90"
                            >{{ photos[0].caption }}</span
                        >
                    </span>
                    <span
                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-background/90 text-foreground"
                        ><Expand :size="15" aria-hidden="true"
                    /></span>
                </span>
            </button>

            <button
                v-for="photo in photos.slice(1)"
                :key="photo.id"
                type="button"
                class="group relative aspect-[4/3] overflow-hidden rounded-xl border border-border bg-muted text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="openPhoto(photo)"
            >
                <img
                    :src="photo.image"
                    :alt="photo.label"
                    class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                />
                <span
                    class="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-foreground/75 to-transparent px-2 pt-5 pb-2 text-[11px] font-semibold text-background"
                    >{{ photo.label }}</span
                >
                <span
                    class="absolute top-2 right-2 inline-flex size-7 items-center justify-center rounded-lg bg-background/90 text-foreground"
                    ><Expand :size="14" aria-hidden="true"
                /></span>
            </button>
        </div>

        <div
            v-else
            class="mt-4 rounded-xl border border-dashed border-border bg-muted/50 px-4 py-7 text-center"
        >
            <Camera
                :size="28"
                class="mx-auto text-muted-foreground"
                aria-hidden="true"
            />
            <p class="mt-2 text-sm font-medium text-foreground">
                Belum ada foto dokumentasi
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Foto kegiatan akan muncul di sini.
            </p>
        </div>

        <div
            v-if="selectedPhoto"
            class="fixed inset-0 z-50 flex items-center justify-center bg-foreground/75 p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="selectedPhoto.label"
            @click.self="closePhoto"
            @keydown.esc="closePhoto"
        >
            <div
                class="w-full max-w-[420px] overflow-hidden rounded-2xl border border-border bg-card shadow-lg"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-border px-4 py-3"
                >
                    <div class="min-w-0">
                        <p
                            class="truncate text-sm font-semibold text-foreground"
                        >
                            {{ selectedPhoto.label }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            {{ selectedIndex + 1 }} dari
                            {{ photos.length }} foto
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                        aria-label="Tutup pratinjau foto"
                        @click="closePhoto"
                    >
                        <X :size="18" aria-hidden="true" />
                    </button>
                </div>
                <div class="relative bg-muted p-3">
                    <img
                        :src="selectedPhoto.image"
                        :alt="selectedPhoto.label"
                        class="aspect-[4/3] w-full rounded-xl object-cover"
                    />
                    <button
                        v-if="photos.length > 1"
                        type="button"
                        class="absolute top-1/2 left-5 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-background/90 text-foreground shadow-sm focus-visible:outline-2 focus-visible:outline-ring"
                        aria-label="Foto sebelumnya"
                        @click="showPrevious"
                    >
                        <ChevronLeft :size="18" aria-hidden="true" />
                    </button>
                    <button
                        v-if="photos.length > 1"
                        type="button"
                        class="absolute top-1/2 right-5 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-background/90 text-foreground shadow-sm focus-visible:outline-2 focus-visible:outline-ring"
                        aria-label="Foto berikutnya"
                        @click="showNext"
                    >
                        <ChevronRight :size="18" aria-hidden="true" />
                    </button>
                </div>
                <p class="px-4 py-3 text-xs leading-5 text-muted-foreground">
                    {{ selectedPhoto.caption }}
                </p>
            </div>
        </div>
    </section>
</template>
