export type BlockFarm = { id: string; name: string; total_area_ha: string };
export type FarmBlock = {
    id: string;
    farm_id: string;
    code: string;
    name: string;
    area_ha: string;
    description: string | null;
    is_active: boolean;
    land_clearing_started_at: string;
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
    planting_round_id: string;
    batch_number: number;
    subblock_label: string;
    reserved_area_ha: string;
    planted_area_ha: string;
    planted_at: string | null;
    status: string;
    stages: BatchStage[];
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
