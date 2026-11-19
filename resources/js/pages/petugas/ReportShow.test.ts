import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ReportShow from './ReportShow.vue';

const testPage = vi.hoisted(() => ({ url: '/petugas/laporan/activity-a' }));
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
        usePage: () => testPage,
    };
});
const activity = {
    id: 'activity-a',
    name: 'Perawatan',
    position: 5,
    worker_count: 3,
    performed_by: 'user-a',
    performer_name: 'Petugas A',
    block: 'Blok Utara',
    subblok: 'B',
    notes: 'Pemantauan dilakukan.',
    task_id: 'task-a',
    details: [
        { label: 'Luas (ha)', value: '0.0005' },
        { label: 'Luas input', value: '5' },
        { label: 'Satuan luas', value: 'm²' },
        { label: 'Hama', value: 'Tidak ditemukan' },
    ],
    photos: [{ id: 'photo-a', url: '/foto-a', available: false }],
};
const log = {
    id: 'log-a',
    farm_id: 'farm-a',
    work_date: '2026-11-17',
    recorded_by: 'user-a',
    recorder_name: 'Petugas A',
    activities: [activity],
};

beforeEach(() => {
    testPage.url =
        '/petugas/laporan/activity-a?back=%2Fpetugas%2Flaporan%3Fsource%3Dsop%26page%3D2';
});
describe('report detail presentation', () => {
    it('shows the requested metadata, one area in m², notes, photo state, and the filtered back link', async () => {
        const html = await renderToString(
            h(ReportShow, {
                farm: { id: 'farm-a', name: 'Kebun Utara' },
                date: '2026-11-17',
                log,
            }),
        );
        for (const text of [
            'Blok Utara · Subblok B',
            '17 November 2026',
            'Petugas A',
            '3 orang',
            '5 m²',
            'Indikasi gangguan',
            'Tidak ditemukan',
            'Pemantauan dilakukan.',
            'Foto 1 tidak tersedia',
        ])
            expect(html).toContain(text);
        expect(html).toContain('<h1 class="sr-only">Rincian aktivitas</h1>');
        expect(html).not.toContain('Kebun Utara');
        expect(html).not.toContain('Laporan harian bersama');
        expect(html).not.toContain('Aktivitas ke-5');
        expect(html).not.toContain('Luas (ha)');
        expect(html).not.toContain('Luas input');
        expect(html).not.toContain('Satuan luas');
        expect(html).not.toContain('0.0005');
        expect(html).toContain('href="/petugas/laporan?source=sop&amp;page=2"');
        expect(html).not.toContain('src="/foto-a"');
    });

    it('supports legacy reports and rejects external back links', async () => {
        testPage.url =
            '/petugas/laporan/activity-a?back=https%3A%2F%2Fexample.com';
        const html = await renderToString(
            h(ReportShow, {
                farm: { id: 'farm-a', name: 'Kebun Utara' },
                date: '2026-11-17',
                log: null,
                legacyActivity: {
                    ...activity,
                    block: null,
                    subblok: null,
                    details: [],
                    photos: [],
                    notes: null,
                },
            }),
        );
        expect(html).toContain('Lokasi spesifik tidak tercatat');
        expect(html).toContain('Ringkasan logbook harian tidak tersedia');
        expect(html).toContain('Belum ada foto dokumentasi');
        expect(html).toContain('href="/petugas/laporan"');
        expect(html).not.toContain('https://example.com');
    });
});
