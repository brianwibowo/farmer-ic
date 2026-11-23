import { effectScope, ref, type EffectScope, type Ref } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    usePetugasReportFilters,
    type PetugasReportFilters,
} from './usePetugasReportFilters';

const mocks = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router: { get: mocks.get } }));

type RequestOptions = {
    onCancelToken: (token: { cancel: () => void }) => void;
    onFinish: () => void;
    onNetworkError: () => false;
    onHttpException: () => false;
    onError: () => false;
    preserveState: boolean;
    preserveScroll: boolean;
    replace: boolean;
};

type Request = {
    data: Record<string, string | number>;
    options: RequestOptions;
    cancel: ReturnType<typeof vi.fn>;
};

let scope: EffectScope;
let serverFilters: Ref<PetugasReportFilters>;
let requests: Request[];
let state: ReturnType<typeof usePetugasReportFilters>;

beforeEach(() => {
    vi.useFakeTimers();
    mocks.get.mockReset();
    requests = [];
    mocks.get.mockImplementation(
        (_url: string, data: Request['data'], options: RequestOptions) => {
            const cancel = vi.fn();
            options.onCancelToken({ cancel });
            requests.push({ data, options, cancel });
        },
    );
    serverFilters = ref<PetugasReportFilters>({});
    scope = effectScope();
    state = scope.run(() =>
        usePetugasReportFilters(
            () => serverFilters.value,
            () => true,
        ),
    )!;
});

afterEach(() => {
    scope.stop();
    vi.useRealTimers();
});

describe('automatic petugas report filters', () => {
    it('debounces search and sends scoped server requests without a page number', () => {
        expect(mocks.get).not.toHaveBeenCalled();
        state.filter.q = '  pan  ';
        vi.advanceTimersByTime(200);
        state.filter.q = '  panen  ';
        vi.advanceTimersByTime(299);
        expect(mocks.get).not.toHaveBeenCalled();
        expect(state.loading.value).toBe(true);

        vi.advanceTimersByTime(1);
        expect(mocks.get).toHaveBeenCalledTimes(1);
        expect(mocks.get).toHaveBeenCalledWith(
            '/petugas/laporan',
            { q: 'panen' },
            expect.objectContaining({
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }),
        );
        serverFilters.value = { q: 'panen' };
        requests[0].options.onFinish();
        expect(state.filter.q).toBe('  panen  ');
        expect(state.loading.value).toBe(false);
    });

    it('applies selection, date, and personal filters immediately', () => {
        state.filter.q = 'tanam';
        state.filter.type = 'planting';
        expect(requests[0].data).toEqual({ q: 'tanam', type: 'planting' });
        vi.advanceTimersByTime(300);
        expect(requests).toHaveLength(1);

        state.filter.date = '2026-11-17';
        state.filter.source = 'sop';
        state.filter.mine = true;
        expect(requests[0].cancel).toHaveBeenCalledOnce();
        expect(requests[1].cancel).toHaveBeenCalledOnce();
        expect(requests[2].cancel).toHaveBeenCalledOnce();
        expect(requests[3].data).toEqual({
            q: 'tanam',
            type: 'planting',
            date: '2026-11-17',
            source: 'sop',
            mine: 1,
        });
    });

    it('cancels stale searches and does not let an old finish clear loading', () => {
        state.filter.q = 'pan';
        vi.advanceTimersByTime(300);
        state.filter.q = 'panen';
        expect(requests[0].cancel).toHaveBeenCalledOnce();
        requests[0].options.onFinish();
        expect(state.loading.value).toBe(true);

        vi.advanceTimersByTime(300);
        expect(requests[1].data).toEqual({ q: 'panen' });
        serverFilters.value = { q: 'panen' };
        requests[1].options.onFinish();
        expect(state.loading.value).toBe(false);
    });

    it('clears individual filters and omits empty parameters', () => {
        serverFilters.value = {
            q: 'jagung',
            type: 'planting',
            date: '2026-11-17',
            source: 'sop',
            mine: true,
        };
        state.filter.source = '';
        expect(requests[0].data).toEqual({
            q: 'jagung',
            type: 'planting',
            date: '2026-11-17',
            mine: 1,
        });
        state.filter.mine = false;
        expect(requests[1].data).not.toHaveProperty('mine');
    });

    it('restores server filters without issuing another visit', () => {
        state.filter.q = 'pending';
        serverFilters.value = { q: 'lama', source: 'mandiri' };
        vi.advanceTimersByTime(300);
        expect(mocks.get).not.toHaveBeenCalled();
        expect(state.filter.q).toBe('lama');
        expect(state.filter.source).toBe('mandiri');
        expect(state.loading.value).toBe(false);
    });

    it('reports failed visits and retries the current filters', () => {
        state.filter.type = 'maintenance';
        expect(requests[0].options.onNetworkError()).toBe(false);
        requests[0].options.onFinish();
        expect(state.error.value).toContain('belum berhasil dimuat');
        expect(state.loading.value).toBe(false);

        state.retry();
        expect(state.error.value).toBe('');
        expect(state.loading.value).toBe(true);
        expect(requests[1].data).toEqual({ type: 'maintenance' });
    });

    it('changes pages with the current filters and preserves the requested page on retry', () => {
        serverFilters.value = { source: 'sop', per_page: 20 };
        state.requestPage(2);
        expect(requests[0].data).toEqual({
            source: 'sop',
            per_page: 20,
            page: 2,
        });
        expect(requests[0].options.replace).toBe(false);
        expect(state.loading.value).toBe(true);
        state.requestPage(3);
        expect(requests).toHaveLength(1);

        expect(requests[0].options.onNetworkError()).toBe(false);
        requests[0].options.onFinish();
        state.retry();
        expect(requests[1].data).toEqual({
            source: 'sop',
            per_page: 20,
            page: 2,
        });
        expect(requests[1].options.replace).toBe(false);
    });

    it('resets to the first page when changing page size and keeps it through later filters', () => {
        serverFilters.value = { q: 'jagung', source: 'mandiri' };
        state.filter.per_page = 20;
        expect(requests[0].data).toEqual({
            q: 'jagung',
            source: 'mandiri',
            per_page: 20,
        });
        serverFilters.value = {
            q: 'jagung',
            source: 'mandiri',
            per_page: 20,
        };
        requests[0].options.onFinish();
        state.filter.mine = true;
        expect(requests[1].data).toEqual({
            q: 'jagung',
            source: 'mandiri',
            mine: 1,
            per_page: 20,
        });
        state.filter.per_page = 10;
        expect(requests[1].cancel).toHaveBeenCalledOnce();
        expect(requests[2].data).toEqual({
            q: 'jagung',
            source: 'mandiri',
            mine: 1,
        });
    });

    it('cancels a page visit when a filter changes', () => {
        state.requestPage(2);
        state.filter.source = 'sop';
        expect(requests[0].cancel).toHaveBeenCalledOnce();
        expect(requests[1].data).toEqual({ source: 'sop' });
        requests[0].options.onFinish();
        expect(state.loading.value).toBe(true);
    });

    it('cleans up a pending search when the page is disposed', () => {
        state.filter.q = 'laporan';
        scope.stop();
        vi.advanceTimersByTime(300);
        expect(mocks.get).not.toHaveBeenCalled();
    });
});
