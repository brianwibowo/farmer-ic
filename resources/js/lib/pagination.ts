/** Preserve the current route, filters and anchor when changing a page. */
export function paginationUrl(
    url: string,
    page: number,
    pageName = 'page',
): string {
    const target = new URL(url, 'http://inertia.local');
    target.searchParams.set(pageName, String(page));
    return `${target.pathname}${target.search}${target.hash}`;
}
