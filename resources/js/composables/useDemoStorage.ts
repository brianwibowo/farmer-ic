const storagePrefix = 'demo_v1_';

export function readDemoStorage<T>(key: string, fallback: T): T {
    try {
        const stored = window.localStorage.getItem(`${storagePrefix}${key}`);
        return stored ? (JSON.parse(stored) as T) : fallback;
    } catch {
        return fallback;
    }
}

export function writeDemoStorage<T>(key: string, value: T): void {
    try {
        window.localStorage.setItem(`${storagePrefix}${key}`, JSON.stringify(value));
    } catch {
        // Demo data remains available in memory if browser storage is unavailable.
    }
}

export function clearDemoStorage(): void {
    try {
        Object.keys(window.localStorage)
            .filter((key) => key.startsWith(storagePrefix))
            .forEach((key) => window.localStorage.removeItem(key));
    } catch {
        // There is nothing else to clear when browser storage is unavailable.
    }
}
