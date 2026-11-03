import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useActivityDate() {
    const page = usePage();
    return computed(() => {
        const value = new URLSearchParams(page.url.split('?')[1] ?? '').get(
            'work_date',
        );
        return value && /^\d{4}-\d{2}-\d{2}$/.test(value)
            ? `?work_date=${value}`
            : '';
    });
}
