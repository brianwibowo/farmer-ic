import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { getAppName, resolveAppName } from '@/lib/appName';

export function useAppName() {
    const page = usePage();

    return computed(() => resolveAppName(page.props.name ?? getAppName()));
}
