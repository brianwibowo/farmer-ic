const images = import.meta.glob<string>('./assets/images/**/*', {
    eager: true,
    import: 'default',
    query: '?url',
});

/** Resolve only versioned historical assets, never today's public image tree. */
export function referenceAsset(path: string): string {
    return images[`./assets${path}`] ?? images['./assets/images/kebun-placeholder.svg'];
}

export function resolveFixtureAssets<T>(value: T): T {
    if (typeof value === 'string') {
        return (value.startsWith('/images/') ? referenceAsset(value) : value) as T;
    }
    if (Array.isArray(value)) return value.map(resolveFixtureAssets) as T;
    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, resolveFixtureAssets(item)])) as T;
    }
    return value;
}
