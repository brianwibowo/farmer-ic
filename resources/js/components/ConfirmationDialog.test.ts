import { renderToString } from '@vue/server-renderer';
import type { SSRContext } from '@vue/server-renderer';
import { createSSRApp } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import ConfirmationDialog from './ConfirmationDialog.vue';
import { requestConfirmation, resolveConfirmation } from '@/composables/useConfirmation';

const sweetAlert = vi.hoisted(() => ({ fire: vi.fn(), close: vi.fn() }));
vi.mock('sweetalert2', () => ({ default: sweetAlert }));

afterEach(() => {
    resolveConfirmation(false);
    vi.clearAllMocks();
});

describe('confirmation SSR hydration boundary', () => {
    it('does not send a body teleport with the initial server-rendered page', async () => {
        const context: SSRContext = {};
        await renderToString(createSSRApp(ConfirmationDialog), context);

        expect(context.teleports?.body).toBeUndefined();
        expect(sweetAlert.fire).not.toHaveBeenCalled();
        expect(sweetAlert.close).not.toHaveBeenCalled();
    });

    it('keeps pending client confirmation state out of server body markup', async () => {
        const decision = requestConfirmation();
        const context: SSRContext = {};
        const html = await renderToString(createSSRApp(ConfirmationDialog), context);

        expect(context.teleports?.body).toBeUndefined();
        expect(html).not.toContain('app-confirmation-target');
        expect(sweetAlert.fire).not.toHaveBeenCalled();
        resolveConfirmation(false);
        expect(await decision).toBe(false);
    });
});
