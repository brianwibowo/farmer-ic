import { afterEach, describe, expect, it, vi } from 'vitest';
import { viteSsrTiming } from './vite-ssr-timing';

const original = process.env.ACTIVITY_TIMING_ENABLED;
afterEach(() => {
    if (original === undefined) delete process.env.ACTIVITY_TIMING_ENABLED;
    else process.env.ACTIVITY_TIMING_ENABLED = original;
});

describe('Vite SSR timing', () => {
    it.each(['false', '(false)', '0', 'off'])(
        'does not attach when set to %s',
        (value) => {
            process.env.ACTIVITY_TIMING_ENABLED = value;
            const use = vi.fn();
            const hook = viteSsrTiming().configureServer;
            if (typeof hook !== 'function')
                throw new Error('Missing server hook');
            hook({
                config: { mode: 'test', envDir: process.cwd() },
                middlewares: { use },
            } as never);
            expect(use).not.toHaveBeenCalled();
        },
    );

    it('attaches timing only to the SSR endpoint when enabled', () => {
        process.env.ACTIVITY_TIMING_ENABLED = 'true';
        const use = vi.fn();
        const hook = viteSsrTiming().configureServer;
        if (typeof hook !== 'function') throw new Error('Missing server hook');
        hook({
            config: { mode: 'test', envDir: process.cwd() },
            middlewares: { use },
        } as never);
        expect(use).toHaveBeenCalledOnce();
        expect(use.mock.calls[0][0]).toBe('/__inertia_ssr');
    });
});
