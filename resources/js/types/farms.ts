export type FarmPermissions = { update: boolean; delete: boolean; forceDelete: boolean };

export type FarmDeletionSummary = {
    groups: { key: string; label: string; count: number; effect: 'delete' | 'detach' }[];
    blockers: string[];
    token: string;
};
export type LandTenure = 'owned' | 'rented';

export function landTenureLabel(value: LandTenure | null): string {
    return value === 'owned' ? 'Milik sendiri' : value === 'rented' ? 'Sewa' : 'Belum diisi';
}

export type FarmRow = {
    id: string;
    name: string;
    land_tenure: LandTenure | null;
    city: string | null;
    district: string | null;
    province: string | null;
    total_area_ha: string;
    can: FarmPermissions;
};

export type ManagedFarm = Omit<FarmRow, 'can'> & {
    owner_id: string | null;
    owner_name: string | null;
    team_name: string;
    address: string | null;
    latitude: string | null;
    longitude: string | null;
    image_url: string | null;
    active_cycles: number;
    active_area_ha: string;
    created_at: string | null;
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
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 }).format(Number(value));
}
