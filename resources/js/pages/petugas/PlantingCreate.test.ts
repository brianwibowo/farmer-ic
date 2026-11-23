import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PlantingCreate from './PlantingCreate.vue';

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
                clearErrors: () => undefined,
            }),
    };
});

const props = {
    farm: { id: 'farm-a', name: 'Kebun Cileuksa' },
    cycle: {
        id: 'cycle-a',
        batch_number: 2,
        subblock_label: 'Subblok B',
        status: 'active',
        reserved_area_ha: '5.0000',
        planted_area_ha: '2.0000',
        planned_planting_at: '2026-11-01',
        planted_at: '2026-11-02',
        block: { id: 'block-a', name: 'Blok Utara' },
        variety: { id: 'variety-a', name: 'Bisi 18' },
    },
    varieties: [{ id: 'variety-a', name: 'Bisi 18' }],
    commodityConfigured: true,
    varietyMismatch: false,
    reports: [
        {
            id: 'report-a',
            date: '2026-11-02',
            notes: null,
            detail: {
                seed_name: 'Benih jagung',
                quantity_kg: '5',
                area_ha: '2',
            },
            photos: [],
        },
    ],
    canRecord: true,
    today: '2026-11-17',
    workDate: '2026-11-17',
    blockedReason: null,
    areaLimitM2: 30000,
    numericLimits: {
        workerCount: 32767,
        quantityKg: 999999,
        area: 999999,
        quantity: 999999,
        bagSize: 99999,
    },
};

beforeEach(() => {
    formState.initial = {};
    formState.errors = {};
});

describe('planting activity form presentation', () => {
    it('retains planting context, inputs, evidence and report history', async () => {
        const html = await renderToString(h(PlantingCreate, props));

        expect(html).toContain('Blok Utara');
        expect(html).toContain('Subblok B');
        expect(html).toContain('Acuan HST');
        expect(html).toContain('Waktu pelaksanaan');
        expect(html).toContain('aria-label="Tambah jumlah pekerja"');
        expect(html).toContain('aria-label="Varietas"');
        expect(html).toMatch(/id="planting-variety"[^>]*disabled/);
        expect(html).toContain(
            'Varietas mengikuti penanaman pertama pada subblok ini.',
        );
        expect(html).toContain('Satuan luas');
        expect(html).toContain('Satuan benih');
        expect(html).not.toContain('Nama benih / produk');
        expect(html).toContain('Maksimal 30.000 m²');
        expect(html).toContain('id="activity-photos"');
        expect(html).toContain('Benih jagung');
        expect(html).toContain('/petugas/laporan/report-a');
    });

    it('keeps sack weight and validation feedback visible when needed', async () => {
        formState.initial = { quantity_unit: 'sak' };
        formState.errors = {
            bag_size: 'Isi berat per sak.',
            notes: 'Catatan terlalu panjang.',
            'photos.0': 'Foto tidak valid.',
        };
        const html = await renderToString(h(PlantingCreate, props));

        expect(html).toContain('Berat per sak (kg) *');
        expect(html).toContain('planting-error-bag_size');
        expect(html).toContain('photo-help photo-errors');
        expect(html).toContain('Foto tidak valid.');
        expect(html).toMatch(/<details[^>]*\sopen/);
        expect(html).toContain('planting-notes-error');
    });

    it('retains the read-only state when recording is not permitted', async () => {
        const html = await renderToString(
            h(PlantingCreate, { ...props, canRecord: false }),
        );

        expect(html).toContain('Realisasi penanaman');
        expect(html).toContain('Form ini tidak menimpa');
        expect(html).not.toContain('Simpan realisasi penanaman');
        expect(html).toContain('/petugas/laporan/report-a');
    });

    it('keeps the variety selector editable for unplanted subblocks', async () => {
        const html = await renderToString(
            h(PlantingCreate, {
                ...props,
                cycle: {
                    ...props.cycle,
                    planted_at: null,
                    planted_area_ha: '0.0000',
                    variety: null,
                },
                reports: [],
            }),
        );

        expect(html).toContain('id="planting-variety"');
        expect(html).not.toMatch(/id="planting-variety"[^>]*disabled/);
        expect(html).not.toContain(
            'Varietas mengikuti penanaman pertama pada subblok ini.',
        );
    });
});
