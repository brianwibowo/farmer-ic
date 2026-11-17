import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import Laporan from './Laporan.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        Head: defineComponent({ setup: () => () => null }),
        Link: defineComponent({
            props: { href: String },
            setup:
                (props, { slots }) =>
                () =>
                    h('a', { href: props.href }, slots.default?.()),
        }),
        usePage: () => ({ url: '/petugas/laporan?source=sop&page=2' }),
        router: { get: vi.fn() },
    };
});
const props = {
    farm: { id: 'farm-a', name: 'Kebun Utara' },
    reports: {
        data: [
            {
                id: 'activity-a',
                name: 'Perawatan',
                date: '2026-11-17',
                location: 'Blok Utara',
                source: 'SOP' as const,
                worker_count: 3,
                notes: 'Pemeriksaan rutin',
                highlight: 'Luas dikerjakan: 100 m²',
                photo: null,
                photo_count: 0,
            },
        ],
        total: 16,
        current_page: 2,
        last_page: 2,
        prev_page_url: '/petugas/laporan?source=sop&page=1',
        next_page_url: null,
    },
    stats: { sop: 16, mandiri: 0 },
    filters: { source: 'sop' },
    types: [{ code: 'maintenance', name: 'Perawatan' }],
    categories: [],
};
describe('report history presentation', () => {
    it('retains filters and pagination and links back to the same filtered page', async () => {
        const html = await renderToString(h(Laporan, props));
        expect(html).toContain('Ringkasan laporan sesuai filter');
        expect(html).toContain('<h1 class="sr-only">Laporan</h1>');
        expect(html).not.toContain('Kebun Utara');
        expect(html).not.toContain('Laporan harian');
        expect(html).not.toContain('/petugas/laporan/harian');
        expect(html).toContain('Tanggal kerja');
        expect(html).toContain('type="date"');
        expect(html).toMatch(/<h3[^>]*>Perawatan<\/h3>/);
        expect(html).toContain('Luas dikerjakan: 100 m²');
        expect(html).not.toContain('Pemeriksaan rutin');
        expect(html).toContain('<time datetime="2026-11-17">2026-11-17</time>');
        expect(html).toContain('17 November 2026');
        expect(html).toContain('Halaman 2 / 2');
        expect(html).toContain('Terapkan filter');
        expect(html).toContain(
            '/petugas/laporan/activity-a?back=%2Fpetugas%2Flaporan%3Fsource%3Dsop%26page%3D2',
        );
    });

    it('keeps the selected work date in the filter', async () => {
        const html = await renderToString(
            h(Laporan, {
                ...props,
                filters: { ...props.filters, date: '2026-11-17' },
            }),
        );

        expect(html).toContain('Tanggal kerja');
        expect(html).toContain('type="date"');
        expect(html).toContain('value="2026-11-17"');
        expect(html).toContain('Terapkan filter');
    });

    it('groups reports by work date, newest first, without changing their order within a date', async () => {
        const report = props.reports.data[0];
        const html = await renderToString(
            h(Laporan, {
                ...props,
                reports: {
                    ...props.reports,
                    data: [
                        {
                            ...report,
                            id: 'old',
                            date: '2026-11-16',
                            highlight: 'Luas dikerjakan: 1 m²',
                        },
                        {
                            ...report,
                            id: 'first',
                            highlight: 'Luas dikerjakan: 2 m²',
                        },
                        {
                            ...report,
                            id: 'old-second',
                            date: '2026-11-16',
                            highlight: 'Luas dikerjakan: 3 m²',
                        },
                        {
                            ...report,
                            id: 'second',
                            highlight: 'Luas dikerjakan: 4 m²',
                        },
                    ],
                },
            }),
        );

        const today = '<time datetime="2026-11-17">2026-11-17</time>';
        const yesterday = '<time datetime="2026-11-16">2026-11-16</time>';
        expect(html.split(today)).toHaveLength(2);
        expect(html.split(yesterday)).toHaveLength(2);
        expect(html).toContain('aria-labelledby="report-date-2026-11-17"');
        expect(html.indexOf(today)).toBeLessThan(
            html.indexOf('Luas dikerjakan: 2 m²'),
        );
        expect(html.indexOf('Luas dikerjakan: 2 m²')).toBeLessThan(
            html.indexOf('Luas dikerjakan: 4 m²'),
        );
        expect(html.indexOf('Luas dikerjakan: 4 m²')).toBeLessThan(
            html.indexOf(yesterday),
        );
        expect(html.indexOf(yesterday)).toBeLessThan(
            html.indexOf('Luas dikerjakan: 1 m²'),
        );
        expect(html.indexOf('Luas dikerjakan: 1 m²')).toBeLessThan(
            html.indexOf('Luas dikerjakan: 3 m²'),
        );
    });

    it('shows the date heading on each page when a date spans pages', async () => {
        const firstPage = await renderToString(
            h(Laporan, {
                ...props,
                reports: {
                    ...props.reports,
                    current_page: 1,
                    data: [
                        { ...props.reports.data[0], id: 'activity-page-one' },
                    ],
                },
            }),
        );
        const secondPage = await renderToString(h(Laporan, props));
        const dateHeading = '<time datetime="2026-11-17">2026-11-17</time>';

        expect(firstPage).toContain(dateHeading);
        expect(secondPage).toContain(dateHeading);
        expect(firstPage).toContain('/petugas/laporan/activity-page-one?');
        expect(secondPage).toContain('/petugas/laporan/activity-a?');
        expect(secondPage).toContain('Halaman 2 / 2');
    });

    it('keeps the filtered empty state without rendering date headings', async () => {
        const html = await renderToString(
            h(Laporan, {
                ...props,
                reports: {
                    ...props.reports,
                    data: [],
                    total: 0,
                    current_page: 1,
                    last_page: 1,
                    prev_page_url: null,
                },
                stats: { sop: 0, mandiri: 0 },
            }),
        );

        expect(html).toContain('Belum ada laporan yang ditampilkan');
        expect(html).not.toContain('<time datetime=');
    });

    it('explains a missing farm assignment instead of inventing data', async () => {
        const html = await renderToString(
            h(Laporan, {
                ...props,
                farm: null,
                reports: null,
                stats: { sop: 0, mandiri: 0 },
                types: [],
            }),
        );
        expect(html).toContain('Belum ada penugasan kebun');
        expect(html).toContain('Hubungi Admin untuk penugasan kebun.');
        expect(html).not.toContain('Terapkan filter');
    });
});
