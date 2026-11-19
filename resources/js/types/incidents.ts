export type IncidentSeverity = 'light' | 'medium' | 'emergency';

export type IncidentStatus = 'open' | 'in_progress' | 'resolved';

export type IncidentView =
    | 'unresolved'
    | 'open'
    | 'in_progress'
    | 'resolved'
    | 'all';

export type IncidentSeverityOption = {
    value: IncidentSeverity;
    label: string;
};

export type IncidentFollowUpOption = {
    id: string;
    code: string;
    name: string;
    href: string;
};

export type IncidentTicketSummary = {
    total: number;
    open: number;
    in_progress: number;
    resolved: number;
    emergency_open: number;
};

export type IncidentActivityDetailItem = {
    label: string;
    value: string | number;
};

export type IncidentActivityPhoto = {
    id: string;
    url: string;
};

export type IncidentTicketActivity = {
    id: string;
    code: string | null;
    name: string;
    position: number | null;
    worker_count: number | null;
    performed_by: string | null;
    performer_name: string | null;
    block: string | null;
    subblok: string | null;
    notes: string | null;
    task_id: string | null;
    source: 'sop' | 'mandiri';
    details: IncidentActivityDetailItem[];
    photos: IncidentActivityPhoto[];
    work_date?: string;
    daily_log_id?: string | null;
};

export type IncidentTicketRow = {
    id: string;
    activity_id: string;
    farm_id: string;
    farm_name: string | null;
    block_id: string | null;
    block_name: string | null;
    planting_cycle_id: string | null;
    subblok: string | null;
    location_label: string;
    incident_category: string | null;
    severity: IncidentSeverity;
    severity_label: string;
    status: IncidentStatus;
    status_label: string;
    description: string | null;
    affected_area_ha: string | number | null;
    reporter_name: string | null;
    reported_at: string | null;
    work_date: string | null;
    admin_note: string | null;
    responded_by_name: string | null;
    responded_at: string | null;
    resolution_note: string | null;
    resolved_by_name: string | null;
    resolved_at: string | null;
    resolved_activity_id: string | null;
    resolved_activity_name: string | null;
    reopen_reason: string | null;
    reopened_by_name: string | null;
    reopened_at: string | null;
};

export type IncidentTicketDetail = IncidentTicketRow & {
    immediate_action: string | null;
    daily_log_id: string | null;
    report: IncidentTicketActivity | null;
    follow_up_activity: IncidentTicketActivity | null;
    follow_up_options: IncidentFollowUpOption[];
    follow_up_blocked_reason: string | null;
    can: {
        respond: boolean;
        resolve_directly: boolean;
        resolve_by_follow_up: boolean;
        reopen: boolean;
    };
};

export type IncidentTicketsPage = {
    data: IncidentTicketRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type IncidentTicketFilters = {
    search?: string;
    farm_id?: string;
    severity?: '' | IncidentSeverity;
    view?: IncidentView;
    per_page?: number;
};

export const incidentStatusLabels: Record<IncidentStatus, string> = {
    open: 'Perlu penanganan',
    in_progress: 'Sedang ditangani',
    resolved: 'Selesai',
};

export const incidentStatusClasses: Record<IncidentStatus, string> = {
    open: 'bg-warning/15 text-warning',
    in_progress: 'bg-info/10 text-info',
    resolved: 'bg-success/10 text-success',
};

export const incidentSeverityLabels: Record<IncidentSeverity, string> = {
    light: 'Ringan',
    medium: 'Sedang',
    emergency: 'Darurat',
};

export const incidentSeverityClasses: Record<IncidentSeverity, string> = {
    light: 'bg-info/10 text-info',
    medium: 'bg-warning/15 text-warning',
    emergency: 'bg-error/10 text-error',
};

export const incidentViewOptions: [IncidentView, string][] = [
    ['unresolved', 'Perlu tindak lanjut'],
    ['open', 'Menunggu respon'],
    ['in_progress', 'Sedang ditangani'],
    ['resolved', 'Selesai'],
    ['all', 'Semua'],
];

export const incidentSeverityFilterOptions: [IncidentSeverity, string][] = [
    ['emergency', 'Darurat'],
    ['medium', 'Sedang'],
    ['light', 'Ringan'],
];

export function formatIncidentDate(value: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
              date,
          );
}

export function formatIncidentDateTime(value: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(date);
}
