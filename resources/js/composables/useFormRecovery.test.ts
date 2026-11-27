import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test';

const harness = vi.hoisted(() => ({
    before: null as null | ((event: any) => boolean | void),
    mounted: [] as Array<() => void>,
    unmounted: [] as Array<() => void>,
    remove: vi.fn(),
    visit: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({ router: {
    on: vi.fn((_name, callback) => { harness.before = callback; return harness.remove; }),
    visit: harness.visit,
} }));
vi.mock('vue', async (original) => ({
    ...await original<typeof import('vue')>(),
    onMounted: (callback: () => void) => harness.mounted.push(callback),
    onBeforeUnmount: (callback: () => void) => harness.unmounted.push(callback),
}));

import { useUnsavedChanges } from './useFormRecovery';
import { confirmationState, resolveConfirmation } from './useConfirmation';

function navigation(method = 'get') {
    return { detail: { visit: { url: new URL('http://localhost/admin/farm-management?sort=name'), method, data: {}, preserveScroll: true } }, preventDefault: vi.fn() };
}

beforeEach(() => {
    vi.clearAllMocks();
    harness.mounted = [];
    harness.unmounted = [];
    vi.stubGlobal('window', { addEventListener: vi.fn(), removeEventListener: vi.fn() });
});
afterEach(() => {
    harness.unmounted.forEach((dispose) => dispose());
    resolveConfirmation(false);
    vi.unstubAllGlobals();
});

describe('unsaved form navigation', () => {
    it('keeps the current form when the user cancels', async () => {
        useUnsavedChanges(() => true);
        const event = navigation();
        expect(harness.before?.(event)).toBe(false);
        expect(event.preventDefault).toHaveBeenCalledOnce();
        expect(confirmationState.open).toBe(true);
        resolveConfirmation(false);
        await Promise.resolve();
        expect(harness.visit).not.toHaveBeenCalled();
    });

    it('resumes the selected destination once without showing a second confirmation', async () => {
        useUnsavedChanges(() => true);
        const event = navigation();
        harness.visit.mockImplementationOnce((_url, visit) => {
            const resumed = { detail: { visit }, preventDefault: vi.fn() };
            expect(harness.before?.(resumed)).toBeUndefined();
            expect(resumed.preventDefault).not.toHaveBeenCalled();
        });
        harness.before?.(event);
        harness.before?.(navigation());
        resolveConfirmation(true);
        await Promise.resolve();
        expect(harness.visit).toHaveBeenCalledExactlyOnceWith(event.detail.visit.url, event.detail.visit);
    });

    it('allows Batal again after choosing to stay, then leaves only after explicit confirmation', async () => {
        useUnsavedChanges(() => true);
        for (let attempt = 0; attempt < 2; attempt++) {
            const event = navigation();
            expect(harness.before?.(event)).toBe(false);
            expect(confirmationState.open).toBe(true);
            resolveConfirmation(false);
            await Promise.resolve();
            expect(confirmationState.open).toBe(false);
            expect(harness.visit).not.toHaveBeenCalled();
        }
        const lastAttempt = navigation();
        harness.before?.(lastAttempt);
        expect(confirmationState.open).toBe(true);
        resolveConfirmation(true);
        await Promise.resolve();
        expect(harness.visit).toHaveBeenCalledExactlyOnceWith(lastAttempt.detail.visit.url, lastAttempt.detail.visit);
    });

    it('leaves clean navigation and form submissions untouched', () => {
        let dirty = false;
        useUnsavedChanges(() => dirty);
        expect(harness.before?.(navigation())).toBeUndefined();
        dirty = true;
        expect(harness.before?.(navigation('post'))).toBeUndefined();
        expect(confirmationState.open).toBe(false);
    });

    it('does not navigate after the guarded form has been unmounted', async () => {
        useUnsavedChanges(() => true);
        harness.before?.(navigation());
        harness.unmounted.forEach((dispose) => dispose());
        resolveConfirmation(true);
        await Promise.resolve();
        expect(harness.visit).not.toHaveBeenCalled();
        expect(confirmationState.open).toBe(false);
    });
});
