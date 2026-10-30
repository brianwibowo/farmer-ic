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
    user: { id: string; name: string; email: string } | null;
}>();
const emit = defineEmits<{ close: [] }>();
const form = useForm({});
const error = computed(() => Object.values(form.errors)[0]);
watch(
    () => props.user?.id,
    () => form.clearErrors(),
);

function remove(): void {
    if (!props.user) return;
    form.delete('/admin/user-management/' + props.user.id, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Dialog
        :open="!!user"
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
            <div
                class="flex size-12 items-center justify-center rounded-xl bg-error/10 text-error"
            >
                <AlertTriangle class="size-6" />
            </div>
            <DialogTitle>Hapus user?</DialogTitle>
            <DialogDescription class="text-muted-foreground">
                Akun <strong>{{ user?.name }}</strong> ({{ user?.email }}) akan
                dihapus dari daftar aktif dan tidak dapat lagi login. Asosiasi tenant
                dan catatan operasional maupun AI tetap tersimpan.
            </DialogDescription>
            <p
                v-if="error"
                role="alert"
                class="rounded-xl bg-error/10 p-3 text-sm text-error"
            >
                {{ error }}
            </p>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    class="rounded-xl"
                    :disabled="form.processing"
                    @click="emit('close')"
                    >Batal</Button
                >
                <Button variant="destructive"
                    type="button"
                    class="rounded-xl"
                    :disabled="form.processing"
                    @click="remove"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="animate-spin"
                    />{{ form.processing ? 'Menghapus…' : 'Ya, hapus user' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
