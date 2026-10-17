<script setup lang="ts">
import { Camera, Expand, X } from '@lucide/vue';
import { ref } from 'vue';
import type { ActivityPhoto } from '@/data/activityLogs';

defineProps<{
    photos: ActivityPhoto[];
}>();

const selectedPhoto = ref<ActivityPhoto | null>(null);

function openPhoto(photo: ActivityPhoto): void {
    selectedPhoto.value = photo;
}

function closePhoto(): void {
    selectedPhoto.value = null;
}
</script>

<template>
    <section
        aria-labelledby="activity-photos-heading"
        class="rounded-xl border border-border bg-card p-4"
    >
        <div
            class="flex items-center justify-between gap-2 border-b border-border pb-3"
        >
            <div class="flex items-center gap-2">
                <Camera
                    :size="18"
                    :stroke-width="1.8"
                    class="shrink-0 text-primary"
                    aria-hidden="true"
                />
                <h2
                    id="activity-photos-heading"
                    class="text-[16px] leading-5 font-semibold text-foreground"
                >
                    Foto Dokumentasi
                </h2>
            </div>
            <span class="text-xs font-medium text-muted-foreground">
                {{ photos.length }} Foto
            </span>
        </div>

        <ul class="mt-3.5 grid grid-cols-2 gap-3">
            <li v-for="photo in photos" :key="photo.id">
                <button
                    type="button"
                    class="group flex h-full w-full flex-col overflow-hidden rounded-lg border border-border bg-muted/40 text-left transition-colors hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="openPhoto(photo)"
                >
                    <div
                        class="relative aspect-4/3 w-full overflow-hidden bg-muted"
                    >
                        <img
                            :src="photo.image"
                            :alt="photo.label"
                            class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                        />
                        <span
                            class="absolute right-2 bottom-2 inline-flex h-6 w-6 items-center justify-center rounded-md bg-card/90 text-foreground shadow-xs"
                            aria-hidden="true"
                        >
                            <Expand :size="13" />
                        </span>
                    </div>
                    <div class="flex flex-1 items-center p-2.5">
                        <p class="text-xs font-semibold text-foreground">
                            {{ photo.label }}
                        </p>
                    </div>
                </button>
            </li>
        </ul>

        <!-- Photo Lightbox Modal -->
        <div
            v-if="selectedPhoto"
            class="fixed inset-0 z-50 flex items-center justify-center bg-foreground/70 p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="selectedPhoto.label"
            @click.self="closePhoto"
        >
            <div
                class="w-full max-w-[380px] overflow-hidden rounded-2xl border border-border bg-card shadow-lg"
            >
                <div
                    class="flex items-center justify-between border-b border-border px-4 py-3"
                >
                    <h3 class="text-sm font-semibold text-foreground">
                        {{ selectedPhoto.label }}
                    </h3>
                    <button
                        type="button"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        aria-label="Tutup pratinjau foto"
                        @click="closePhoto"
                    >
                        <X :size="18" />
                    </button>
                </div>

                <div class="bg-muted p-3">
                    <img
                        :src="selectedPhoto.image"
                        :alt="selectedPhoto.label"
                        class="aspect-4/3 w-full rounded-lg object-cover"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
