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
    obstacles: number;
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
    start_time: string;
    end_time: string;
    worker_count: number;
    notes: string | null;
    recorder_name: string | null;
    activities_count: number;
    obstacle_count: number;
};
export type LogbookActivity = {
    id: string;
    type: string | null;
    position: number;
    notes: string | null;
    obstacle_status: 'none' | 'reported' | 'not_recorded';
    obstacles: string | null;
    proposed_solution: string | null;
    action_taken: string | null;
    block_mapping: {
        form_version: number;
        block_id: string | null;
        block_name: string;
        length_m: string;
        width_m: string;
        row_spacing_cm: string | null;
        plant_spacing_cm: string | null;
        rows_per_bed: number | null;
    } | null;
};
export type LogbookDetail = Omit<
    LogbookRow,
    'activities_count' | 'obstacle_count'
> & {
    farm_id: string;
    recorded_by: string;
    activities: LogbookActivity[];
    created_at: string;
    updated_at: string | null;
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
