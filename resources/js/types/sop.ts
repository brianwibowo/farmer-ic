export type SopTeam = { id: number; name: string };
export type SopCommodity = { id: string; name: string };

export type SopActivityCategory = 'planting' | 'maintenance' | 'harvest';
export type SopActivitySubcategory =
    | 'irrigation'
    | 'weeding'
    | 'follow_up_fertilization'
    | 'growth_check'
    | 'pest_control';

export type SopTask = {
    id: string;
    sop_template_version_id: string;
    activity_category: SopActivityCategory;
    activity_subcategory: SopActivitySubcategory | null;
    name: string;
    instructions: string | null;
    offset_days: number;
    tolerance_days: number;
    is_required: boolean;
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
    team_id: number;
    commodity_id: string;
    name: string;
    description: string | null;
    is_active: boolean;
    created_at: string;
    versions_count: number;
    can: { view: boolean; update: boolean; delete: boolean };
    team: SopTeam;
    commodity: SopCommodity | null;
};

export type SopTemplateDetail = Omit<SopTemplateRow, 'versions_count' | 'can'> & {
    versions: SopVersion[];
};

export type SopFormTemplate = Pick<
    SopTemplateRow,
    'id' | 'commodity_id' | 'name' | 'description' | 'is_active'
>;

export type SopFilters = {
    search?: string;
    commodity_id?: string;
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
    manageVersions: boolean;
    manageTasks: boolean;
};

export const sopActivityCategoryLabels: Record<SopActivityCategory, string> = {
    planting: 'Penanaman',
    maintenance: 'Perawatan',
    harvest: 'Panen',
};

export const sopActivitySubcategoryLabels: Record<SopActivitySubcategory, string> = {
    irrigation: 'Pengairan',
    weeding: 'Pendangiran',
    follow_up_fertilization: 'Pemupukan susulan',
    growth_check: 'Pengecekan pertumbuhan',
    pest_control: 'Pengendalian hama dan penyakit',
};

export function sopTaskCategoryLabel(task: Pick<SopTask, 'activity_category'>): string {
    return sopActivityCategoryLabels[task.activity_category] ?? task.activity_category;
}

export function sopTaskSubcategoryLabel(
    task: Pick<SopTask, 'activity_subcategory'>,
): string {
    return task.activity_subcategory
        ? (sopActivitySubcategoryLabels[task.activity_subcategory] ??
              task.activity_subcategory)
        : '-';
}

export function hstLabel(task: Pick<SopTask, 'offset_days'>): string {
    return `H${task.offset_days >= 0 ? '+' : ''}${task.offset_days}`;
}

export function sopDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value))
        : '—';
}
