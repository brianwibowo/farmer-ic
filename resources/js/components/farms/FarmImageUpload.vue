<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Upload } from '@lucide/vue';
import { Button } from '@/components/ui/button';

const file = defineModel<File | null>({ required: true });
const remove = defineModel<boolean>('remove', { required: true });
const props = defineProps<{ currentUrl: string | null; error?: string }>();
const emit = defineEmits<{ validationError: [message: string] }>();
const input = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(null);
const previewFailed = ref(false);
const imageUrl = computed(
    () => preview.value ?? (remove.value ? null : props.currentUrl),
);

watch(file, (value) => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = value ? URL.createObjectURL(value) : null;
});
watch(imageUrl, () => {
    previewFailed.value = false;
});
onBeforeUnmount(() => {
    if (preview.value) URL.revokeObjectURL(preview.value);
});

function choose(event: Event): void {
    const selected = (event.target as HTMLInputElement).files?.[0];
    if (!selected) return;
    if (
        !['image/jpeg', 'image/png', 'image/webp'].includes(selected.type) ||
        selected.size > 10 * 1024 * 1024
    ) {
        emit(
            'validationError',
            'Pilih foto JPEG, PNG, atau WebP maksimal 10 MB.',
        );
        if (input.value) input.value.value = '';
        return;
    }
    emit('validationError', '');
    remove.value = false;
    file.value = selected;
}

function clear(): void {
    file.value = null;
    remove.value = true;
    if (input.value) input.value.value = '';
    emit('validationError', '');
}
</script>

<template>
    <div class="space-y-3">
        <div>
            <p class="text-sm font-medium">
                Foto lahan
                <span class="font-normal text-muted-foreground"
                    >(opsional)</span
                >
            </p>
            <p
                id="farm-image-help"
                class="mt-1 text-xs leading-5 text-muted-foreground"
            >
                JPEG, PNG, atau WebP. Maksimal 1 foto per lahan, 10 MB per file.
            </p>
        </div>
        <label
            for="farm-image"
            class="flex items-center gap-2 text-sm font-medium"
        >
            <Upload class="size-4 text-primary" aria-hidden="true" />
            {{
                currentUrl && !remove ? 'Ganti foto lahan' : 'Tambah foto lahan'
            }}
        </label>
        <input
            ref="input"
            id="farm-image"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="admin-field w-full min-w-0 text-sm file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1 file:text-foreground"
            :aria-invalid="!!error"
            :aria-describedby="
                error ? 'farm-image-help farm-image-error' : 'farm-image-help'
            "
            @change="choose"
        />
        <p
            v-if="error"
            id="farm-image-error"
            role="alert"
            class="text-xs text-error"
        >
            {{ error }}
        </p>
    </div>
    <div class="mt-4 space-y-3" aria-live="polite">
        <img
            v-if="imageUrl && !previewFailed"
            :src="imageUrl"
            alt="Pratinjau foto lahan"
            class="max-h-64 w-full rounded-xl border border-border bg-muted object-contain"
            @error="previewFailed = true"
        />
        <p v-else class="text-sm text-muted-foreground">
            {{
                previewFailed
                    ? 'Foto tidak dapat ditampilkan. Pilih foto pengganti atau coba muat ulang.'
                    : 'Belum ada foto dipilih.'
            }}
        </p>
        <Button
            v-if="imageUrl || error"
            type="button"
            variant="destructive-outline"
            class="min-h-11"
            @click="clear"
        >
            Hapus foto
        </Button>
    </div>
</template>
