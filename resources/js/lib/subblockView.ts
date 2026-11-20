export type SubblockView = 'running' | 'harvested' | 'cancelled' | 'all';

export function matchesSubblockView(
    status: string,
    view: SubblockView,
): boolean {
    if (view === 'all') return true;
    if (view === 'running') return status === 'planned' || status === 'active';
    return status === view;
}
