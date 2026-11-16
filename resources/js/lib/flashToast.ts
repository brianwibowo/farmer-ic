import { router } from '@inertiajs/vue3';
import { toast } from '@/lib/toast';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('navigate', (event) => {
        if (event.detail.type !== 'initial') {
            toast.dismiss();
        }
    });

    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        void toast[data.type](data.message);
    });
}
