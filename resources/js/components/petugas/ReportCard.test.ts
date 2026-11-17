import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import ReportCard from './ReportCard.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        Link: defineComponent({
            props: { href: String },
            setup:
                (props, { slots }) =>
                () =>
                    h('a', { href: props.href }, slots.default?.()),
        }),
    };
});

const report = {
    id: 'activity-a',
    name: 'Perawatan',
    date: '2026-11-17',
    location: 'Blok Utara · B',
    source: 'SOP' as const,
    worker_count: 5,
    notes: 'Pemeriksaan lahan.',
    photo: '/private/foto',
    photo_count: 2,
};

describe('Petugas report card', () => {
    it('renders the persisted activity, location, date and photo count', async () => {
        const html = await renderToString(
            h(ReportCard, {
                report,
                farmName: 'Kebun Utara',
                href: '/petugas/laporan/activity-a',
            }),
        );
        expect(html).toContain('Kebun Utara');
        expect(html).toContain('Perawatan');
        expect(html).toContain('17 November 2026');
        expect(html).toContain('Blok Utara · B');
        expect(html).toContain('5 pekerja');
        expect(html).toContain('Pemeriksaan lahan.');
        expect(html).toContain('2 foto tercatat');
        expect(html).toContain('src="/private/foto"');
    });

    it('distinguishes an unavailable photo from no recorded photos', async () => {
        const missing = await renderToString(
            h(ReportCard, {
                report: { ...report, photo: null },
                farmName: 'Kebun',
                href: '/',
            }),
        );
        expect(missing).toContain('Foto tidak tersedia');
        expect(missing).not.toContain('<img');
        const empty = await renderToString(
            h(ReportCard, {
                report: { ...report, photo: null, photo_count: 0 },
                farmName: 'Kebun',
                href: '/',
            }),
        );
        expect(empty).toContain('Belum ada foto');
    });
});
