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
        expect(html).toContain('Kebun Utara');
        expect(html).toContain('Pemeriksaan rutin');
        expect(html).toContain('17 November 2026');
        expect(html).toContain('Halaman 2 / 2');
        expect(html).toContain('Terapkan filter');
        expect(html).toContain(
            '/petugas/laporan/activity-a?back=%2Fpetugas%2Flaporan%3Fsource%3Dsop%26page%3D2',
        );
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
