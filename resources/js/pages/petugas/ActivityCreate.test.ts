import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import type { PetugasTask } from '@/types/petugas-task';
import ActivityCreate from './ActivityCreate.vue';

const pageState = vi.hoisted(() => ({
    url: '/petugas/tasks/task-1/aktivitas/buat',
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
        usePage: () => ({ url: pageState.url }),
        useForm: (data: Record<string, unknown>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                progress: null,
                clearErrors: () => undefined,
                transform: () => undefined,
                post: () => undefined,
            }),
    };
});

function taskFixture(
    category: PetugasTask['activity_category'],
    commodityName: string | null = 'Jagung',
): PetugasTask {
    return {
        id: 'task-1',
        name:
            category === 'growth_observation'
                ? 'Pengamatan Fase Vegetatif'
                : 'Pemupukan',
        instructions: 'Catat fase pertumbuhan.',
        status: 'pending',
        urgency: 'today',
        activity_category: category,
        offset_days: 30,
        scheduled_date: '2026-11-19',
        due_date: '2026-11-22',
        reporting_window: { start: '2026-11-19', end: '2026-11-22' },
        is_required: true,
        linked_activities_count: 0,
        completed_at: null,
        completed_by: null,
        completion_note: null,
        can: { record: true, complete: false },
        subblok: {
            id: 'cycle-1',
            number: 1,
            label: 'Subblok A',
            planned_planting_at: '2026-10-20',
            planted_at: '2026-10-20',
            hst: 30,
            farm: { id: 'farm-1', name: 'Kebun Demo' },
            block: { id: 'block-1', name: 'Blok A' },
            commodity: commodityName
                ? { id: 'com-1', name: commodityName }
                : null,
            variety: { id: 'var-1', name: 'Hibrida BISI-18' },
        },
    };
}

describe('petugas task ActivityCreate', () => {
    it('renders commodity stage select with HST suggestion and hides worker_count for growth_observation tasks', async () => {
        const html = (
            await renderToString(
                h(ActivityCreate, {
                    task: taskFixture('growth_observation', 'Jagung'),
                    fields: [
                        {
                            key: 'growth_phase',
                            label: 'Fase pertumbuhan',
                            type: 'text',
                        },
                    ],
                    requiresWorkerCount: false,
                    areaLimitM2: 0,
                    numericLimits: { workerCount: 500, quantityKg: 999999 },
                    today: '2026-11-19',
                    workDate: '2026-11-19',
                }),
            )
        ).replace(/\s+/g, ' ');

        expect(html).not.toContain('activity-worker-count');
        expect(html).toContain('Fase pertumbuhan *');
        expect(html).toContain('id="detail-growth_phase"');
        expect(html).toContain('role="combobox"');
        expect(html).not.toContain('<input id="detail-growth_phase"');
        expect(html).toContain(
            'Saran sesuai umur tanaman (30 HST): Vegetatif.',
        );
    });

    it('falls back to text input when commodity has no growthStages catalog', async () => {
        const html = (
            await renderToString(
                h(ActivityCreate, {
                    task: taskFixture('growth_observation', 'Sorgum'),
                    fields: [
                        {
                            key: 'growth_phase',
                            label: 'Fase pertumbuhan',
                            type: 'text',
                        },
                    ],
                    requiresWorkerCount: false,
                    areaLimitM2: 0,
                    numericLimits: { workerCount: 500, quantityKg: 999999 },
                    today: '2026-11-19',
                    workDate: '2026-11-19',
                }),
            )
        ).replace(/\s+/g, ' ');

        expect(html).not.toContain('activity-worker-count');
        expect(html).not.toContain('role="combobox"');
        expect(html).toContain('Contoh: Vegetatif, Pembungaan, Siap panen');
    });

    it('keeps worker_count visible for standard maintenance tasks', async () => {
        const html = (
            await renderToString(
                h(ActivityCreate, {
                    task: taskFixture('maintenance', 'Jagung'),
                    fields: [
                        { key: 'area', label: 'Luas area', type: 'number' },
                        {
                            key: 'area_unit',
                            label: 'Satuan luas',
                            type: 'select',
                            options: ['m²', 'Ha'],
                        },
                    ],
                    requiresWorkerCount: true,
                    areaLimitM2: 5000,
                    numericLimits: { workerCount: 500, quantityKg: 999999 },
                    today: '2026-11-19',
                    workDate: '2026-11-19',
                }),
            )
        ).replace(/\s+/g, ' ');

        expect(html).toContain('activity-worker-count');
    });
});
