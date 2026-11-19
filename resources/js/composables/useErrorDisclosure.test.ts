import { effectScope, nextTick, ref } from 'vue';
import { describe, expect, it } from 'vitest';
import { useErrorDisclosure } from './useErrorDisclosure';

describe('error disclosure', () => {
    it('opens for errors but stays open while they are corrected', async () => {
        const error = ref(false);
        const scope = effectScope();
        const state = scope.run(() => useErrorDisclosure(() => error.value))!;

        expect(state.open.value).toBe(false);
        error.value = true;
        await nextTick();
        expect(state.open.value).toBe(true);
        error.value = false;
        await nextTick();
        expect(state.open.value).toBe(true);

        state.onToggle({ currentTarget: { open: false } } as unknown as Event);
        expect(state.open.value).toBe(false);
        error.value = true;
        await nextTick();
        expect(state.open.value).toBe(true);
        scope.stop();
    });
});
