import { router } from '@inertiajs/vue3';
import { onScopeDispose, reactive, ref, watch } from 'vue';

export type PetugasReportFilters = {
    q?: string | null;
    type?: string | null;
    date?: string | null;
    source?: string | null;
    mine?: boolean;
    per_page?: number | null;
};

type FilterState = {
    q: string;
    type: string;
    date: string;
    source: string;
    mine: boolean;
    per_page: number;
};

function normalize(filters: PetugasReportFilters): FilterState {
    return {
        q: filters.q ?? '',
        type: filters.type ?? '',
        date: filters.date ?? '',
        source: filters.source ?? '',
        mine: !!filters.mine,
        per_page: filters.per_page ?? 10,
    };
}

function sameFilters(a: FilterState, b: FilterState): boolean {
    return (
        a.q.trim() === b.q.trim() &&
        a.type === b.type &&
        a.date === b.date &&
        a.source === b.source &&
        a.mine === b.mine &&
        a.per_page === b.per_page
    );
}

export function usePetugasReportFilters(
    currentFilters: () => PetugasReportFilters,
    enabled: () => boolean,
) {
    const filter = reactive(normalize(currentFilters()));
    const loading = ref(false);
    const error = ref('');
    let timer: ReturnType<typeof setTimeout> | undefined;
    let cancelVisit: (() => void) | undefined;
    let revision = 0;
    let syncing = false;
    let requestedPage = 1;
    let replaceHistory = true;

    function cancelPending(): void {
        revision++;
        if (timer !== undefined) clearTimeout(timer);
        timer = undefined;
        const cancel = cancelVisit;
        cancelVisit = undefined;
        cancel?.();
    }

    function load(page = 1, replace = true): void {
        timer = undefined;
        requestedPage = page;
        replaceHistory = replace;
        const currentRevision = revision;
        const data: Record<string, string | number> = {};
        const query = filter.q.trim();
        if (query) data.q = query;
        if (filter.type) data.type = filter.type;
        if (filter.date) data.date = filter.date;
        if (filter.source) data.source = filter.source;
        if (filter.mine) data.mine = 1;
        if (filter.per_page !== 10) data.per_page = filter.per_page;
        if (page > 1) data.page = page;

        const fail = (): false => {
            if (revision === currentRevision) {
                error.value =
                    'Laporan belum berhasil dimuat. Silakan coba lagi.';
            }
            return false;
        };

        router.get('/petugas/laporan', data, {
            preserveState: true,
            preserveScroll: true,
            replace,
            onCancelToken: (token) => {
                if (revision === currentRevision) cancelVisit = token.cancel;
                else token.cancel();
            },
            onError: fail,
            onHttpException: fail,
            onNetworkError: fail,
            onFinish: () => {
                if (revision !== currentRevision) return;
                cancelVisit = undefined;
                loading.value = false;
            },
        });
    }

    function applyNow(): void {
        cancelPending();
        error.value = '';
        if (!enabled() || sameFilters(filter, normalize(currentFilters()))) {
            loading.value = false;
            return;
        }
        loading.value = true;
        load();
    }

    function retry(): void {
        cancelPending();
        if (!enabled()) return;
        error.value = '';
        loading.value = true;
        load(requestedPage, replaceHistory);
    }

    function requestPage(page: number): void {
        if (!enabled() || loading.value) return;
        cancelPending();
        error.value = '';
        loading.value = true;
        load(page, false);
    }

    watch(
        () =>
            [
                filter.q,
                filter.type,
                filter.date,
                filter.source,
                filter.mine,
                filter.per_page,
            ] as const,
        (next, previous) => {
            if (syncing) return;
            cancelPending();
            error.value = '';
            if (
                !enabled() ||
                sameFilters(filter, normalize(currentFilters()))
            ) {
                loading.value = false;
                return;
            }
            loading.value = true;
            const onlySearchChanged =
                next[0] !== previous[0] &&
                next
                    .slice(1)
                    .every((value, index) => value === previous[index + 1]);
            if (onlySearchChanged) timer = setTimeout(load, 300);
            else load();
        },
        { flush: 'sync' },
    );

    watch(
        currentFilters,
        (serverFilters) => {
            const updated = normalize(serverFilters);
            if (sameFilters(filter, updated)) return;
            cancelPending();
            syncing = true;
            Object.assign(filter, updated);
            syncing = false;
            loading.value = false;
            error.value = '';
        },
        { flush: 'sync' },
    );

    onScopeDispose(cancelPending);

    return { filter, loading, error, applyNow, retry, requestPage };
}
