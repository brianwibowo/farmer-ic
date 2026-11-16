export type FarmLocationCandidate = {
    label: string;
    latitude: number;
    longitude: number;
    approximate: boolean;
    matches_region: boolean;
    can_auto_select: boolean;
    search_stage: 'address' | 'place' | 'region';
};

export async function searchFarmLocation(
    address: string,
    districtCode: string,
    signal: AbortSignal,
    farmId?: string,
): Promise<FarmLocationCandidate[]> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const token = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    )?.content;
    if (token) headers['X-CSRF-TOKEN'] = token;
    else {
        const cookie = document.cookie
            .split('; ')
            .find((value) => value.startsWith('XSRF-TOKEN='));
        if (cookie)
            headers['X-XSRF-TOKEN'] = decodeURIComponent(
                cookie.slice('XSRF-TOKEN='.length),
            );
    }
    const response = await fetch(
        farmId
            ? `/admin/farm-management/${farmId}/geocode`
            : '/admin/farm-management/geocode',
        {
            method: 'POST',
            credentials: 'same-origin',
            signal,
            headers,
            body: JSON.stringify({ address, district_code: districtCode }),
        },
    );
    if (!response.ok) {
        const messages: Record<number, string> = {
            401: 'Sesi berakhir. Masuk kembali untuk mencari lokasi.',
            403: 'Anda tidak memiliki izin mencari lokasi untuk kebun ini.',
            419: 'Sesi berakhir. Muat ulang halaman sebelum mencari lokasi.',
            422: 'Periksa alamat dan pilih kecamatan dari daftar wilayah.',
            429: 'Pencarian sedang sibuk. Tunggu sebentar lalu coba lagi atau pilih pin manual.',
        };
        throw new Error(
            messages[response.status] ??
                'Lokasi belum dapat dicari. Coba lagi atau pilih pin secara manual.',
        );
    }
    const result: { data: FarmLocationCandidate[] } = await response.json();
    return result.data;
}
