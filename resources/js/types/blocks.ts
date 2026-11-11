export type BlockFarm = { id: string; name: string; total_area_ha: string };
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
    code: string;
    name: string;
    area_ha: string;
    description: string | null;
    is_active: boolean;
    land_clearing_started_at: string | null;
    land_clearing_completed_at: string | null;
};
export type BatchStage = {
    code: string;
    position: number;
    status: 'upcoming' | 'in_progress' | 'completed';
    started_at: string | null;
    completed_at: string | null;
};
export type PreparationBatch = {
    id: string;
    batch_number: number;
    subblock_label: string;
    reserved_area_ha: string;
    planted_area_ha: string;
    planned_planting_at: string | null;
    planted_at: string | null;
    status: string;
    stages: BatchStage[];
    growth?: BatchGrowth;
};
export type BatchGrowth = {
    available: boolean;
    as_of: string;
    hst: number | null;
    commodity: string | null;
    variety: string | null;
    estimated_harvest_at: string | null;
    observation: {
        id: string;
        observed_at: string;
        phase: string | null;
        percentage: number | null;
    } | null;
};
export const stageLabels: Record<string, string> = {
    land_clearing: 'Land Clearing',
    soil_preparation: 'Pengolahan tanah',
    basic_fertilizer: 'Pemupukan dasar',
    planting: 'Penanaman',
    maintenance: 'Pemeliharaan',
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
