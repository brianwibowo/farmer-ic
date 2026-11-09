/** URL boundary for the historical snapshot only. Never patch the live router. */
const base = '/referensi';
const paths = new Set(['/', '/kebun', '/kebun/detail', '/kebun/aktivitas', '/aktivitas', '/rincian-aktivitas', '/laporan', '/finance', '/ai', '/bypass']);

export function referenceUrl(input, depth = 0) {
    if (typeof input !== 'string' || !input.startsWith('/') || input.startsWith('//') || (input.includes('\\') || input.split('').some((character) => character.charCodeAt(0) <= 32)) || depth > 4) return base;
    const parsed = new URL(input, 'https://reference.invalid');
    let path = input.split(/[?#]/, 1)[0];
    if (path === base || path === `${base}/`) path = '/';
    else if (path.startsWith(`${base}/`)) path = path.slice(base.length);
    if (!paths.has(path)) return base;
    if (path === '/bypass') path = '/ai';
    const back = parsed.searchParams.get('back');
    if (back !== null) parsed.searchParams.set('back', referenceUrl(back, depth + 1));
    return `${base}${path === '/' ? '' : path}${parsed.search}${parsed.hash}`;
}
