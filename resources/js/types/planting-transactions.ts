export type PlantingTransactionPhoto = {
    id: string;
    label: string | null;
    caption: string | null;
    url: string;
};

export type PlantingTransactionRecord = {
    id: string;
    activity_date: string;
    area_ha: string;
    seed_name: string | null;
    quantity_kg: string | null;
    performer_name: string | null;
    notes: string | null;
    photos: PlantingTransactionPhoto[];
    log_url: string | null;
};

export type PlantingTransactionRow = {
    key: string;
    cycle_id: string | null;
    first_activity_date: string | null;
    last_activity_date: string | null;
    planted_at: string | null;
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
    seed_kg: string;
    report_count: number;
    reports: PlantingTransactionRecord[];
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
    subblocks: number;
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
