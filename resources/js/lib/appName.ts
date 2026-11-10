export const DEFAULT_APP_NAME = 'Bumi Hijauan';

export function resolveAppName(value: unknown): string {
    return typeof value === 'string' && value.trim()
        ? value.trim()
        : DEFAULT_APP_NAME;
}

// Available before Vue mounts for plain services and startup error reporting.
export function getAppName(): string {
    return resolveAppName(
        typeof document === 'undefined'
            ? undefined
            : document.querySelector<HTMLMetaElement>(
                  'meta[name="application-name"]',
              )?.content,
    );
}
