import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import type {
    IncidentTicketDetail,
    IncidentTicketRow,
} from '@/types/incidents';
import AdminIncidentsIndex from '@/pages/incidents/Index.vue';
import AdminIncidentShow from '@/pages/incidents/Show.vue';
import PetugasIncidents from './Incidents.vue';
import PetugasIncidentShow from './IncidentShow.vue';

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
        router: { get: vi.fn() },
        usePage: () => ({ url: '/admin/incidents', props: {} }),
        useForm: (data: Record<string, unknown>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                post: vi.fn(),
                reset: vi.fn(),
                clearErrors: vi.fn(),
            }),
    };
});

const baseRow: IncidentTicketRow = {
    id: 'ticket-1',
    activity_id: 'act-1',
    farm_id: 'farm-1',
    farm_name: 'Kebun Cariu',
    block_id: 'block-1',
    block_name: 'Blok A',
    planting_cycle_id: 'cycle-1',
    subblok: 'S-01',
    location_label: 'Kebun Cariu · Blok A · S-01',
    incident_category: 'Hama',
    severity: 'emergency',
    severity_label: 'Darurat',
    status: 'in_progress',
    status_label: 'Sedang ditangani',
    description: 'Serangan ulat di petak selatan.',
    affected_area_ha: '0.0300',
    reporter_name: 'Budi Petugas',
    reported_at: '2026-11-18T02:00:00Z',
    work_date: '2026-11-18',
    admin_note: 'Segera semprot insektisida terarah.',
    responded_by_name: 'Sari Admin',
    responded_at: '2026-11-18T03:00:00Z',
    resolution_note: null,
    resolved_by_name: null,
    resolved_at: null,
    resolved_activity_id: null,
    resolved_activity_name: null,
    reopen_reason: null,
    reopened_by_name: null,
    reopened_at: null,
};

const detail: IncidentTicketDetail = {
    ...baseRow,
    immediate_action: 'Pengecekan awal baris selatan.',
    daily_log_id: 'log-1',
    report: {
        id: 'act-1',
        code: 'incident',
        name: 'Pelaporan Insiden',
        position: 1,
        worker_count: 2,
        performed_by: 'user-1',
        performer_name: 'Budi Petugas',
        block: 'Blok A',
        subblok: 'S-01',
        notes: 'Serangan ulat di petak selatan.',
        task_id: null,
        source: 'mandiri',
        details: [
            { label: 'Kategori insiden', value: 'Hama' },
            { label: 'Tingkat kedaruratan', value: 'Darurat' },
        ],
        photos: [],
    },
    follow_up_activity: null,
    follow_up_options: [
        {
            id: 'type-1',
            code: 'maintenance',
            name: 'Perawatan',
            href: '/petugas/aktivitas/mandiri/maintenance?incident_ticket_id=ticket-1',
        },
        {
            id: 'type-2',
            code: 'other',
            name: 'Lainnya',
            href: '/petugas/aktivitas/mandiri/other?incident_ticket_id=ticket-1',
        },
    ],
    follow_up_blocked_reason: null,
    can: {
        respond: true,
        resolve_directly: true,
        resolve_by_follow_up: true,
        reopen: false,
    },
};

describe('incident ticketing pages', () => {
    it('renders the petugas incident queue and detail follow-up options', async () => {
        const listHtml = await renderToString(
            h(PetugasIncidents, {
                farm: { id: 'farm-1', name: 'Kebun Cariu' },
                tickets: [baseRow],
                summary: {
                    total: 1,
                    open: 0,
                    in_progress: 1,
                    resolved: 0,
                    emergency_open: 1,
                },
                filters: { view: 'unresolved' },
            }),
        );

        expect(listHtml).toContain('Tiket insiden');
        expect(listHtml).toContain('/petugas/incidents/ticket-1');
        expect(listHtml).toContain('Arahan Admin:');
        expect(listHtml).toContain('Segera semprot insektisida terarah.');
        expect(listHtml).toContain('Darurat');

        const detailHtml = await renderToString(
            h(PetugasIncidentShow, { ticket: detail }),
        );

        expect(detailHtml).toContain('Tindak lanjuti &amp; selesaikan');
        expect(detailHtml).toContain(
            '/petugas/aktivitas/mandiri/maintenance?incident_ticket_id=ticket-1',
        );
        expect(detailHtml).toContain('Catat Perawatan');
        expect(detailHtml).toContain('Arahan Admin');
    });

    it('renders the admin incident queue and detail actions', async () => {
        const adminListHtml = await renderToString(
            h(AdminIncidentsIndex, {
                tickets: {
                    data: [baseRow],
                    current_page: 1,
                    last_page: 1,
                    total: 1,
                    from: 1,
                    to: 1,
                    prev_page_url: null,
                    next_page_url: null,
                },
                summary: {
                    total: 1,
                    open: 0,
                    in_progress: 1,
                    resolved: 0,
                    emergency_open: 1,
                },
                filters: { view: 'unresolved', per_page: 15 },
                farms: [{ id: 'farm-1', name: 'Kebun Cariu' }],
                severities: [
                    { value: 'light', label: 'Ringan' },
                    { value: 'medium', label: 'Sedang' },
                    { value: 'emergency', label: 'Darurat' },
                ],
            }),
        );

        expect(adminListHtml).toContain('Tiket Insiden');
        expect(adminListHtml).toContain('/admin/incidents/ticket-1');
        expect(adminListHtml).toContain('Darurat');
        expect(adminListHtml).toContain('Sedang ditangani');

        const adminDetailHtml = await renderToString(
            h(AdminIncidentShow, {
                ticket: detail,
                severities: [
                    { value: 'light', label: 'Ringan' },
                    { value: 'medium', label: 'Sedang' },
                    { value: 'emergency', label: 'Darurat' },
                ],
            }),
        );

        expect(adminDetailHtml).toContain('Tanggapan &amp; Arahan Admin');
        expect(adminDetailHtml).toContain('Tutup &amp; Selesaikan Langsung');
        expect(adminDetailHtml).toContain('/admin/logbook/farm-1/log-1');
    });
});
