import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it } from 'vitest';
import ReportPhotoGallery from './ReportPhotoGallery.vue';

describe('report photo gallery', () => {
    it('provides an honest empty state', async () => {
        const html = await renderToString(
            h(ReportPhotoGallery, { photos: [], activityName: 'Perawatan' }),
        );
        expect(html).toContain('0 foto');
        expect(html).toContain('Belum ada foto dokumentasi');
    });

    it('does not request files already known to be unavailable', async () => {
        const html = await renderToString(
            h(ReportPhotoGallery, {
                activityName: 'Perawatan',
                photos: [
                    { id: '1', url: '/foto-hilang', available: false },
                    { id: '2', url: '/foto-tersedia', available: true },
                ],
            }),
        );
        expect(html).toContain('2 foto');
        expect(html).toContain('Foto 1 tidak tersedia');
        expect(html).not.toContain('src="/foto-hilang"');
        expect(html).toContain('src="/foto-tersedia"');
        expect(html).toContain('Perbesar foto 2 Perawatan');
    });
});
