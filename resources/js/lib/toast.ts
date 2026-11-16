import Swal from 'sweetalert2';
import type { SweetAlertIcon, SweetAlertResult } from 'sweetalert2';

type ToastKind = 'success' | 'info' | 'warning' | 'error';

const ICONS: Record<ToastKind, string> = {
    success:
        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
    info: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
    warning:
        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
    error: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
};

const CLOSE_ICON =
    '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

function isAdminSurface(): boolean {
    return (
        typeof document !== 'undefined' &&
        document.querySelector('.admin-dashboard-theme') !== null
    );
}

function resolveTopOffset(): string {
    if (typeof document === 'undefined') {
        return '1rem';
    }

    const header = document.querySelector<HTMLElement>(
        '.admin-dashboard-theme header, header.sticky',
    );
    if (!header) {
        return '1rem';
    }

    const rect = header.getBoundingClientRect();
    if (rect.top <= 4 && rect.bottom > 0 && rect.bottom < 140) {
        return `${Math.round(rect.bottom + 12)}px`;
    }

    return '1rem';
}

const baseToast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    showCloseButton: true,
    closeButtonHtml: CLOSE_ICON,
    timer: 3500,
    timerProgressBar: true,
    didOpen: (toastEl) => {
        const container = toastEl.parentElement;
        if (container) {
            container.style.setProperty('--swal-toast-top', resolveTopOffset());
        }
        if (
            typeof window !== 'undefined' &&
            window.matchMedia('(hover: hover)').matches
        ) {
            toastEl.addEventListener('mouseenter', Swal.stopTimer);
            toastEl.addEventListener('mouseleave', Swal.resumeTimer);
        }
    },
});

function fire(icon: ToastKind, message: string): Promise<SweetAlertResult> {
    const admin = isAdminSurface();

    return baseToast.fire({
        icon: icon as SweetAlertIcon,
        iconHtml: ICONS[icon],
        title: message,
        customClass: {
            container: admin
                ? 'swal-toast-container admin-dashboard-theme'
                : 'swal-toast-container',
            popup: admin
                ? 'swal-toast-popup admin-dashboard-theme'
                : 'swal-toast-popup',
            title: 'swal-toast-title',
            closeButton: 'swal-toast-close',
            timerProgressBar: 'swal-toast-progress',
        },
    });
}

export const toast = {
    success: (message: string) => fire('success', message),
    info: (message: string) => fire('info', message),
    warning: (message: string) => fire('warning', message),
    error: (message: string) => fire('error', message),
    dismiss: () => {
        if (Swal.isVisible()) {
            Swal.close();
        }
    },
};
