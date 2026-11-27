import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { confirmationState, requestConfirmation, resolveConfirmation } from '@/composables/useConfirmation';
import { toast } from './toast';

const sweetAlert = vi.hoisted(() => ({
    fire: vi.fn(),
    close: vi.fn(),
    getPopup: vi.fn(),
    mixin: vi.fn(),
}));
vi.mock('sweetalert2', () => ({
    default: { ...sweetAlert, mixin: () => ({ fire: sweetAlert.fire }) },
}));

beforeEach(() => {
    vi.clearAllMocks();
    sweetAlert.getPopup.mockReturnValue(null);
    sweetAlert.fire.mockResolvedValue({ isConfirmed: false, isDenied: false, isDismissed: true });
});
afterEach(async () => {
    toast.dismiss();
    resolveConfirmation(false);
    await nextTick();
});

describe('toast and confirmation isolation', () => {
    it('renders messages as literal text instead of HTML', async () => {
        await toast.success('<img src=x onerror=alert(1)>');
        expect(sweetAlert.fire).toHaveBeenCalledWith(expect.objectContaining({
            titleText: '<img src=x onerror=alert(1)>',
        }));
        expect(sweetAlert.fire.mock.calls[0][0]).not.toHaveProperty('title');
    });

    it.each([true, false])('waits for a confirmation decision (%s) before showing a toast', async (confirmed) => {
        const decision = requestConfirmation();
        const notification = toast.error('Laporan belum dapat disimpan.');
        await nextTick();
        expect(sweetAlert.fire).not.toHaveBeenCalled();
        resolveConfirmation(confirmed);
        expect(await decision).toBe(confirmed);
        await notification;
        expect(sweetAlert.fire).toHaveBeenCalledOnce();
    });

    it('does not dismiss a confirmation when navigation dismisses toasts', async () => {
        const decision = requestConfirmation();
        sweetAlert.getPopup.mockReturnValue({ classList: { contains: () => false } });
        toast.dismiss();
        expect(sweetAlert.close).not.toHaveBeenCalled();
        expect(confirmationState.open).toBe(true);
        resolveConfirmation(true);
        expect(await decision).toBe(true);
    });

    it('drops a queued message from the previous page after toast dismissal', async () => {
        const decision = requestConfirmation();
        const notification = toast.info('Pesan halaman lama');
        toast.dismiss();
        resolveConfirmation(false);
        await decision;
        expect(await notification).toEqual(expect.objectContaining({ isDismissed: true }));
        expect(sweetAlert.fire).not.toHaveBeenCalled();
        await toast.success('Pesan halaman baru');
        expect(sweetAlert.fire).toHaveBeenCalledOnce();
    });

    it('still dismisses an active toast', () => {
        sweetAlert.getPopup.mockReturnValue({ classList: { contains: (name: string) => name === 'swal2-toast' } });
        toast.dismiss();
        expect(sweetAlert.close).toHaveBeenCalledOnce();
    });
});
