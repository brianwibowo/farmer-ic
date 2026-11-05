import type { LandTenure } from '@/types/farms';

export type GardenBlock = {
    id: string;
    code: string;
    name: string;
    area_ha: string;
    description: string | null;
    is_active: boolean;
    batches_count: number;
    land_clearing_started_at: string | null;
    land_clearing_completed_at: string | null;
};

export type GardenBatch = {
    id: string;
    batch_number: number;
    subblock_label: string;
    round_number: number;
    reserved_area_ha: string;
    planted_area_ha: string;
    status: string;
    planned_planting_at: string | null;
    planted_at: string | null;
    estimated_harvest_at: string | null;
    hst: number | null;
    current_stage: string | null;
    stages: {
        code: string;
        position: number;
        status: 'upcoming' | 'in_progress' | 'completed';
    }[];
};

export type PetugasGarden = {
    farm: {
        id: string;
        name: string;
        land_tenure: LandTenure | null;
        address: string | null;
        city: string | null;
        district: string | null;
        province: string | null;
        total_area_ha: string;
        latitude: string | null;
        longitude: string | null;
    } | null;
    summary: { blocks: number; batches: number };
    blocks: GardenBlock[];
    selected_block: GardenBlock | null;
    batches: {
        data: GardenBatch[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export function gardenArea(value: string): string {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 }).format(
        Number(value),
    );
}

export function gardenDate(value: string | null): string {
    if (!value) return 'Belum dijadwalkan';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeZone: 'Asia/Jakarta',
    }).format(
        new Date(value.length === 10 ? value + 'T00:00:00+07:00' : value),
    );
}
