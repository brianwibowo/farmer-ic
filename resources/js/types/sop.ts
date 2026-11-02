export type SopFarm = { id: string; name: string };
export type SopVariety = { id: string; name: string; commodity: string };

export type SopActivityType = { id: string; code: string; name: string };

export type SopTask = {
    id: string;
    sop_template_version_id: string;
    activity_type_id: string;
    stage_code: string;
    name: string;
    instructions: string | null;
    schedule_anchor: 'planting_date' | 'stage_start';
    offset_days: number;
    tolerance_days: number;
    position: number;
    is_required: boolean;
    activity_type?: SopActivityType;
};

export type SopVersion = {
    id: string;
    sop_template_id: string;
    version: number;
    notes: string | null;
    status: 'draft' | 'published' | 'retired';
    published_at: string | null;
    creator?: { id: string; name: string };
    publisher?: { id: string; name: string } | null;
    tasks: SopTask[];
};

export type SopTemplateRow = {
    id: string;
    farm_id: string;
    variety_id: string;
    name: string;
    description: string | null;
    is_active: boolean;
    created_at: string;
    versions_count: number;
    can: { view: boolean; update: boolean; delete: boolean };
    farm: SopFarm;
    variety: SopVariety & { commodity?: { id: string; name: string } };
};

export type SopTemplateDetail = Omit<SopTemplateRow, 'versions_count' | 'can'> & {
    versions: SopVersion[];
};

export type SopFormTemplate = Pick<
    SopTemplateRow,
    'id' | 'farm_id' | 'variety_id' | 'name' | 'description' | 'is_active'
>;

export type SopFilters = {
    search?: string;
    farm_id?: string;
    variety_id?: string;
    is_active?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
};

export type SopPage = {
    data: SopTemplateRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type SopCan = {
    update: boolean;
    copy: boolean;
    manageVersions: boolean;
    manageTasks: boolean;
};

export const sopStageLabels: Record<string, string> = {
    land_cleaning: 'Land clearing',
    soil_preparation: 'Pengolahan tanah',
    basic_fertilizer: 'Pemupukan dasar',
    planting: 'Penanaman',
    maintenance: 'Pemeliharaan',
    harvest: 'Panen',
};

export function hstLabel(task: Pick<SopTask, 'offset_days'>): string {
    return `H${task.offset_days >= 0 ? '+' : ''}${task.offset_days}`;
}

export function sopDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value))
        : '—';
}
