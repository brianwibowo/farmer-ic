export type LogbookFarm = {
    id: string;
    name: string;
    city: string | null;
    district: string | null;
    province: string | null;
};

export type LogbookSummary = {
    farms: number;
    reports: number;
    today: number;
    activities: number;
    incidents: number;
};

export type LogbookPage<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type LogbookRow = {
    id: string;
    work_date: string;
    recorder_name: string | null;
    activities_count: number;
    incident_count: number;
};

export type LogbookActivityRow = {
    id: string;
    work_date: string;
    farm_id: string;
    farm_name: string | null;
    code: string | null;
    name: string;
    source: 'sop' | 'mandiri';
    block_name: string | null;
    subblok: string | null;
    performer_name: string | null;
    worker_count: number | null;
    notes: string | null;
    photos_count: number;
    daily_log_id: string | null;
};

export type LogbookActivityDetailField = {
    label: string;
    value: string | number;
};

export type LogbookActivityPhoto = {
    id: string;
    url: string;
};

export type LogbookReportActivity = {
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
    details: LogbookActivityDetailField[];
    photos: LogbookActivityPhoto[];
};

export type LogbookActivityShow = LogbookReportActivity & {
    work_date: string;
    created_at: string | null;
    updated_at: string | null;
    daily_log: {
        id: string;
        work_date: string;
        recorder_name: string | null;
        activities_count: number;
    } | null;
    task: {
        id: string;
        name: string;
        status: string;
    } | null;
};

export type LogbookDetail = {
    id: string;
    farm_id: string;
    work_date: string;
    recorded_by: string;
    recorder_name: string | null;
    activities: LogbookReportActivity[];
    created_at: string;
    updated_at: string | null;
};

export type LogbookIndexFilterOptions = {
    farms: { id: string; name: string }[];
    activity_types: { id: string; code: string; name: string }[];
    performers: { id: string; name: string }[];
};

export type LogbookIndexFilters = {
    search?: string;
    farm_id?: string;
    activity_type?: string;
    source?: 'sop' | 'mandiri' | '';
    performed_by?: string;
    from_date?: string;
    to_date?: string;
    per_page?: number;
};

export function logbookDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeZone: 'Asia/Jakarta',
          }).format(
              new Date(value.length === 10 ? value + 'T00:00:00+07:00' : value),
          )
        : 'Belum ada laporan';
}

export function farmLocation(farm: LogbookFarm): string {
    return (
        [farm.district, farm.city, farm.province].filter(Boolean).join(', ') ||
        'Lokasi belum diisi'
    );
}
