import { describe, expect, it, vi } from 'vite-plus/test';
import { createRenderer, h } from 'vue';
import type { ExecutiveSummaryData } from '@/types/owner-executive';
import { useExecutiveSummary } from './useExecutiveSummary';

const mocks = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router: { get: mocks.get } }));
vi.mock('@/lib/toast', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

describe('executive summary automatic refresh', () => {
    it('refreshes visible pages every five minutes with current filters and stops on unmount', () => {
        vi.useFakeTimers();
        const page = { visibilityState: 'visible' };
        vi.stubGlobal('document', page);
        const summary = {
            filters: {
                year: 2026,
                quarter: 'all',
                partner: '',
                region: '',
                farm: 'farm-a',
                commodity: '',
            },
            farms: [],
            rows: [],
        } as unknown as ExecutiveSummaryData;
        const renderer = createRenderer({
            createElement: () => ({}),
            createText: () => ({}),
            createComment: () => ({}),
            setText: () => {},
            setElementText: () => {},
            parentNode: () => null,
            nextSibling: () => null,
            insert: () => {},
            remove: () => {},
            patchProp: () => {},
        });
        const app = renderer.createApp({
            setup() {
                useExecutiveSummary(() => summary);
                return () => h('div');
            },
        });
        app.mount({});

        vi.advanceTimersByTime(299_999);
        expect(mocks.get).not.toHaveBeenCalled();
        vi.advanceTimersByTime(1);
        expect(mocks.get).toHaveBeenCalledWith(
            '/ringkasan-eksekutif',
            expect.objectContaining({ year: 2026, farm: 'farm-a' }),
            expect.objectContaining({
                preserveState: true,
                preserveScroll: true,
                only: ['summary'],
            }),
        );
        page.visibilityState = 'hidden';
        vi.advanceTimersByTime(300_000);
        expect(mocks.get).toHaveBeenCalledTimes(1);
        app.unmount();
        page.visibilityState = 'visible';
        vi.advanceTimersByTime(300_000);
        expect(mocks.get).toHaveBeenCalledTimes(1);
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });
});
