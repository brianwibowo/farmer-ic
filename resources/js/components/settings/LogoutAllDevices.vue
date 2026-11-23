<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const form = useForm({});
const error = ref('');
const open = ref(false);
const page = usePage();

function setOpen(value: boolean): void {
    if (form.processing) return;
    open.value = value;
    if (value) error.value = '';
}

function logoutAll(): void {
    if (form.processing) return;
    error.value = '';
    router.flushAll();
    form.post('/settings/logout-all-devices', {
        onSuccess: () => router.flushAll(),
        onError: () => { error.value = 'Belum berhasil keluar dari semua perangkat. Coba lagi.'; },
        onHttpException: () => { error.value = 'Belum berhasil keluar dari semua perangkat. Coba lagi.'; return false; },
        onNetworkError: () => { error.value = 'Koneksi terputus. Periksa internet lalu coba lagi.'; return false; },
    });
}
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-semibold text-foreground">Perangkat yang masuk</h2>
        <p class="mt-1 text-sm text-muted-foreground">Akhiri sesi dan akses login tersimpan di semua perangkat, termasuk perangkat ini.</p>
        <Dialog :open="open" @update:open="setOpen">
            <DialogTrigger as-child>
                <Button type="button" variant="destructive" class="mt-4 min-h-11 max-w-full whitespace-normal" data-test="logout-all-devices-button">
                    Keluar dari semua perangkat
                </Button>
            </DialogTrigger>
            <DialogContent
                :class="['admin-dashboard-theme', { 'petugas-dashboard-theme': page.props.navigation?.petugas }]"
                :show-close-button="!form.processing"
                @escape-key-down="form.processing && $event.preventDefault()"
                @pointer-down-outside="form.processing && $event.preventDefault()"
            >
                <DialogHeader>
                    <DialogTitle>Keluar dari semua perangkat?</DialogTitle>
                    <DialogDescription>
                        Anda akan keluar dari perangkat ini dan semua perangkat lain. Anda perlu masuk kembali untuk menggunakan akun ini.
                    </DialogDescription>
                </DialogHeader>
                <p v-if="error" role="alert" class="text-sm text-error">{{ error }}</p>
                <DialogFooter>
                    <Button type="button" variant="outline" class="min-h-11" :disabled="form.processing" @click="setOpen(false)">
                        Batal
                    </Button>
                    <Button type="button" variant="destructive" class="min-h-11 max-w-full whitespace-normal" :disabled="form.processing" data-test="confirm-logout-all-devices" @click="logoutAll">
                        {{ form.processing ? 'Sedang keluar…' : 'Keluar dari semua perangkat' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
