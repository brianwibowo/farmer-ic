export type HarvestTransactionPhoto = {
    id: string;
    label: string | null;
    caption: string | null;
    url: string;
};

export type HarvestTransactionRow = {
    id: string;
    activity_date: string;
    farm: { id: string; name: string } | null;
    block: { id: string; code: string; name: string } | null;
    cycle: {
        id: string;
        batch_number: number | null;
        subblock_label: string | null;
        planted_at: string | null;
        planted_area_ha: string;
    } | null;
    variety: { id: string; name: string } | null;
    commodity: { id: string; name: string } | null;
    harvested_area_ha: string;
    gross_weight_ton: string;
    moisture_percentage: string | null;
    color: string | null;
    ripeness: string | null;
    cleanliness: string | null;
    worker_count: number | null;
    performer_name: string | null;
    notes: string | null;
    source: string;
    photos: HarvestTransactionPhoto[];
    log_url: string | null;
};

export type HarvestTransactionPage = {
    data: HarvestTransactionRow[];
    total: number;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type HarvestTransactionFilters = {
    search?: string;
    farm_id?: string;
    block_id?: string;
    variety_id?: string;
    from_date?: string;
    to_date?: string;
    per_page?: number;
};

export type HarvestTransactionSummary = {
    reports: number;
    area_ha: string;
    weight_ton: string;
};

export type HarvestTransactionFarmOption = { id: string; name: string };

export type HarvestTransactionBlockOption = {
    id: string;
    farm_id: string;
    code: string;
    name: string;
    farm?: { id: string; name: string } | null;
};

export type HarvestTransactionVarietyOption = {
    id: string;
    commodity_id: string;
    name: string;
    commodity: { id: string; name: string } | null;
};
