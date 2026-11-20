import type { GeoJsonPolygon } from '@/lib/geoPolygon';

export type BlockFarm = {
    id: string;
    name: string;
    total_area_ha: string;
    latitude?: string | number | null;
    longitude?: string | number | null;
};
export type BatchCapacity = {
    farm_total_ha: string;
    block_total_ha: string;
    legacy_active_ha: string;
    farm_batches_ha: string;
    block_batches_ha: string;
    farm_remaining_ha: string;
    block_remaining_ha: string;
    available_ha: string;
};
export type FarmBlock = {
    id: string;
    farm_id: string;
    name: string;
    area_ha: string;
    description: string | null;
    is_active: boolean;
    commodity_id: string | null;
    default_sop_template_id: string | null;
    land_clearing_started_at: string | null;
    land_clearing_completed_at: string | null;
    boundary_geojson?: GeoJsonPolygon | null;
    mapped_area_ha?: string | null;
};
export type BlockOptions = {
    commodities: { id: string; name: string }[];
    sops: {
        id: string;
        name: string;
        commodity_id: string;
        is_active: boolean;
    }[];
};
export type BatchStage = {
    code: string;
    position: number;
    status: 'upcoming' | 'in_progress' | 'completed';
    started_at: string | null;
    completed_at: string | null;
};
export type PreparationBatch = {
    farm_cycle_id?: string | null;
    farm_cycle_number?: number | null;
    id: string;
    batch_number: number;
    subblock_label: string;
    reserved_area_ha: string;
    planted_area_ha: string;
    planned_planting_at: string | null;
    planted_at: string | null;
    status: string;
    stages: BatchStage[];
    age?: BatchAge;
    growth?: BatchGrowth;
};
export type BatchAge = {
    hst: number | null;
    as_of: string;
    status: 'available' | 'not_planted' | 'future' | 'cancelled';
};
export type BatchObservationPhoto = {
    id: string;
    url: string;
    available: boolean;
};

export type BatchObservationItem = {
    id: string;
    observed_at: string;
    observer_name: string | null;
    phase: string | null;
    notes: string | null;
    task_id: string | null;
    task_name: string | null;
    task_url: string | null;
    activity_url: string | null;
    photos: BatchObservationPhoto[];
};

export type BatchObservationTaskItem = {
    id: string;
    name: string;
    status: 'pending' | 'in_progress' | 'completed' | 'skipped';
    offset_days: number;
    scheduled_date: string | null;
    due_date: string | null;
    completed_at: string | null;
    url: string | null;
};

export type BatchGrowth = {
    available: boolean;
    as_of: string;
    hst: number | null;
    hst_percentage?: number | null;
    commodity: string | null;
    variety: string | null;
    estimated_harvest_at: string | null;
    observation: {
        id: string;
        observed_at: string;
        phase: string | null;
        percentage: number | null;
    } | null;
    observations?: BatchObservationItem[];
    tasks?: BatchObservationTaskItem[];
};
export const stageLabels: Record<string, string> = {
    land_clearing: 'Land Clearing',
    soil_preparation: 'Pengolahan tanah',
    basic_fertilizer: 'Pemupukan kandang',
    planting: 'Penanaman',
    maintenance: 'Perawatan',
    harvest: 'Panen',
};
export function blockDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeZone: 'Asia/Jakarta',
          }).format(new Date(value))
        : 'Belum dimulai';
}

export type BlockFormCapacity = {
    maximum_area_ha: string;
    minimum_area_ha: string;
    running_batches: number;
};
export type ManagedBlock = FarmBlock & {
    capacity: BlockFormCapacity;
    deletionBlocked: boolean;
    batch_capacity: BatchCapacity;
    batches: PreparationBatch[];
    can: { update: boolean; delete: boolean; createBatch: boolean };
};
