import { shallowReactive } from "vue";

type ConfirmationOptions = {
    title?: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
};

export const confirmationState = shallowReactive({
    open: false,
    title: "",
    description: "",
    confirmLabel: "",
    cancelLabel: "",
});

let resolvePending: ((confirmed: boolean) => void) | null = null;
let removeAbortListener: (() => void) | null = null;

export function resolveConfirmation(confirmed: boolean): void {
    const resolve = resolvePending;
    resolvePending = null;
    removeAbortListener?.();
    removeAbortListener = null;
    confirmationState.open = false;
    resolve?.(confirmed);
}

export function requestConfirmation(
    options: ConfirmationOptions = {},
    signal?: AbortSignal,
): Promise<boolean> {
    // Keep the first pending decision and ignore repeated navigation/close clicks.
    if (resolvePending || signal?.aborted) return Promise.resolve(false);

    return new Promise((resolve) => {
        resolvePending = resolve;
        Object.assign(confirmationState, {
            open: true,
            title: options.title ?? "Perubahan belum disimpan",
            description:
                options.description ??
                "Perubahan yang kamu buat belum disimpan. Jika meninggalkan halaman ini, perubahan tersebut akan hilang.",
            confirmLabel: options.confirmLabel ?? "Tinggalkan halaman",
            cancelLabel: options.cancelLabel ?? "Tetap di halaman",
        });
        if (signal) {
            const abort = () => resolveConfirmation(false);
            signal.addEventListener("abort", abort, { once: true });
            removeAbortListener = () => signal.removeEventListener("abort", abort);
        }
    });
}
