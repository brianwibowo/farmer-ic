import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import HomeSopTask from './HomeSopTask.vue';

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

describe('HomeSopTask', () => {
    it('renders overdue urgency, scheduled date, HST, and home back origin', async () => {
        const html = await renderToString(
            h(HomeSopTask, {
                task: {
                    id: 'task-1',
                    name: 'Pemupukan susulan',
                    instructions: null,
                    status: 'pending',
                    urgency: 'overdue',
                    is_required: true,
                    offset_days: 14,
                    activity_category: 'maintenance',
                    scheduled_date: '2026-11-18',
                    due_date: '2026-11-20',
                    stage: 'maintenance',
                    batch_number: 1,
                    subblock_label: 'Subblok A',
                    block_name: 'Blok Utara',
                    hst: 19,
                },
            }),
        );

        expect(html).toContain('href="/petugas/tasks/task-1?back=%2Fpetugas"');
        expect(html).toContain('Terlambat');
        expect(html).toContain('Terjadwal');
        expect(html).toContain('18 November 2026');
        expect(html).toContain('19 HST');
    });
});
