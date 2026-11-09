// Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/composables/useDemoStorage.ts
// Reference-only fixture compatibility. No browser storage is read or written.
export function readDemoStorage<T>(_key: string, fallback: T): T { return structuredClone(fallback); }
export function clearDemoStorage(): void { /* historical controls cannot reset operational data */ }
