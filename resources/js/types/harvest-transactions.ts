export type HarvestTransactionPhoto = {
    id: string;
    label: string | null;
    caption: string | null;
    url: string;
};

export type HarvestTransactionRecord = {
    id: string;
    activity_date: string;
    harvested_area_ha: string;
    gross_weight_ton: string;
    performer_name: string | null;
    notes: string | null;
    photos: HarvestTransactionPhoto[];
    log_url: string | null;
};

export type HarvestTransactionRow = {
    key: string;
    cycle_id: string | null;
    first_activity_date: string | null;
    last_activity_date: string | null;
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
    report_count: number;
    reports: HarvestTransactionRecord[];
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
    subblocks: number;
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
