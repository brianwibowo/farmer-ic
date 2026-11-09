export type FarmPermissions = {
    update: boolean;
    delete: boolean;
    forceDelete: boolean;
};

export type FarmDeletionSummary = {
    groups: {
        key: string;
        label: string;
        count: number;
        effect: 'delete' | 'detach';
    }[];
    blockers: string[];
    token: string;
};
export type LandTenure = 'owned' | 'rented' | 'partnership';

export type FarmSopCommodity = { id: string; name: string };

export type FarmSopOption = {
    id: string;
    name: string;
    is_active: boolean;
    commodity: FarmSopCommodity | null;
};

export type FarmDefaultSop = FarmSopOption & {
    published_version: {
        id: string;
        version: number;
        published_at: string | null;
    } | null;
    tasks_count: number;
    is_usable: boolean;
};

export function landTenureLabel(value: LandTenure | null): string {
    return value === 'owned'
        ? 'Milik sendiri'
        : value === 'rented'
          ? 'Sewa'
          : value === 'partnership'
            ? 'Kerja sama'
            : 'Belum diisi';
}

export type FarmRow = {
    id: string;
    name: string;
    land_tenure_party: string | null;
    land_tenure: LandTenure | null;
    city: string | null;
    district: string | null;
    province: string | null;
    total_area_ha: string;
    can: FarmPermissions;
};

export type ManagedFarm = Omit<FarmRow, 'can'> & {
    target_volume_ton: string | null;
    progress_overview: {
        total: number;
        stages: { code: string; count: number }[];
    };
    commodity_id: string | null;
    commodity: FarmSopCommodity | null;
    district_code: string | null;
    region: RegionOption | null;
    owner_restricted: boolean;
    capacity: {
        block_area_ha: string;
        reserved_area_ha: string;
        minimum_area_ha: string;
    };
    owner_id: string | null;
    owner_name: string | null;
    pic_user_id: string | null;
    pic_name: string | null;
    pic_phone_number: string | null;
    team_name: string;
    address: string | null;
    latitude: string | null;
    longitude: string | null;
    image_url: string | null;
    default_sop_template_id: string | null;
    default_sop: FarmDefaultSop | null;
    active_cycles: number;
    active_area_ha: string;
    created_at: string | null;
};

export type RegionOption = {
    code: string;
    district: string;
    city: string;
    city_code: string;
    province: string;
    province_code: string;
};

export type RegionReferenceOption = {
    code: string;
    name: string;
    parent_code: string | null;
};

export type FarmFilters = {
    search?: string;
    city?: string;
    province?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
};

export type FarmPage = {
    data: FarmRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export function hectares(value: string | number): string {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 }).format(
        Number(value),
    );
}
