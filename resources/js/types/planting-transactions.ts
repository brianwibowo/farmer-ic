export type PlantingTransactionPhoto = {
    id: string;
    label: string | null;
    caption: string | null;
    url: string;
};

export type PlantingTransactionRow = {
    id: string;
    activity_date: string;
    farm: { id: string; name: string } | null;
    block: { id: string; code: string; name: string } | null;
    cycle: {
        id: string;
        batch_number: number | null;
        subblock_label: string | null;
        planted_at: string | null;
    } | null;
    variety: { id: string; name: string } | null;
    commodity: { id: string; name: string } | null;
    area_ha: string;
    seed_name: string | null;
    quantity_kg: string | null;
    planting_pattern: string | null;
    worker_count: number | null;
    performer_name: string | null;
    notes: string | null;
    source: string;
    photos: PlantingTransactionPhoto[];
    log_url: string | null;
};

export type PlantingTransactionPage = {
    data: PlantingTransactionRow[];
    total: number;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type PlantingTransactionFilters = {
    search?: string;
    farm_id?: string;
    block_id?: string;
    variety_id?: string;
    from_date?: string;
    to_date?: string;
    per_page?: number;
};

export type PlantingTransactionSummary = {
    reports: number;
    area_ha: string;
    seed_kg: string;
};

export type PlantingTransactionFarmOption = { id: string; name: string };

export type PlantingTransactionBlockOption = {
    id: string;
    farm_id: string;
    code: string;
    name: string;
    farm?: { id: string; name: string } | null;
};

export type PlantingTransactionVarietyOption = {
    id: string;
    commodity_id: string;
    name: string;
    commodity: { id: string; name: string } | null;
};
