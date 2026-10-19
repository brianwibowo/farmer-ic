import { router } from '@inertiajs/vue3';
import type { FarmerNavKey } from '@/components/farmer/FarmerBottomNav.vue';
import { finance, home } from '@/routes';

const farmerRoutes: Partial<Record<FarmerNavKey, string>> = {
    home: home().url,
    ai: home().url,
    finance: finance().url,
    report: '/laporan',
};

export function useFarmerNavigation() {
    function navigate(key: FarmerNavKey): void {
        const url = farmerRoutes[key];

        if (url) {
            router.visit(url);
        }
    }

    return { navigate };
}
