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
    highlight: 'Luas dikerjakan: 5 m²',
    photo: '/private/foto',
    photo_count: 2,
};

describe('Petugas report card', () => {
    it('uses the activity category as the title and keeps the report details', async () => {
        const html = await renderToString(
            h(ReportCard, {
                report,
                href: '/petugas/laporan/activity-a',
            }),
        );
        expect(html).toMatch(/<h3[^>]*>Perawatan<\/h3>/);
        expect(html).toContain('>SOP</span>');
        expect(html.split('>Perawatan</h3>')).toHaveLength(2);
        expect(html).not.toContain('Kebun Utara');
        expect(html).toContain('17 November 2026');
        expect(html).toContain('Blok Utara · B');
        expect(html).toContain('5 pekerja');
        expect(html).toContain('Luas dikerjakan: 5 m²');
        expect(html).not.toContain('Pemeriksaan lahan.');
        expect(html).toContain('2 foto tercatat');
        expect(html).toContain('src="/private/foto"');
    });

    it.each([
        'Benih: Bisi 18 · 2 sak',
        'Berat panen: 2,75 ton',
        'Insiden: Hama · Sedang',
    ])('shows the structured highlight %s', async (highlight) => {
        const html = await renderToString(
            h(ReportCard, {
                report: { ...report, highlight },
                href: '/petugas/laporan/activity-a',
            }),
        );
        expect(html).toContain(highlight);
        expect(html).not.toContain('Pemeriksaan lahan.');
    });

    it('does not replace a missing highlight with notes', async () => {
        const html = await renderToString(
            h(ReportCard, {
                report: { ...report, highlight: null },
                href: '/petugas/laporan/activity-a',
            }),
        );
        expect(html).not.toContain('Pemeriksaan lahan.');
        expect(html).not.toContain('Luas dikerjakan:');
    });

    it('shows the source badge even when no worker count was recorded', async () => {
        const html = await renderToString(
            h(ReportCard, {
                report: { ...report, source: 'Mandiri', worker_count: null },
                href: '/petugas/laporan/activity-a',
            }),
        );
        expect(html).toMatch(/<h3[^>]*>Perawatan<\/h3>/);
        expect(html).toContain('>Mandiri</span>');
        expect(html).not.toContain('pekerja');
    });

    it('distinguishes an unavailable photo from no recorded photos', async () => {
        const missing = await renderToString(
            h(ReportCard, {
                report: { ...report, photo: null },
                href: '/',
            }),
        );
        expect(missing).toContain('Foto tidak tersedia');
        expect(missing).not.toContain('<img');
        const empty = await renderToString(
            h(ReportCard, {
                report: { ...report, photo: null, photo_count: 0 },
                href: '/',
            }),
        );
        expect(empty).toContain('Belum ada foto');
    });
});
