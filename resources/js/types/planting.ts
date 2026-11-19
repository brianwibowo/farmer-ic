export type PlantingCycleContext = {
    farm_cycle_number?: number | null;
    id: string;
    batch_number: number;
    subblock_label: string;
    status: string;
    reserved_area_ha: string;
    planted_area_ha: string;
    planned_planting_at: string | null;
    planted_at: string | null;
    block: { id: string; name: string };
    variety: { id: string; name: string } | null;
};

export type PlantingReport = {
    id: string;
    date: string;
    notes: string | null;
    input?: { quantity: string | number; quantity_unit: string } | null;
    detail: {
        seed_name: string;
        quantity_kg: string | null;
        area_ha: string;
    } | null;
    photos: { id: string; url: string }[];
};
