import { afterEach, describe, expect, it } from 'vitest';
import { confirmationState, requestConfirmation, resolveConfirmation } from './useConfirmation';

afterEach(() => resolveConfirmation(false));

describe('confirmation decisions', () => {
    it('keeps the first decision when repeated clicks request another confirmation', async () => {
        const decision = requestConfirmation({ title: 'Perubahan blok' });
        expect(confirmationState.open).toBe(true);
        expect(await requestConfirmation({ title: 'Permintaan kedua' })).toBe(false);
        expect(confirmationState.title).toBe('Perubahan blok');
        resolveConfirmation(true);
        expect(await decision).toBe(true);
        expect(confirmationState.open).toBe(false);
    });

    it('cancels a pending decision when its form is disposed', async () => {
        const lifetime = new AbortController();
        const decision = requestConfirmation({}, lifetime.signal);
        lifetime.abort();
        expect(await decision).toBe(false);
        expect(confirmationState.open).toBe(false);
    });

    it('supports cancellation and a later independent confirmation', async () => {
        const first = requestConfirmation();
        resolveConfirmation(false);
        expect(await first).toBe(false);
        const second = requestConfirmation({ confirmLabel: 'Buang perubahan' });
        expect(confirmationState.confirmLabel).toBe('Buang perubahan');
        resolveConfirmation(true);
        expect(await second).toBe(true);
    });
});
