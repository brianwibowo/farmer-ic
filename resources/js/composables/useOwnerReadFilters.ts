import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from '@/lib/toast';

export function useOwnerReadFilters<T extends Record<string, string>>(
    path: string,
    initial: T,
) {
    const filters = ref<T>({ ...initial });
    const loading = ref(false);
    const error = ref('');
    const feedback = ref('');

    function sync(values: T): void {
        filters.value = { ...values };
    }

    function submit(validate?: () => string | null): void {
        if (loading.value) return;
        error.value = validate?.() ?? '';
        feedback.value = '';
        if (error.value) {
            void toast.warning(error.value);
            return;
        }

        const data: Record<string, string> = {};
        for (const key of Object.keys(filters.value)) {
            const value = filters.value[key];
            if (typeof value === 'string' && value !== '') data[key] = value;
        }
        const fail = (): false => {
            const message =
                'Data belum berhasil dimuat. Periksa koneksi lalu coba lagi.';
            error.value = message;
            void toast.error(message);
            return false;
        };
        router.get(path, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onSuccess: () => {
                void toast.success('Data berhasil diperbarui.');
            },
            onError: (errors) => {
                const message =
                    Object.values(errors).join(' ') ||
                    'Filter belum dapat diterapkan.';
                error.value = message;
                void toast.error(message);
            },
            onHttpException: fail,
            onNetworkError: fail,
            onFinish: () => (loading.value = false),
        });
    }

    function reset(defaults: T): void {
        if (loading.value) return;
        sync(defaults);
        submit();
    }

    return { filters, loading, error, feedback, submit, reset, sync };
}
