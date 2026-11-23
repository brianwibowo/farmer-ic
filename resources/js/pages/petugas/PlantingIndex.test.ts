import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PlantingIndex from './PlantingIndex.vue';

const pageState = vi.hoisted(() => ({ url: '/petugas/penanaman' }));

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
        usePage: () => ({
            url: pageState.url,
            props: {
                farmCycleOptions: [],
                canCreateFarmCycle: false,
            },
        }),
        useForm: (data: Record<string, unknown>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                isDirty: false,
                clearErrors: () => undefined,
                defaults: () => undefined,
                post: () => undefined,
            }),
    };
});

beforeEach(() => {
    pageState.url = '/petugas/penanaman';
});

describe('planting subblock index cards', () => {
    it('subdues subblocks older than 3 days with an inline window caption while keeping fresh cards bright', async () => {
        const html = await renderToString(
            h(PlantingIndex, {
                farm: { id: 'farm-a', name: 'Kebun Cileuksa' },
                blocks: [
                    {
                        id: 'block-a',
                        name: 'Blok Utara',
                        area_ha: '5.0000',
                        is_active: true,
                        available_ha: '1.0000',
                        can_create_batch: true,
                        cycles_count: 3,
                    },
                ],
                selectedBlockId: null,
                cycles: [
                    {
                        id: 'cycle-planned',
                        batch_number: 3,
                        subblock_label: 'C',
                        status: 'planned',
                        reserved_area_ha: '1.0000',
                        planted_area_ha: '0.0000',
                        planned_planting_at: '2026-11-20',
                        planted_at: null,
                        block: { id: 'block-a', name: 'Blok Selatan' },
                        variety: null,
                        canRecord: true,
                        canSync: true,
                        blockedReason: null,
                        plantingWindow: null,
                    },
                    {
                        id: 'cycle-fresh',
                        batch_number: 2,
                        subblock_label: 'B',
                        status: 'active',
                        reserved_area_ha: '1.0000',
                        planted_area_ha: '1.0000',
                        planned_planting_at: null,
                        planted_at: '2026-11-19',
                        block: { id: 'block-a', name: 'Blok Tengah' },
                        variety: { id: 'var-a', name: 'Bisi 18' },
                        canRecord: true,
                        canSync: false,
                        blockedReason: null,
                        plantingWindow: {
                            starts_at: '2026-11-18',
                            ends_at: '2026-11-20',
                            expired: false,
                        },
                    },
                    {
                        id: 'cycle-old',
                        batch_number: 1,
                        subblock_label: 'A',
                        status: 'active',
                        reserved_area_ha: '1.0000',
                        planted_area_ha: '1.0000',
                        planned_planting_at: null,
                        planted_at: '2026-10-02',
                        block: { id: 'block-a', name: 'Blok Utara' },
                        variety: { id: 'var-a', name: 'Bisi 18' },
                        canRecord: true,
                        canSync: false,
                        blockedReason: null,
                        plantingWindow: {
                            starts_at: '2026-10-01',
                            ends_at: '2026-10-03',
                            expired: true,
                        },
                    },
                ],
            }),
        );

        const articles = html.split('<article');
        expect(articles).toHaveLength(4);

        const plannedCard = articles[1];
        const freshCard = articles[2];
        const expiredCard = articles[3];

        expect(plannedCard).toContain('bg-card');
        expect(plannedCard).toContain('text-primary');
        expect(plannedCard).toContain('bg-primary text-primary-foreground');
        expect(plannedCard).toContain('Tertanam 0 ha');
        expect(plannedCard).not.toContain('0.0000 ha');
        expect(plannedCard).not.toContain('Lewat 3 hari sejak tanam pertama');
        expect(plannedCard).not.toContain('Siapkan tugas');

        expect(freshCard).toContain('bg-card');
        expect(freshCard).toContain('text-primary');
        expect(freshCard).toContain('bg-primary text-primary-foreground');
        expect(freshCard).not.toContain('Lewat 3 hari sejak tanam pertama');
        expect(freshCard).not.toContain('Siapkan tugas');

        expect(expiredCard).toContain('border-muted-foreground/40');
        expect(expiredCard).toContain('bg-muted/80');
        expect(expiredCard).toContain('text-muted-foreground');
        expect(expiredCard).toContain('text-foreground/75');
        expect(expiredCard).toContain('Catat penanaman');
        expect(expiredCard).toContain('data-variant="outline"');
        expect(expiredCard).not.toContain('bg-primary text-primary-foreground');
        expect(expiredCard).not.toContain('Siapkan tugas');
        expect(expiredCard).toContain(
            'Lewat 3 hari sejak tanam pertama · Tambahan hanya untuk tanggal 1 Okt 2026 – 3 Okt 2026.',
        );
        expect(html).toContain('href="/petugas/laporan"');
        expect(html).toContain('Kembali ke laporan');
        expect(html).not.toContain('Kembali ke aktivitas');
    });

    it('respects a valid back origin from Beranda and forwards it to subblock links', async () => {
        pageState.url = '/petugas/penanaman?back=%2Fpetugas';
        const html = await renderToString(
            h(PlantingIndex, {
                farm: { id: 'farm-a', name: 'Kebun Cileuksa' },
                blocks: [],
                selectedBlockId: null,
                cycles: [
                    {
                        id: 'cycle-planned',
                        batch_number: 3,
                        subblock_label: 'C',
                        status: 'planned',
                        reserved_area_ha: '1.0000',
                        planted_area_ha: '0.0000',
                        planned_planting_at: '2026-11-20',
                        planted_at: null,
                        block: { id: 'block-a', name: 'Blok Selatan' },
                        variety: null,
                        canRecord: true,
                        canSync: false,
                        blockedReason: null,
                        plantingWindow: null,
                    },
                ],
            }),
        );

        expect(html).toContain('href="/petugas"');
        expect(html).toContain('Kembali ke beranda');
        expect(html).toContain(
            'href="/petugas/subbloks/cycle-planned/penanaman?back=%2Fpetugas"',
        );
    });
});
