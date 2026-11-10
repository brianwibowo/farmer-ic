export type OwnerFarmOrigin = 'home' | 'farms';

export function ownerFarmOrigin(pageUrl: string): OwnerFarmOrigin {
    const query = pageUrl.split('#')[0].split('?')[1] ?? '';
    return new URLSearchParams(query).get('from') === 'home' ? 'home' : 'farms';
}

/** Carry only the known navigation context, including through nested block pages. */
export function withOwnerFarmOrigin(
    href: string,
    origin: OwnerFarmOrigin,
): string {
    const hashIndex = href.indexOf('#');
    const hash = hashIndex < 0 ? '' : href.slice(hashIndex);
    const target = hashIndex < 0 ? href : href.slice(0, hashIndex);
    const [path, query = ''] = target.split('?');
    const params = new URLSearchParams(query);
    params.delete('from');
    if (origin === 'home') params.set('from', 'home');
    const search = params.toString();

    return `${path}${search ? `?${search}` : ''}${hash}`;
}
