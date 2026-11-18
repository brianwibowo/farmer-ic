import { router } from "@inertiajs/vue3";
import { nextTick, onBeforeUnmount, onMounted } from "vue";
import { requestConfirmation } from "@/composables/useConfirmation";

export async function focusFormErrors(): Promise<void> {
    await nextTick();
    const target = document.querySelector<HTMLElement>("[data-form-errors]");
    target?.focus();
    target?.scrollIntoView({ block: "center", behavior: "instant" });
}

export function useUnsavedChanges(isDirty: () => boolean): void {
    const lifetime = new AbortController();
    let confirming = false;
    let approved = false;
    const beforeUnload = (event: BeforeUnloadEvent) => {
        if (!isDirty()) return;
        event.preventDefault();
        event.returnValue = "";
    };
    const remove = router.on("before", (event) => {
        if (approved || event.detail.visit.method !== "get" || !isDirty()) return;
        event.preventDefault();
        if (confirming) return false;
        confirming = true;
        const visit = event.detail.visit;
        void requestConfirmation({}, lifetime.signal).then((confirmed) => {
            confirming = false;
            if (!confirmed || lifetime.signal.aborted) return;
            approved = true;
            try {
                router.visit(visit.url, visit);
            } finally {
                approved = false;
            }
        });
        return false;
    });
    onMounted(() => window.addEventListener("beforeunload", beforeUnload));
    onBeforeUnmount(() => {
        lifetime.abort();
        remove();
        window.removeEventListener("beforeunload", beforeUnload);
    });
}
