import { router } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted } from 'vue';

export async function focusFormErrors(): Promise<void> {
    await nextTick();
    const target = document.querySelector<HTMLElement>('[data-form-errors]');
    target?.focus();
    target?.scrollIntoView({ block: 'center', behavior: 'instant' });
}

export function useUnsavedChanges(isDirty: () => boolean): void {
    const beforeUnload = (event: BeforeUnloadEvent) => {
        if (!isDirty()) return;
        event.preventDefault();
        event.returnValue = '';
    };
    const remove = router.on('before', (event) => {
        if (event.detail.visit.method === 'get' && isDirty()) {
            return window.confirm(
                'Ada perubahan yang belum disimpan. Tinggalkan halaman ini?',
            );
        }
    });
    onMounted(() => window.addEventListener('beforeunload', beforeUnload));
    onBeforeUnmount(() => {
        remove();
        window.removeEventListener('beforeunload', beforeUnload);
    });
}
