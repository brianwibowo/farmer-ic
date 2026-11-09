export type OwnerReadPage<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    from?: number | null;
    to?: number | null;
};

export type OwnerOption = { id: string; name: string };

export type OwnerActivityRow = {
    id: string;
    description: string | null;
    status: string | null;
    activity_date: string;
    farm: OwnerOption | null;
    block_name: string | null;
    performer: OwnerOption | null;
    type: { code: string; name: string } | null;
    cycle: { id: string; label: string } | null;
};

export type OwnerActivityDetail = OwnerActivityRow & {
    worker_count: number | null;
    photos: Array<{
        id: string;
        image_url: string;
        caption: string | null;
        label: string | null;
    }>;
    details: Array<{ label: string; value: string | null }>;
    log: {
        id: string;
        work_date: string;
        start_time: string;
        end_time: string | null;
        notes: string | null;
    } | null;
    obstacle_status: string | null;
    obstacles: string | null;
    proposed_solution: string | null;
    action_taken: string | null;
};

export type OwnerSopTask = {
    id: string;
    name: string;
    instructions: string | null;
    activity_category: string;
    activity_subcategory: string | null;
    offset_days: number;
    tolerance_days: number;
    is_required: boolean;
};

export type OwnerSopRow = {
    id: string;
    name: string;
    description: string | null;
    is_active: boolean;
    commodity: OwnerOption | null;
    versions_count: number;
    published_version: number | null;
};

export type OwnerSopDetail = OwnerSopRow & {
    versions: Array<{
        id: string;
        version: number;
        status: string;
        notes: string | null;
        published_at: string | null;
        tasks: OwnerSopTask[];
    }>;
};

export type OwnerUserRow = {
    id: string;
    name: string;
    email: string;
    phone_number: string | null;
    role: string | null;
    is_active: boolean;
    assigned_farm: OwnerOption | null;
};

export type OwnerUserDetail = OwnerUserRow & {
    teams: Array<{ id: number; name: string }>;
};

export function ownerReadDate(value: string | null, withTime = false): string {
    if (!value) return 'Belum tercatat';
    const date = new Date(
        value.length === 10 ? `${value}T00:00:00+07:00` : value,
    );
    if (Number.isNaN(date.valueOf())) return 'Tanggal tidak tersedia';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        ...(withTime ? { timeStyle: 'short' as const } : {}),
        timeZone: 'Asia/Jakarta',
    }).format(date);
}

export function ownerReadStatus(value: string | null): string {
    if (!value) return 'Belum ada status';
    const labels: Record<string, string> = {
        pending: 'Menunggu',
        planned: 'Direncanakan',
        active: 'Aktif',
        in_progress: 'Berlangsung',
        completed: 'Selesai',
        submitted: 'Diajukan',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        cancelled: 'Dibatalkan',
        draft: 'Draf',
        published: 'Diterbitkan',
        retired: 'Diarsipkan',
        none: 'Tanpa kendala',
        reported: 'Ada kendala',
        not_recorded: 'Status kendala belum dicatat',
        has_obstacle: 'Ada kendala',
        blocked: 'Terkendala',
        resolved: 'Ditangani',
    };
    return labels[value] ?? value.replaceAll('_', ' ');
}

export function ownerReadRole(value: string | null): string {
    const labels: Record<string, string> = {
        owner: 'Owner',
        admin: 'Admin',
        petugas: 'Petugas',
        superadmin: 'Superadmin',
    };
    return value ? (labels[value] ?? value) : 'Belum ada role';
}
