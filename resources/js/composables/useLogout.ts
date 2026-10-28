import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { logout } from '@/routes';

export function useLogout() {
    const form = useForm({});
    const error = ref('');

    function signOut(): void {
        if (form.processing) return;
        error.value = '';
        router.flushAll();
        form.post(logout().url, {
            preserveState: false,
            onSuccess: () => router.flushAll(),
            onError: () => { error.value = 'Belum berhasil keluar. Coba lagi.'; },
            onHttpException: () => {
                error.value = 'Belum berhasil keluar. Muat ulang halaman lalu coba lagi.';
                return false;
            },
            onNetworkError: () => {
                error.value = 'Koneksi terputus. Periksa internet lalu coba lagi.';
                return false;
            },
        });
    }

    return { signOut, processing: computed(() => form.processing), error };
}
