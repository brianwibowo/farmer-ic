// Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/composables/useAppearance.ts
import { computed, ref } from 'vue';
// Snapshot-only, in-memory theme. Never alter the live application's preference.
export function useAppearance() {
    const appearance = ref<'light' | 'dark'>('light');
    return { appearance, resolvedAppearance: computed(() => appearance.value) };
}
