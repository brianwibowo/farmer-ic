import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import type { BatchGrowth } from '@/types/blocks';
import BatchPlantGrowth from './BatchPlantGrowth.vue';

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

describe('batch plant growth modal content', () => {
    const baseGrowth: BatchGrowth = {
        available: true,
        as_of: '2026-11-19',
        hst: 46,
        hst_percentage: 50,
        commodity: 'Jagung',
        variety: 'Hibrida BISI-18',
        estimated_harvest_at: '2027-01-10',
        observation: {
            id: 'obs-1',
            observed_at: '2026-11-18T10:00:00Z',
            phase: 'Vegetatif',
            percentage: null,
        },
        observations: [
            {
                id: 'obs-1',
                observed_at: '2026-11-18T10:00:00Z',
                observer_name: 'Budi Santoso',
                phase: 'Vegetatif',
                notes: 'Pertumbuhan batang kokoh.',
                task_id: 'task-obs-1',
                task_name: 'Pengamatan Fase Vegetatif',
                task_url: '/admin/tasks/task-obs-1',
                activity_url: '/admin/logbook/aktivitas/act-1',
                photos: [
                    {
                        id: 'photo-1',
                        url: '/kebun/farm-1/daily-logs/activities/act-1/photos/photo-1',
                        available: true,
                    },
                ],
            },
        ],
        tasks: [
            {
                id: 'task-obs-1',
                name: 'Pengamatan Fase Vegetatif',
                status: 'completed',
                offset_days: 30,
                scheduled_date: '2026-11-15',
                due_date: '2026-11-20',
                completed_at: '2026-11-18T11:00:00Z',
                url: '/admin/tasks/task-obs-1',
            },
        ],
    };

    it('renders HST progress %, observed phase badge, linked SOP tasks, and observation photos', async () => {
        const html = (
            await renderToString(
                h(BatchPlantGrowth, {
                    growth: baseGrowth,
                    plantedAt: '2026-10-04',
                    theme: 'admin-dashboard-theme',
                }),
            )
        ).replace(/\s+/g, ' ');

        expect(html).toContain('50%');
        expect(html).toContain('Progres umur (HST)');
        expect(html).toContain('Fase berdasarkan HST');
        expect(html).toContain('Pembungaan &amp; penyerbukan');
        expect(html).toContain('Fase hasil pengamatan');
        expect(html).toContain('Vegetatif');
        expect(html).toContain('Teramati');
        expect(html).toContain('Pengamatan Fase Vegetatif');
        expect(html).toContain('href="/admin/tasks/task-obs-1"');
        expect(html).toContain('Budi Santoso');
        expect(html).toContain('Pertumbuhan batang kokoh.');
        expect(html).toContain(
            '/kebun/farm-1/daily-logs/activities/act-1/photos/photo-1',
        );
    });

    it('falls back cleanly to catalog cycle duration when hst_percentage is absent and omits null URLs for owners', async () => {
        const html = (
            await renderToString(
                h(BatchPlantGrowth, {
                    growth: {
                        ...baseGrowth,
                        hst: 45,
                        hst_percentage: null,
                        estimated_harvest_at: null,
                        observation: null,
                        observations: [],
                        tasks: [
                            {
                                ...baseGrowth.tasks![0],
                                status: 'pending',
                                url: null,
                            },
                        ],
                    },
                    plantedAt: '2026-10-05',
                    theme: 'admin-dashboard-theme',
                }),
            )
        ).replace(/\s+/g, ' ');

        // 45 / 105 (BISI-18 final throughHst) = 42.9%
        expect(html).toContain('42,9');
        expect(html).toContain('Progres umur (HST)');
        expect(html).not.toContain('href="/admin/tasks/task-obs-1"');
        expect(html).toContain(
            'Belum ada laporan pengamatan pertumbuhan dari petugas',
        );
    });
});
