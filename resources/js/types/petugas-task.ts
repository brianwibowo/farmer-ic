export type PetugasTaskStatus =
    | 'pending'
    | 'in_progress'
    | 'completed'
    | 'skipped';

export type PetugasTaskUrgency = 'overdue' | 'today' | 'upcoming' | 'completed';

export type PetugasTaskCategory =
    | 'maintenance'
    | 'growth_observation'
    | 'harvest';

export type PetugasTask = {
    id: string;
    name: string;
    instructions: string | null;
    status: PetugasTaskStatus;
    urgency: PetugasTaskUrgency;
    activity_category: PetugasTaskCategory;
    offset_days: number;
    hok?: number | null;
    scheduled_date: string | null;
    due_date: string | null;
    reporting_window: { start: string; end: string } | null;
    is_required: boolean;
    linked_activities_count: number;
    completed_at: string | null;
    completed_by: string | null;
    completion_note: string | null;
    can: {
        record: boolean;
        complete: boolean;
    };
    subblok: {
        id: string;
        number: number;
        label: string | null;
        status?: 'planned' | 'active' | 'harvested' | 'cancelled';
        planned_planting_at: string | null;
        planted_at: string | null;
        hst: number | null;
        farm: { id: string; name: string };
        block: { id: string; name: string };
        commodity?: { id: string; name: string } | null;
        variety: { id: string; name: string };
    };
};

export type PetugasTaskFilters = {
    q?: string | null;
    block_id?: string | null;
    subblok_id?: string | null;
    per_page?: number;
    status: 'all' | 'active' | PetugasTaskUrgency;
    view?: 'running' | 'history';
    category?: PetugasTaskCategory | null;
};

export type PetugasDefaultSop = {
    id: string;
    name: string;
    is_active: boolean;
    commodity: { id: string; name: string } | null;
    published_version: {
        id: string;
        version: number;
        published_at: string | null;
    } | null;
    tasks_count: number;
    is_usable: boolean;
};

export type PetugasActivityPageProps = {
    farm: { id: string; name: string } | null;
    defaultSop: PetugasDefaultSop | null;
};

export type PetugasTaskPageProps = {
    tasks: {
        data: PetugasTask[];
        total: number;
        from: number | null;
        to: number | null;
        current_page: number;
        last_page: number;
    } | null;
    options: {
        blocks: { id: string; name: string }[];
        subbloks: {
            id: string;
            block_id: string;
            label: string | null;
            number: number;
        }[];
        categories: PetugasTaskCategory[];
    };
    filters: PetugasTaskFilters;
    summary: Record<PetugasTaskUrgency, number>;
    farm: { id: string; name: string } | null;
    defaultSopMissing: boolean;
};

export type TaskActivityField = {
    key: string;
    label: string;
    type: 'text' | 'number' | 'select';
    optional?: boolean;
    max?: number;
    step?: string;
    options?: string[];
};

export type TaskActivityReport = {
    id: string;
    performer_name: string | null;
    work_date: string | null;
    worker_count: number | null;
    notes: string | null;
    details: Record<string, string | number | null> | null;
    photos: { id: string; url: string; available: boolean }[];
};
