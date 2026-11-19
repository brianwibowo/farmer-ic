import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import VoluntaryCreate from './VoluntaryCreate.vue';

const formState = vi.hoisted(() => ({
    initial: {} as Record<string, unknown>,
    errors: {} as Record<string, string>,
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h, reactive } = await import('vue');
    return {
        Head: defineComponent({ setup: () => () => null }),
        Link: defineComponent({
            props: { href: String },
            setup:
                (props, { slots }) =>
                () =>
                    h('a', { href: props.href }, slots.default?.()),
        }),
        useForm: (data: Record<string, unknown>) =>
            reactive({
                ...data,
                ...formState.initial,
                errors: { ...formState.errors },
                processing: false,
                progress: null,
                clearErrors: () => undefined,
            }),
    };
});

const areaFields = [
    {
        key: 'area',
        label: 'Luas area',
        type: 'number',
        max: 999999,
        step: '0.0001',
    },
    {
        key: 'area_unit',
        label: 'Satuan luas',
        type: 'select',
        options: ['m²', 'Ha'],
    },
];
const props = {
    farm: { id: 'farm-a', name: 'Kebun Cileuksa' },
    today: '2026-11-17',
    numericLimits: { workerCount: 32767, quantityKg: 999999 },
    fields: areaFields,
    blocks: [
        {
            id: 'block-a',
            code: 'B01',
            name: 'Blok Utara',
            area_limit_m2: 50000,
            blocked_reason: null,
        },
    ],
    cycles: [
        {
            id: 'cycle-a',
            subblock_label: 'Subblok A',
            block: { id: 'block-a', name: 'Blok Utara' },
            reserved_area_ha: '2',
            area_limit_m2: 20000,
            blocked_reason: null,
        },
    ],
    matchingTasks: [
        {
            id: 'task-a',
            planting_cycle_id: 'cycle-a',
            name: 'Perawatan lanjutan',
            scheduled_date: '2026-11-16T17:00:00.000000Z',
        },
    ],
};

beforeEach(() => {
    formState.initial = {};
    formState.errors = {};
});

describe('voluntary activity form presentation', () => {
    it('keeps the required block choice for land preparation', async () => {
        const html = await renderToString(
            h(VoluntaryCreate, {
                ...props,
                type: {
                    id: '1',
                    code: 'land_cleaning',
                    name: 'Pembersihan lahan',
                },
            }),
        );

        expect(html).toContain('Aktivitas mandiri');
        expect(html).toContain('Pembersihan lahan');
        expect(html).toContain('Kebun Cileuksa');
        expect(html).toContain('for="voluntary-block"');
        expect(html).not.toContain('id="voluntary-cycle"');
        expect(html).toContain('aria-label="Tambah jumlah pekerja"');
        expect(html).toContain('id="activity-photos"');
        expect(html).toContain(
            'grid-cols-[minmax(0,1fr)_minmax(5.75rem,0.55fr)]',
        );
    });

    it('keeps location limits, a blocked reason, and matching SOP links', async () => {
        formState.initial = { cycle_id: 'cycle-a' };
        const html = await renderToString(
            h(VoluntaryCreate, {
                ...props,
                type: { id: '2', code: 'maintenance', name: 'Perawatan' },
                cycles: [
                    {
                        ...props.cycles[0],
                        blocked_reason: 'Penanaman belum dicatat.',
                    },
                ],
            }),
        );

        expect(html).toContain('for="voluntary-cycle"');
        expect(html).not.toContain('id="voluntary-block"');
        expect(html).toContain('Maksimal 20.000 m²');
        expect(html).toContain('Penanaman belum dicatat.');
        expect(html).toContain('/petugas/tasks/task-a');
        expect(html).toContain('Perawatan lanjutan');
        expect(html).toContain('17 Nov 2026');
        expect(html).toMatch(/disabled[^>]*>[^<]*(?:<!--.*?-->)?/);
    });

    it('shows both optional locations for incidents and exposes server errors', async () => {
        formState.errors = {
            work_date: 'Tanggal tidak valid.',
            block_id: 'Blok tidak valid.',
            notes: 'Catatan terlalu panjang.',
            'photos.0': 'Foto tidak valid.',
        };
        const html = await renderToString(
            h(VoluntaryCreate, {
                ...props,
                type: { id: '3', code: 'incident', name: 'Insiden' },
            }),
        );

        expect(html).toContain('for="voluntary-block"');
        expect(html).toContain('for="voluntary-cycle"');
        const subblock = html.match(
            /<button(?=[^>]*id="voluntary-cycle")[^>]*>/,
        )?.[0];
        expect(subblock).toContain('disabled');
        expect(html).toContain('Tanpa blok, aktivitas berlaku untuk seluruh');
        expect(html).toContain(
            'Pilih blok terlebih dahulu untuk memilih subblok.',
        );
        expect(html).toContain('voluntary-date-error');
        expect(html).toContain('voluntary-block-error');
        expect(html).toContain('photo-help photo-errors');
        expect(html).toContain('Foto tidak valid.');
        expect(html).toMatch(/<details[^>]*\sopen/);
        expect(html).toContain('voluntary-notes-error');
    });

    it('shows the selected block empty state when it has no active subblocks', async () => {
        formState.initial = { block_id: 'block-b' };
        const html = await renderToString(
            h(VoluntaryCreate, {
                ...props,
                type: { id: '4', code: 'other', name: 'Lainnya' },
                blocks: [
                    ...props.blocks,
                    {
                        id: 'block-b',
                        code: 'B02',
                        name: 'Blok Selatan',
                        area_limit_m2: 30000,
                        blocked_reason: null,
                    },
                ],
            }),
        );
        expect(html).toContain('Belum ada subblok aktif pada blok ini.');
        expect(html).toContain('Maksimal 30.000 m²');
        expect(
            html.match(/<button(?=[^>]*id="voluntary-cycle")[^>]*>/)?.[0],
        ).toContain('disabled');
    });

    it('locks the location and displays incident context in follow-up mode', async () => {
        const html = await renderToString(
            h(VoluntaryCreate, {
                ...props,
                type: { id: '2', code: 'maintenance', name: 'Perawatan' },
                incidentTicket: {
                    id: 'ticket-123',
                    incident_category: 'Hama',
                    severity: 'emergency',
                    severity_label: 'Darurat',
                    description: 'Serangan ulat di baris selatan.',
                    admin_note: 'Lakukan penyemprotan segera.',
                    scope: 'cycle',
                    cycle_id: 'cycle-a',
                    block_id: null,
                    location_label: 'Kebun Cileuksa · Blok Utara · Subblok A',
                },
            }),
        );

        expect(html).toContain('/petugas/incidents/ticket-123');
        expect(html).toContain('Tindak lanjut tiket insiden');
        expect(html).toContain('Serangan ulat di baris selatan.');
        expect(html).toContain('Arahan Admin:');
        expect(html).toContain('Lokasi dikunci sesuai tiket insiden');
        expect(html).toContain('Kebun Cileuksa · Blok Utara · Subblok A');
        expect(html).not.toContain('id="voluntary-cycle"');
        expect(html).not.toContain('id="voluntary-block"');
    });

    it('hides worker_count for open incidents and shows it optionally when resolved immediately', async () => {
        const incidentFields = [
            ...areaFields,
            {
                key: 'incident_status',
                label: 'Status awal',
                type: 'select',
                options: ['Perlu penanganan', 'Sudah ditangani langsung'],
            },
        ];

        const openHtml = await renderToString(
            h(VoluntaryCreate, {
                ...props,
                fields: incidentFields,
                type: { id: '3', code: 'incident', name: 'Pelaporan Insiden' },
            }),
        );
        expect(openHtml).not.toContain('id="activity-worker-count"');

        formState.initial = {
            details: {
                area: '',
                area_unit: 'm²',
                incident_status: 'Sudah ditangani langsung',
            },
        };
        const resolvedHtml = await renderToString(
            h(VoluntaryCreate, {
                ...props,
                fields: incidentFields,
                type: { id: '3', code: 'incident', name: 'Pelaporan Insiden' },
            }),
        );
        expect(resolvedHtml).toContain('Jumlah pekerja (opsional)');
        expect(resolvedHtml).toContain('id="activity-worker-count"');
        const workerInput = resolvedHtml.match(
            /<input(?=[^>]*id="activity-worker-count")[^>]*>/,
        )?.[0];
        expect(workerInput).toBeDefined();
        expect(workerInput).not.toContain('required');
    });
});
