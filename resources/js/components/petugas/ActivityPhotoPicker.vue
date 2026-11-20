<script setup lang="ts">
import { Camera, ImagePlus, X } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
    photos: File[];
    invalid: boolean;
    busy?: boolean;
    status?: string;
    notice?: string;
}>();
const emit = defineEmits<{
    select: [event: Event];
    remove: [index: number];
}>();
const previews = ref<string[]>([]);

onMounted(() => {
    watch(
        () => props.photos.slice(),
        (photos, _previous, onCleanup) => {
            const urls = photos.map((photo) => URL.createObjectURL(photo));
            previews.value = urls;
            onCleanup(() => urls.forEach((url) => URL.revokeObjectURL(url)));
        },
        { immediate: true },
    );
});
</script>

<template>
    <div class="relative space-y-3">
        <!-- Keep the hidden input positioned within the form's scrollable content. -->
        <input
            id="activity-photos"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            class="peer sr-only"
            :disabled="busy || photos.length >= 5"
            :aria-invalid="invalid"
            :aria-describedby="
                invalid ? 'photo-help photo-errors' : 'photo-help'
            "
            @change="emit('select', $event)"
        />
        <label
            for="activity-photos"
            class="flex min-h-20 cursor-pointer items-center gap-3 rounded-xl border border-dashed border-primary/40 bg-primary/5 p-3 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring peer-disabled:cursor-not-allowed peer-disabled:opacity-60"
        >
            <span
                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
            >
                <ImagePlus class="size-5" aria-hidden="true" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-primary">
                    {{
                        photos.length >= 5
                            ? 'Batas foto tercapai'
                            : 'Tambah foto'
                    }}
                </span>
                <span
                    id="photo-help"
                    class="mt-1 block text-xs leading-5 text-muted-foreground"
                >
                    Min. 1, maks. 5 foto · JPG, PNG, WebP · 15 MB/foto.
                </span>
            </span>
            <span
                class="shrink-0 rounded-full bg-card px-2 py-1 text-xs font-semibold text-foreground"
                aria-live="polite"
            >
                {{ photos.length }}/5
            </span>
        </label>
        <p v-if="status" role="status" class="text-xs text-muted-foreground">
            {{ status }}
        </p>
        <p v-if="notice" role="status" class="text-xs text-warning">
            {{ notice }}
        </p>
        <ul
            v-if="photos.length"
            class="grid grid-cols-2 gap-2 min-[360px]:grid-cols-3"
            aria-label="Foto dipilih"
        >
            <li
                v-for="(photo, index) in photos"
                :key="`${photo.name}-${index}`"
                class="min-w-0"
            >
                <div
                    class="relative aspect-square overflow-hidden rounded-xl bg-muted"
                >
                    <img
                        v-if="previews[index]"
                        :src="previews[index]"
                        alt=""
                        class="size-full object-cover"
                        @error="previews[index] = ''"
                    />
                    <Camera
                        v-else
                        class="absolute inset-0 m-auto size-6 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <button
                        type="button"
                        :disabled="busy"
                        :aria-label="`Hapus ${photo.name}`"
                        class="absolute top-1 right-1 inline-flex size-11 items-center justify-center rounded-xl bg-card text-foreground hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        @click="emit('remove', index)"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <p class="mt-1 truncate text-xs text-muted-foreground">
                    {{ photo.name }}
                </p>
            </li>
        </ul>
    </div>
</template>
