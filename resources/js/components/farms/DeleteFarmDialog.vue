<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { AlertTriangle, LoaderCircle } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';

const props = defineProps<{ farm: { id: string; name: string } | null }>();
const emit = defineEmits<{ close: [] }>();
const form = useForm({});
const deleteError = computed(() => (form.errors as Record<string, string | undefined>).delete);
watch(() => props.farm?.id, () => form.clearErrors());
function remove(): void {
    if (!props.farm) return;
    form.delete(`/admin/farm-management/${props.farm.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Dialog :open="!!farm" @update:open="(open) => { if (!open && !form.processing) emit('close'); }">
        <DialogContent class="admin-dashboard-theme rounded-xl bg-card text-foreground" :show-close-button="!form.processing" @escape-key-down="(event) => { if (form.processing) event.preventDefault(); }" @interact-outside="(event) => { if (form.processing) event.preventDefault(); }">
            <div class="flex size-12 items-center justify-center rounded-xl bg-error/10 text-error"><AlertTriangle class="size-6" /></div>
            <DialogTitle>Hapus kebun?</DialogTitle>
            <DialogDescription class="text-muted-foreground">Kebun <strong>{{ farm?.name }}</strong> akan dihapus permanen. Kebun yang memiliki histori operasional tidak dapat dihapus.</DialogDescription>
            <p v-if="deleteError" role="alert" class="rounded-xl bg-error/10 p-3 text-sm text-error">{{ deleteError }}</p>
            <DialogFooter>
                <Button type="button" variant="outline" class="rounded-xl" :disabled="form.processing" @click="emit('close')">Batal</Button>
                <Button type="button" class="rounded-xl bg-error text-destructive-foreground hover:bg-error/90" :disabled="form.processing" @click="remove"><LoaderCircle v-if="form.processing" class="animate-spin" />{{ form.processing ? 'Menghapus…' : 'Ya, hapus kebun' }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
