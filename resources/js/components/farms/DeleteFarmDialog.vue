<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { AlertTriangle, LoaderCircle } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{ farm: { id: string; name: string } | null }>();
const emit = defineEmits<{ close: [] }>();
const form = useForm({});
const deleteError = computed(
    () => (form.errors as Record<string, string | undefined>).delete,
);
const checking = ref(false);
const checked = ref(false);
const blockers = ref<string[]>([]);
const checkError = ref('');
let request: AbortController | undefined;
async function check(): Promise<void> {
    request?.abort();
    const id = props.farm?.id;
    if (!id) return;
    const current = new AbortController();
    request = current;
    checking.value = true;
    checked.value = false;
    checkError.value = '';
    blockers.value = [];
    try {
        const response = await fetch(
            `/admin/farm-management/${id}/deletion-check`,
            { signal: current.signal, headers: { Accept: 'application/json' } },
        );
        if (!response.ok) throw new Error('Pemeriksaan gagal');
        const data = await response.json();
        if (props.farm?.id === id && !current.signal.aborted) {
            blockers.value = data.blockers;
            checked.value = true;
        }
    } catch (error) {
        if (!current.signal.aborted)
            checkError.value =
                'Dampak penghapusan belum dapat diperiksa. Coba lagi sebelum melanjutkan.';
    } finally {
        if (props.farm?.id === id) checking.value = false;
    }
}
watch(
    () => props.farm?.id,
    () => {
        form.clearErrors();
        request?.abort();
        checked.value = false;
        if (props.farm) void check();
    },
    { immediate: true },
);
onBeforeUnmount(() => request?.abort());
function remove(): void {
    if (
        !props.farm ||
        !checked.value ||
        checking.value ||
        blockers.value.length ||
        form.processing
    )
        return;
    form.delete(`/admin/farm-management/${props.farm.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Dialog
        :open="!!farm"
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
            <DialogTitle>Hapus kebun?</DialogTitle>
            <DialogDescription class="text-muted-foreground"
                >Kebun <strong>{{ farm?.name }}</strong> akan dihapus permanen.
                Penghapusan hanya diperbolehkan jika belum ada histori
                operasional dan semua penugasan Petugas sudah dilepas atau
                dipindahkan.</DialogDescription
            >
            <p
                v-if="deleteError"
                role="alert"
                class="rounded-xl bg-error/10 p-3 text-sm text-error"
            >
                {{ deleteError }}
            </p>
            <p
                v-if="checking"
                role="status"
                class="text-sm text-muted-foreground"
            >
                Memeriksa histori dan penugasan kebun…
            </p>
            <p
                v-if="blockers.length"
                role="alert"
                class="rounded-xl bg-muted p-3 text-sm text-foreground"
            >
                Belum dapat dihapus karena masih memiliki
                {{ blockers.join(', ') }}. Histori tetap dipertahankan. Lepaskan
                penugasan melalui Manajemen Pengguna bila diperlukan.
            </p>
            <div v-if="checkError" role="alert" class="text-sm">
                <p>{{ checkError }}</p>
                <Button
                    type="button"
                    variant="outline"
                    class="mt-2"
                    @click="check"
                    >Coba lagi</Button
                >
            </div>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    class="rounded-xl"
                    :disabled="form.processing"
                    @click="emit('close')"
                    >Batal</Button
                >
                <Button
                    variant="destructive"
                    type="button"
                    class="rounded-xl"
                    :disabled="
                        form.processing ||
                        checking ||
                        !checked ||
                        !!blockers.length
                    "
                    @click="remove"
                    ><LoaderCircle
                        v-if="form.processing"
                        class="animate-spin"
                    />{{
                        form.processing ? 'Menghapus…' : 'Hapus kebun permanen'
                    }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
