import { nextTick } from 'vue';

export async function dismissStartupScreen(): Promise<void> {
    if (typeof document === 'undefined') return;
    const screen = document.getElementById('startup-screen');
    if (!screen) return;

    screen.dispatchEvent(new Event('startup:ready'));
    await nextTick();

    const startedAt = Number(screen.dataset.startedAt || performance.now());
    const remaining = Math.max(0, 1200 - (performance.now() - startedAt));
    await new Promise<void>((resolve) => window.setTimeout(resolve, remaining));

    screen.classList.add('is-leaving');
    await new Promise<void>((resolve) => window.setTimeout(resolve, 200));
    screen.remove();
    document.getElementById('app')?.removeAttribute('inert');
    document.getElementById('app')?.removeAttribute('aria-busy');
}

export function showStartupError(): void {
    if (typeof document === 'undefined') return;
    const screen = document.getElementById('startup-screen');
    screen?.dispatchEvent(new Event('startup:ready'));
    const error = document.getElementById('startup-error');
    if (error) error.hidden = false;
}
