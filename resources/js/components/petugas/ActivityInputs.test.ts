import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it } from 'vitest';
import ActivityPhotoPicker from './ActivityPhotoPicker.vue';
import ActivityWorkerCount from './ActivityWorkerCount.vue';

describe('activity task input controls', () => {
    it('labels the worker stepper, exposes limits, and disables decrement at one', async () => {
        const html = await renderToString(
            h(ActivityWorkerCount, {
                modelValue: 1,
                maximum: 25,
                error: 'Periksa jumlah pekerja.',
            }),
        );

        expect(html).toContain('for="activity-worker-count"');
        expect(html).toMatch(
            /aria-label="Kurangi jumlah pekerja"[^>]*disabled/,
        );
        expect(html).toContain('aria-label="Tambah jumlah pekerja"');
        expect(html).toContain('max="25"');
        expect(html).toContain('aria-describedby="error-worker_count"');
        expect(html).not.toContain('worker-count-help');
        expect(html).not.toContain('Maksimal 25 pekerja.');
        expect(html).toContain('Periksa jumlah pekerja.');
    });

    it('shows selected photo names and disables adding beyond the limit', async () => {
        const photos = Array.from(
            { length: 5 },
            (_, index) =>
                new File(['image'], `foto-${index + 1}.jpg`, {
                    type: 'image/jpeg',
                }),
        );
        const html = await renderToString(
            h(ActivityPhotoPicker, { photos, invalid: true }),
        );

        expect(html).toMatch(/id="activity-photos"[^>]*disabled/);
        expect(html).toContain('photo-help photo-errors');
        expect(html).toContain('Batas foto tercapai');
        expect(html).toContain('Hapus foto-1.jpg');
        expect(html).toContain('foto-5.jpg');
        expect(html).toContain('5/5');
    });

    it('anchors the visually hidden photo input inside its picker', async () => {
        const html = await renderToString(
            h(ActivityPhotoPicker, { photos: [], invalid: false }),
        );

        expect(html).toContain('<div class="relative space-y-3">');
        expect(html).toMatch(
            /<input id="activity-photos"[^>]*class="peer sr-only"/,
        );
    });
});
