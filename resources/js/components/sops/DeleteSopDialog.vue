<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { AlertTriangle, LoaderCircle } from '@lucide/vue';
import { computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    sop: { id: string; name: string } | null;
}>();
const emit = defineEmits<{ close: [] }>();
const form = useForm({});
const error = computed(() => (form.errors as Record<string, string | undefined>).delete);

watch(
    () => props.sop?.id,
    () => form.clearErrors(),
);

function remove(): void {
    if (!props.sop) return;
    form.delete(`/admin/sops/${props.sop.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Dialog
        :open="!!sop"
        @update:open="
            (open) => {
                if (!open && !form.processing) emit('close');
            }
        "
    >
        <DialogContent
            class="admin-dashboard-theme rounded-xl bg-card text-foreground"
            :show-close-button="!form.processing"
            @escape-key-down="
                (event) => {
                    if (form.processing) event.preventDefault();
                }
            "
            @interact-outside="
                (event) => {
                    if (form.processing) event.preventDefault();
                }
            "
        >
            <div class="flex size-12 items-center justify-center rounded-xl bg-error/10 text-error">
                <AlertTriangle class="size-6" />
            </div>
            <DialogTitle>Hapus template SOP?</DialogTitle>
            <DialogDescription class="text-muted-foreground">
                Template <strong>{{ sop?.name }}</strong> beserta seluruh versi dan
                langkahnya akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.
            </DialogDescription>
            <p v-if="error" role="alert" class="rounded-xl bg-error/10 p-3 text-sm text-error">
                {{ error }}
            </p>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    class="rounded-xl"
                    :disabled="form.processing"
                    @click="emit('close')"
                >
                    Batal
                </Button>
                <Button
                    variant="destructive"
                    type="button"
                    class="rounded-xl"
                    :disabled="form.processing"
                    @click="remove"
                >
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    {{ form.processing ? 'Menghapus.' : 'Ya, hapus SOP' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
