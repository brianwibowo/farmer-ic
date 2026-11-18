import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { computed, effectScope, reactive } from 'vue';
import { useFarmGeocoding } from './useFarmGeocoding';
import {
    searchFarmLocation,
    type FarmLocationCandidate,
} from '@/services/farmGeocodingService';

vi.mock('@/services/farmGeocodingService', () => ({
    searchFarmLocation: vi.fn(),
}));
const search = vi.mocked(searchFarmLocation);
const candidate: FarmLocationCandidate = {
    label: 'Sukamakmur, Bogor',
    latitude: -6.579,
    longitude: 107.002,
    approximate: false,
    matches_region: true,
    can_auto_select: true,
    search_stage: 'address',
};
const scopes: ReturnType<typeof effectScope>[] = [];

function setup(saved: [number, number] | null = null) {
    const state = reactive({
        address: '',
        districtCode: '',
        valid: true,
        disabled: false,
        point: saved,
    });
    const scope = effectScope();
    scopes.push(scope);
    const geocoding = scope.run(() =>
        useFarmGeocoding({
            address: () => state.address,
            districtCode: () => state.districtCode,
            regionValid: () => state.valid,
            disabled: () => state.disabled,
            location: computed({
                get: () => state.point,
                set: (point) => {
                    state.point = point;
                },
            }),
        }),
    )!;
    function fill() {
        state.address = 'Jalan Raya Sukamakmur';
        state.districtCode = '32.01.09';
    }
    return { state, geocoding, fill, scope };
}

afterEach(() => {
    scopes.splice(0).forEach((scope) => scope.stop());
    vi.resetAllMocks();
});

describe('completed farm address geocoding', () => {
    it('automatically places a matching regional fallback and keeps its approximation label until manual correction', async () => {
        const { state, geocoding, fill } = setup();
        fill();
        search.mockResolvedValue([
            { ...candidate, approximate: true, search_stage: 'region' },
        ]);
        await geocoding.completeAddress();
        expect(state.point).toEqual([-6.579, 107.002]);
        expect(geocoding.selected.value?.search_stage).toBe('region');
        expect(geocoding.selected.value?.approximate).toBe(true);
        expect(geocoding.focus.value?.zoom).toBe(11);
        geocoding.beginManual();
        // Preserve the panel while dragging so the map does not shift under the pointer.
        expect(geocoding.selected.value?.search_stage).toBe('region');
        geocoding.setManual([-6.58, 107.003]);
        expect(geocoding.selected.value).toBeNull();
        expect(state.point).toEqual([-6.58, 107.003]);
        state.address = 'Alamat diperbaiki';
        await geocoding.completeAddress();
        expect(search).toHaveBeenCalledTimes(1);
    });

    it('automatically selects a single place with matching city/province and an unverified district', async () => {
        const { state, geocoding, fill } = setup();
        fill();
        const place: FarmLocationCandidate = {
            ...candidate,
            search_stage: 'place',
            approximate: true,
            matches_region: false,
            can_auto_select: true,
        };
        search.mockResolvedValue([place]);
        await geocoding.completeAddress();
        expect(geocoding.candidates.value).toHaveLength(0);
        expect(geocoding.selected.value).toEqual(place);
        expect(state.point).toEqual([-6.579, 107.002]);
        geocoding.setManual(null);
        expect(geocoding.selected.value).toBeNull();
    });
    it('cancels uncommitted region drafts and can search the completed address again', async () => {
        let resolve!: (rows: FarmLocationCandidate[]) => void;
        search.mockImplementationOnce(
            () =>
                new Promise((done) => {
                    resolve = done;
                }),
        );
        const { state, geocoding, fill } = setup();
        fill();
        const request = geocoding.completeAddress();
        state.valid = false;
        resolve([candidate]);
        await request;
        expect(state.point).toBeNull();
        expect(geocoding.loading.value).toBe(false);
        expect(geocoding.message.value).not.toContain('Mencari');
        state.valid = true;
        search.mockResolvedValue([candidate]);
        await geocoding.completeAddress();
        expect(state.point).toEqual([-6.579, 107.002]);
    });
    it('waits for completion and complete fields, then searches once and focuses the pin', async () => {
        search.mockResolvedValue([candidate]);
        const { state, geocoding, fill } = setup();
        state.address = 'Jalan';
        await geocoding.completeAddress();
        expect(search).not.toHaveBeenCalled();
        fill();
        expect(search).not.toHaveBeenCalled();
        await geocoding.completeAddress();
        await geocoding.completeAddress();
        expect(search).toHaveBeenCalledTimes(1);
        expect(state.point).toEqual([-6.579, 107.002]);
        expect(geocoding.focus.value?.zoom).toBe(16);
    });

    it('ignores late responses after manual placement or removal and never automatically replaces them', async () => {
        let resolve!: (rows: FarmLocationCandidate[]) => void;
        search.mockImplementation(
            () =>
                new Promise((done) => {
                    resolve = done;
                }),
        );
        const { state, geocoding, fill } = setup();
        fill();
        const request = geocoding.completeAddress();
        geocoding.setManual([-6.5, 107.1]);
        resolve([candidate]);
        await request;
        expect(state.point).toEqual([-6.5, 107.1]);
        geocoding.setManual(null);
        state.address = 'Jalan Baru Sukamakmur';
        await geocoding.completeAddress();
        expect(state.point).toBeNull();
        expect(search).toHaveBeenCalledTimes(1);
        search.mockResolvedValue([candidate]);
        await geocoding.retry();
        expect(state.point).toEqual([-6.579, 107.002]);
    });

    it('discards old addresses even when the provider ignores cancellation', async () => {
        let resolve!: (rows: FarmLocationCandidate[]) => void;
        search.mockImplementationOnce(
            () =>
                new Promise((done) => {
                    resolve = done;
                }),
        );
        const { state, geocoding, fill } = setup();
        fill();
        const oldRequest = geocoding.completeAddress();
        state.address = 'Jalan Baru';
        search.mockResolvedValue([{ ...candidate, latitude: -6.8 }]);
        await geocoding.completeAddress();
        resolve([candidate]);
        await oldRequest;
        expect(state.point).toEqual([-6.8, 107.002]);
    });

    it('requires a choice for ambiguity or region mismatch and labels approximate results', async () => {
        const { state, geocoding, fill } = setup();
        fill();
        search.mockResolvedValue([candidate, { ...candidate, latitude: -6.7 }]);
        await geocoding.completeAddress();
        expect(state.point).toBeNull();
        expect(geocoding.candidates.value).toHaveLength(2);
        search.mockResolvedValue([
            { ...candidate, matches_region: false, can_auto_select: false },
        ]);
        await geocoding.retry();
        expect(state.point).toBeNull();
        geocoding.choose({ ...candidate, approximate: true });
        expect(geocoding.focus.value?.zoom).toBe(11);
        expect(geocoding.message.value).toContain('Perkiraan');
    });

    it('preserves saved pins on edit, empty results and provider failure', async () => {
        const { state, geocoding, fill } = setup([-6.1, 107.1]);
        fill();
        await geocoding.completeAddress();
        expect(search).not.toHaveBeenCalled();
        search.mockResolvedValue([]);
        await geocoding.retry();
        expect(geocoding.message.value).toContain('tidak ditemukan');
        search.mockRejectedValue(new Error('Layanan sibuk'));
        await geocoding.retry();
        expect(geocoding.message.value).toBe('Layanan sibuk');
        expect(state.point).toEqual([-6.1, 107.1]);
    });

    it('cancels pending results when saving or leaving the form', async () => {
        let resolve!: (rows: FarmLocationCandidate[]) => void;
        search.mockImplementation(
            () =>
                new Promise((done) => {
                    resolve = done;
                }),
        );
        const { state, geocoding, fill, scope } = setup();
        fill();
        const request = geocoding.completeAddress();
        state.disabled = true;
        scope.stop();
        resolve([candidate]);
        await request;
        expect(state.point).toBeNull();
        expect(geocoding.loading.value).toBe(false);
    });
});
