import type { BatchStage } from '@/types/blocks';

export type OwnerPaginator<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
export type OwnerFarmRow = {
    id: string;
    name: string;
    land_tenure: string | null;
    city: string | null;
    district: string | null;
    province: string | null;
    total_area_ha: string;
};
export type OwnerBlock = {
    id: string;
    code: string;
    name: string;
    area_ha: string;
    description: string | null;
    is_active: boolean;
    cycles_count: number;
    land_clearing_started_at?: string | null;
    land_clearing_completed_at?: string | null;
};
export type OwnerCycle = {
    id: string;
    label: string;
    batch_number: number | null;
    status: string;
    reserved_area_ha: string | null;
    planted_area_ha: string;
    planned_planting_at: string | null;
    planted_at: string | null;
    estimated_harvest_at: string | null;
    variety: { name: string; commodity: string | null } | null;
    sop: { id: string; name: string; version: number } | null;
    stages: (BatchStage & { id: string; name: string })[];
    tasks: Array<{
        id: string;
        name: string;
        status: string;
        instructions: string | null;
        offset_days: number;
        tolerance_days: number;
        is_required: boolean;
        scheduled_date: string | null;
        due_date: string | null;
        completed_at: string | null;
    }>;
};
export const ownerStatus: Record<string, string> = {
    planned: 'Direncanakan',
    active: 'Aktif',
    harvested: 'Sudah dipanen',
    cancelled: 'Dibatalkan',
    pending: 'Belum dimulai',
    in_progress: 'Berlangsung',
    submitted: 'Sudah dilaporkan',
    completed: 'Selesai',
    skipped: 'Dilewati',
    open: 'Terbuka',
    resolved: 'Ditangani',
    closed: 'Ditutup',
};
export function ownerDate(value?: string | null): string {
    return value
        ? new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeZone: 'Asia/Jakarta',
          }).format(new Date(value))
        : 'Belum tercatat';
}
export function ownerArea(value?: string | number | null): string {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 }).format(
        Number(value ?? 0),
    );
}
