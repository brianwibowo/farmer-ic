import { computed, onScopeDispose, ref, shallowRef, watch } from 'vue';
import type { WritableComputedRef } from 'vue';
import {
    searchFarmLocation,
    type FarmLocationCandidate,
} from '@/services/farmGeocodingService';

export type FarmMapFocus = { point: [number, number]; zoom: number };

export function useFarmGeocoding(options: {
    address: () => string;
    districtCode: () => string;
    regionValid: () => boolean;
    disabled: () => boolean;
    location: WritableComputedRef<[number, number] | null>;
    farmId?: string;
    notify?: (message: string) => void;
}) {
    const loading = ref(false);
    const message = ref('');
    const candidates = ref<FarmLocationCandidate[]>([]);
    const selected = shallowRef<FarmLocationCandidate | null>(null);
    const focus = shallowRef<FarmMapFocus | null>(null);
    const manual = ref(!!options.location.value);
    const signature = computed(() =>
        JSON.stringify([options.address().trim(), options.districtCode()]),
    );
    const ready = computed(
        () =>
            options.address().trim().length >= 3 &&
            !!options.districtCode() &&
            options.regionValid(),
    );
    let lastAttempt = signature.value;
    let generation = 0;
    let controller: AbortController | undefined;
    let pending: Promise<void> | undefined;

    function cancel(invalidateAttempt = false): void {
        if (loading.value) {
            if (invalidateAttempt) lastAttempt = '';
            message.value = options.location.value
                ? 'Pin sebelumnya dipertahankan. Periksa posisinya sebelum menyimpan.'
                : '';
        }
        generation++;
        controller?.abort();
        controller = undefined;
        loading.value = false;
        pending = undefined;
    }

    watch(
        signature,
        () => {
            cancel(true);
            candidates.value = [];
            focus.value = null;
            message.value = options.location.value
                ? 'Alamat berubah. Pin sebelumnya masih ditampilkan; periksa atau cari ulang dari alamat.'
                : '';
        },
        { flush: 'sync' },
    );
    watch(
        options.disabled,
        (disabled) => {
            if (disabled) cancel(true);
        },
        { flush: 'sync' },
    );
    watch(
        ready,
        (valid) => {
            if (!valid) {
                cancel(true);
                candidates.value = [];
            }
        },
        { flush: 'sync' },
    );

    function choose(candidate: FarmLocationCandidate): void {
        cancel();
        candidates.value = [];
        selected.value = candidate;
        options.location.value = [candidate.latitude, candidate.longitude];
        focus.value = {
            point: [candidate.latitude, candidate.longitude],
            zoom: candidate.approximate ? 11 : 16,
        };
        message.value = candidate.approximate
            ? 'Perkiraan lokasi ditemukan. Geser pin ke titik lahan yang tepat.'
            : 'Pin ditemukan dari alamat. Periksa posisinya dan geser bila perlu.';
        if (candidate.approximate) options.notify?.(message.value);
    }

    function beginManual(): void {
        cancel();
        manual.value = true;
        focus.value = null;
    }

    function setManual(point: [number, number] | null): void {
        beginManual();
        selected.value = null;
        candidates.value = [];
        focus.value = null;
        options.location.value = point;
        message.value = point
            ? 'Posisi pin manual digunakan. Simpan kebun untuk menyimpan titik ini.'
            : 'Pin dihapus. Pilih titik manual atau cari ulang dari alamat untuk memasangnya kembali.';
    }

    function search(force = false): Promise<void> {
        if (options.disabled() || !ready.value) return Promise.resolve();
        if (!force && signature.value === lastAttempt)
            return pending ?? Promise.resolve();
        if (!force && manual.value) return Promise.resolve();
        cancel();
        const current = generation;
        const requestSignature = signature.value;
        lastAttempt = requestSignature;
        controller = new AbortController();
        const signal = controller.signal;
        loading.value = true;
        candidates.value = [];
        message.value = 'Mencari lokasi dari alamat…';
        pending = (async () => {
            try {
                const results = await searchFarmLocation(
                    options.address().trim(),
                    options.districtCode(),
                    signal,
                    options.farmId,
                );
                if (
                    current !== generation ||
                    signal.aborted ||
                    signature.value !== requestSignature
                )
                    return;
                if (results.length === 1 && results[0].can_auto_select) {
                    choose(results[0]);
                } else {
                    candidates.value = results;
                    message.value = results.length
                        ? 'Pilih hasil yang sesuai dengan alamat dan wilayah kebun.'
                        : 'Lokasi tidak ditemukan. Perjelas alamat atau pilih pin secara manual.';
                    if (!results.length) options.notify?.(message.value);
                }
            } catch (error) {
                if (current === generation && !signal.aborted) {
                    message.value =
                        error instanceof Error
                            ? error.message
                            : 'Lokasi belum dapat dicari. Pilih pin manual atau coba lagi.';
                    options.notify?.(message.value);
                }
            } finally {
                if (current === generation) {
                    loading.value = false;
                    pending = undefined;
                }
            }
        })();
        return pending;
    }

    onScopeDispose(() => cancel());
    return {
        loading,
        message,
        candidates,
        selected,
        focus,
        ready,
        completeAddress: () => search(),
        retry: () => search(true),
        choose,
        beginManual,
        setManual,
        cancel,
    };
}
