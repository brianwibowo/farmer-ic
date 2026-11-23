import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type {
    PetugasTask,
    PetugasTaskPageProps,
    TaskActivityReport,
} from '@/types/petugas-task';
import Aktivitas from './Aktivitas.vue';
import TaskShow from './TaskShow.vue';

const state = vi.hoisted(() => ({
    remembered: null as Record<string, unknown> | null,
    errors: {} as Record<string, string>,
    processing: false,
    url: '/petugas/tasks/task-a?work_date=2026-11-17',
}));
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
        usePage: () => ({ url: state.url }),
        router: { get: vi.fn() },
        useRemember: (initial: object) => state.remembered ?? initial,
        useForm: () => ({
            note: '',
            errors: state.errors,
            processing: state.processing,
            post: vi.fn(),
        }),
    };
});

const task: PetugasTask = {
    id: 'task-a',
    name: 'Pemupukan susulan',
    instructions: 'Berikan pupuk sesuai kebutuhan.',
    status: 'in_progress',
    urgency: 'today',
    activity_category: 'maintenance',
    offset_days: 21,
    scheduled_date: '2026-11-17',
    due_date: '2026-11-19',
    reporting_window: { start: '2026-11-17', end: '2026-11-19' },
    is_required: true,
    linked_activities_count: 2,
    completed_at: null,
    completed_by: null,
    completion_note: null,
    can: { record: true, complete: true },
    subblok: {
        id: 'subblok-a',
        number: 2,
        label: 'A',
        planned_planting_at: '2026-10-27',
        planted_at: '2026-10-27',
        hst: 21,
        farm: { id: 'farm-a', name: 'Kebun Utara' },
        block: { id: 'block-a', name: 'Blok Utara' },
        variety: { id: 'variety-a', name: 'Bisi 18' },
    },
};
const listProps: PetugasTaskPageProps = {
    tasks: {
        data: [
            task,
            {
                ...task,
                id: 'task-b',
                name: 'Panen jagung',
                status: 'completed',
                urgency: 'completed',
                activity_category: 'harvest',
            },
        ],
        total: 2,
        from: 1,
        to: 2,
        current_page: 1,
        last_page: 1,
    },
    options: {
        blocks: [{ id: 'block-a', name: 'Blok Utara' }],
        subbloks: [
            { id: 'subblok-a', block_id: 'block-a', label: 'A', number: 2 },
        ],
        categories: ['maintenance', 'harvest'],
    },
    filters: { status: 'active' },
    summary: { overdue: 2, today: 1, upcoming: 3, completed: 1 },
    farm: { id: 'farm-a', name: 'Kebun Utara' },
    defaultSopMissing: false,
};
const report: TaskActivityReport = {
    id: 'report-a',
    work_date: '2026-11-17',
    performer_name: 'Petugas A',
    worker_count: 3,
    notes: 'Pekerjaan selesai separuh.',
    details: { area: 0, method: 'Manual' },
    photos: [
        { id: 'missing', url: '/foto-hilang', available: false },
        { id: 'present', url: '/foto-ada', available: true },
    ],
};
const detailProps = {
    task,
    reports: [report],
    fields: [
        { key: 'area', label: 'Luas dikerjakan', type: 'number' as const },
        { key: 'method', label: 'Metode', type: 'text' as const },
    ],
    canRecord: true,
    blockedReason: null,
    completionGuidance: 'Laporan tidak otomatis menyelesaikan tugas.',
};

beforeEach(() => {
    state.remembered = null;
    state.errors = {};
    state.processing = false;
    state.url = '/petugas/tasks/task-a?work_date=2026-11-17';
});

describe('task list presentation', () => {
    it('paginates the filtered task list with the report page sizes', async () => {
        const manyTasks = Array.from({ length: 12 }, (_, index) => ({
            ...task,
            id: `task-${index + 1}`,
            name: `Pekerjaan ${index + 1}`,
        }));
        const html = await renderToString(
            h(Aktivitas, {
                ...listProps,
                tasks: {
                    data: manyTasks.slice(0, 10),
                    total: 12,
                    from: 1,
                    to: 10,
                    current_page: 1,
                    last_page: 2,
                },
            }),
        );
        expect(html).toContain('Pekerjaan 10');
        expect(html).not.toContain('Pekerjaan 11');
        expect(html).toContain('dari 12 tugas');
        expect(html).toContain('Pagination tugas');
        expect(html).toContain('Halaman 2');
        expect(html).toContain('Jumlah tugas per halaman');
    });

    it('shows scoped summary, filters, active task metadata, and its detail link', async () => {
        const html = await renderToString(h(Aktivitas, listProps));
        for (const text of [
            'Ringkasan tugas',
            'Terlambat',
            'Hari ini',
            'Mendatang',
            'Cari tugas',
            'Status tugas',
            'Kategori tugas',
            'Subblok',
            'Pemupukan susulan',
            'Blok Utara',
            '17 November 2026',
            'Target HST 21',
            'Dikerjakan',
            '2 laporan terkait',
        ])
            expect(html).toContain(text);
        expect(html).toContain('href="/petugas/tasks/task-a"');
        expect(html).toContain('Panen jagung');
    });

    it('shows assignment and filtered empty states without sample tasks', async () => {
        const html = await renderToString(
            h(Aktivitas, {
                ...listProps,
                tasks: null,
                farm: null,
                defaultSopMissing: true,
            }),
        );
        expect(html).toContain('Belum ada penugasan lahan');
        expect(html).toContain('Hubungi Admin');
        expect(html).not.toContain('Pemupukan susulan');
    });

    it('shows closed-subblock tasks in history with their real status and no urgency', async () => {
        const historical: PetugasTask = {
            ...task,
            status: 'pending',
            urgency: 'overdue',
            can: { record: false, complete: false },
            subblok: { ...task.subblok, status: 'cancelled' },
        };
        const html = await renderToString(
            h(Aktivitas, {
                ...listProps,
                tasks: {
                    data: [historical],
                    total: 1,
                    from: 1,
                    to: 1,
                    current_page: 1,
                    last_page: 1,
                },
                filters: { status: 'all', view: 'history' },
            }),
        );
        expect(html).toContain('Riwayat tugas');
        expect(html).toContain('Pemupukan susulan');
        expect(html).toContain('Terjadwal');
        expect(html).toContain('Subblok ditutup · hanya dapat dilihat');
        expect(html).not.toContain('Terlambat');
    });
});

describe('task detail presentation', () => {
    it('returns to history and keeps closed-subblock tasks read-only', async () => {
        const historical: PetugasTask = {
            ...task,
            status: 'pending',
            urgency: 'overdue',
            can: { record: false, complete: false },
            subblok: { ...task.subblok, status: 'harvested' },
        };
        const html = await renderToString(
            h(TaskShow, {
                ...detailProps,
                task: historical,
                canRecord: false,
                completionGuidance: 'Subblok sudah ditutup.',
            }),
        );
        expect(html).toContain('href="/petugas/aktivitas?view=history"');
        expect(html).toContain('Terjadwal');
        expect(html).not.toContain('Terlambat');
        expect(html).not.toContain('Catat aktivitas tugas ini');
        expect(html).not.toContain('Selesaikan tugas');
    });

    it('keeps task context, instructions, actions, linked details and real photo states', async () => {
        const html = await renderToString(h(TaskShow, detailProps));
        for (const text of [
            'Detail tugas',
            'Pemupukan susulan',
            'Kebun Utara',
            'Blok Utara',
            'Bisi 18',
            'Estimasi HOK',
            'Tidak ditetapkan',
            'HST 21',
            'Jendela pelaporan',
            '17 November 2026',
            '19 November 2026',
            'Instruksi SOP',
            'Berikan pupuk sesuai kebutuhan.',
            'Hari ini',
            'Dikerjakan',
            'Wajib',
            'Catat aktivitas tugas ini',
            'Selesaikan tugas',
            'Aktivitas terkait',
            'Petugas A',
            '3 pekerja',
            'Luas dikerjakan',
            'Manual',
            'Pekerjaan selesai separuh.',
            'Foto 1 tidak tersedia',
        ])
            expect(html).toContain(text);
        expect(html).toContain(
            'href="/petugas/aktivitas?work_date=2026-11-17"',
        );
        expect(html).toContain(
            'href="/petugas/tasks/task-a/aktivitas/buat?work_date=2026-11-17"',
        );
        expect(html).toContain('href="/petugas/laporan/report-a"');
        expect(html).toContain('src="/foto-ada"');
        expect(html).not.toContain('src="/foto-hilang"');
        expect(html).not.toContain('href="/foto-hilang"');
        expect(html).toMatch(/>\s*0\s*<\/dd>/);
    });

    it('shows blocked, skipped, empty, and nullable report states without enabling actions', async () => {
        const html = await renderToString(
            h(TaskShow, {
                ...detailProps,
                task: {
                    ...task,
                    status: 'skipped',
                    urgency: 'completed',
                    instructions: null,
                    scheduled_date: null,
                    reporting_window: null,
                    is_required: false,
                    can: { record: false, complete: false },
                    subblok: {
                        ...task.subblok,
                        label: null,
                        hst: null,
                        planted_at: null,
                    },
                },
                reports: [
                    {
                        ...report,
                        work_date: null,
                        worker_count: null,
                        details: null,
                        notes: null,
                        photos: [],
                    },
                ],
                canRecord: false,
                blockedReason: 'Pencatatan belum tersedia.',
            }),
        );
        for (const text of [
            'Dilewati',
            'Wajib',
            'Subblok 2',
            'Belum dijadwalkan',
            'Estimasi HOK',
            'Tidak ditetapkan',
            'Tidak ada instruksi tambahan.',
            'Pencatatan belum tersedia.',
            'Tanggal kerja tidak tercatat',
        ])
            expect(html).toContain(text);
        expect(html).not.toContain('Opsional');
        expect(html).not.toContain('Umur tanaman');
        expect(html).not.toContain(
            'href="/petugas/tasks/task-a/aktivitas/buat',
        );
        expect(html).not.toContain('3 pekerja');
        expect(html).not.toContain('Catatan penyelesaian (opsional)');
    });

    it('shows completion feedback and linked-report empty state', async () => {
        state.errors = {
            note: 'Catatan terlalu panjang.',
            status: 'Tugas sudah ditutup.',
        };
        state.processing = true;
        const html = await renderToString(
            h(TaskShow, { ...detailProps, reports: [] }),
        );
        expect(html).toContain('Catatan terlalu panjang.');
        expect(html).toContain('Tugas sudah ditutup.');
        expect(html).toContain('Menyimpan penyelesaian tugas');
        expect(html).toContain('Belum ada laporan untuk tugas ini');
        expect(html).toContain('disabled');
    });

    it('renders completed information instead of a completion form', async () => {
        const html = await renderToString(
            h(TaskShow, {
                ...detailProps,
                task: {
                    ...task,
                    status: 'completed',
                    urgency: 'completed',
                    completed_at: '2026-11-17T08:00:00Z',
                    completion_note: 'Pekerjaan tuntas.',
                    can: { record: false, complete: false },
                },
                canRecord: false,
            }),
        );
        expect(html).toContain('Tugas selesai');
        expect(html).toContain('Pekerjaan tuntas.');
        expect(html).not.toContain('Catatan penyelesaian (opsional)');
    });

    it('displays Estimasi HOK in place of Umur tanaman with fallback when null', async () => {
        const withHok = await renderToString(
            h(TaskShow, {
                ...detailProps,
                task: { ...task, hok: 5 },
            }),
        );
        expect(withHok).toContain('Estimasi HOK');
        expect(withHok).toContain('5 HOK');
        expect(withHok).not.toContain('Umur tanaman');
        expect(withHok).not.toContain('Tidak ditetapkan');

        const withoutHok = await renderToString(
            h(TaskShow, {
                ...detailProps,
                task: { ...task, hok: null },
            }),
        );
        expect(withoutHok).toContain('Estimasi HOK');
        expect(withoutHok).toContain('Tidak ditetapkan');
        expect(withoutHok).not.toContain('Umur tanaman');
    });
});
