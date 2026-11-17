import { ref, watch } from 'vue';

/** Open a disclosure for validation errors without closing it after correction. */
export function useErrorDisclosure(hasError: () => boolean) {
    const open = ref(false);

    watch(
        hasError,
        (invalid) => {
            if (invalid) open.value = true;
        },
        { immediate: true },
    );

    function onToggle(event: Event): void {
        open.value = (event.currentTarget as HTMLDetailsElement).open;
    }

    return { open, onToggle };
}
