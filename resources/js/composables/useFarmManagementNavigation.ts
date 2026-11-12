import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Both portals share the same pages, within their own authorized routes. */
export function useFarmManagementNavigation() {
    const page = usePage();
    const indexUrl = computed(() =>
        page.props.navigation?.owner ? '/kebun' : '/admin/farm-management',
    );
    const title = computed(() =>
        page.props.navigation?.owner ? 'Lahan' : 'Manajemen Kebun',
    );

    return { indexUrl, title };
}
