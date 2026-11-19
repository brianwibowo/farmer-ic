import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import DailyReportContent from './DailyReportContent.vue';

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

describe('daily report photo fallback', () => {
    it('shows unavailable evidence without requesting the missing file', async () => {
        const html = await renderToString(
            h(DailyReportContent, {
                farm: { id: 'farm-a', name: 'Kebun Utara' },
                date: '2026-11-17',
                log: null,
                canRecord: false,
                legacyActivity: {
                    id: 'activity-a',
                    name: 'Perawatan',
                    position: null,
                    worker_count: 2,
                    performed_by: 'user-a',
                    performer_name: 'Petugas',
                    subblok: null,
                    block: null,
                    notes: null,
                    task_id: null,
                    details: [],
                    photos: [
                        {
                            id: 'missing',
                            url: '/missing-photo',
                            available: false,
                        },
                        {
                            id: 'present',
                            url: '/present-photo',
                            available: true,
                        },
                    ],
                },
            }),
        );
        expect(html).toContain('Foto 1 tidak tersedia');
        expect(html).not.toContain('src="/missing-photo"');
        expect(html).toContain('src="/present-photo"');
    });
});
