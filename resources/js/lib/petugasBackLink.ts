const DEFAULT_ALLOW = /^\/petugas(?:\/|\?|$)/;

function parsePetugasPath(value: string, allow: RegExp): URL | null {
    if (!value || value.startsWith('//') || value.includes('\\')) {
        return null;
    }

    if (!allow.test(value)) {
        return null;
    }

    try {
        const parsed = new URL(value, 'http://localhost');
        if (parsed.origin !== 'http://localhost') {
            return null;
        }

        const normalized = `${parsed.pathname}${parsed.search}${parsed.hash}`;

        return allow.test(normalized) ? parsed : null;
    } catch {
        return null;
    }
}

export function extractPetugasBackParam(
    currentUrl: string,
    allow: RegExp = DEFAULT_ALLOW,
): string | null {
    const parsed = parsePetugasPath(currentUrl, /^\/petugas(?:\/|\?|$)/);
    if (!parsed) {
        return null;
    }

    const candidate = parsed.searchParams.get('back');
    if (!candidate) {
        return null;
    }

    const resolved = parsePetugasPath(candidate, allow);

    return resolved
        ? `${resolved.pathname}${resolved.search}${resolved.hash}`
        : null;
}

export function resolvePetugasBackHref(
    currentUrl: string,
    fallback: string,
    allow: RegExp = DEFAULT_ALLOW,
): string {
    return extractPetugasBackParam(currentUrl, allow) ?? fallback;
}

export function withPetugasBack(
    targetHref: string,
    backHref: string | null | undefined,
): string {
    if (!backHref) {
        return targetHref;
    }

    const resolvedBack = parsePetugasPath(backHref, DEFAULT_ALLOW);
    if (!resolvedBack) {
        return targetHref;
    }

    resolvedBack.searchParams.delete('back');
    const cleanBack = `${resolvedBack.pathname}${resolvedBack.search}${resolvedBack.hash}`;
    const target = parsePetugasPath(targetHref, DEFAULT_ALLOW);
    if (!target) {
        return targetHref;
    }

    target.searchParams.set('back', cleanBack);

    return `${target.pathname}${target.search}${target.hash}`;
}

export function petugasBackLabel(href: string, defaultLabel: string): string {
    const parsed = parsePetugasPath(href, DEFAULT_ALLOW);
    if (!parsed) {
        return defaultLabel;
    }

    const pathname = parsed.pathname.replace(/\/$/, '') || '/';

    if (pathname === '/petugas') {
        return 'Kembali ke beranda';
    }
    if (
        pathname === '/petugas/kebun' ||
        pathname.startsWith('/petugas/kebun/')
    ) {
        return 'Kembali ke lahan';
    }
    if (pathname === '/petugas/aktivitas' || pathname === '/petugas/tasks') {
        return 'Kembali ke daftar tugas';
    }
    if (pathname === '/petugas/laporan/harian') {
        return 'Kembali ke laporan harian';
    }
    if (pathname === '/petugas/laporan') {
        return 'Kembali ke laporan';
    }
    if (pathname === '/petugas/incidents') {
        return 'Kembali ke daftar tiket insiden';
    }
    if (pathname.startsWith('/petugas/incidents/')) {
        return 'Kembali ke tiket insiden';
    }
    if (pathname === '/petugas/penanaman') {
        return 'Daftar subblok';
    }
    if (pathname.startsWith('/petugas/tasks/')) {
        return 'Kembali ke tugas';
    }

    return defaultLabel;
}
