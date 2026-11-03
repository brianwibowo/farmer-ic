export type PetugasTaskStatus =
    | 'pending'
    | 'in_progress'
    | 'submitted'
    | 'completed'
    | 'skipped'
    | 'cancelled';

export type PetugasTaskUrgency = 'overdue' | 'today' | 'upcoming' | 'completed';

export type PetugasTaskCategory = 'maintenance' | 'harvest';

export type PetugasTaskSubcategory =
    | 'irrigation'
    | 'weeding'
    | 'follow_up_fertilization'
    | 'growth_check'
    | 'pest_control';

export type PetugasTaskSubmission = {
    status: 'submitted' | 'approved' | 'rejected';
    submitted_at: string | null;
    review_note: string | null;
};

export type PetugasTask = {
    id: string;
    name: string;
    instructions: string | null;
    status: PetugasTaskStatus;
    urgency: PetugasTaskUrgency;
    activity_category: PetugasTaskCategory;
    activity_subcategory: PetugasTaskSubcategory | null;
    offset_days: number;
    scheduled_date: string | null;
    due_date: string | null;
    is_required: boolean;
    linked_activities_count: number;
    latest_submission: PetugasTaskSubmission | null;
    can: {
        start: boolean;
        submit: boolean;
        record: boolean;
    };
    subblok: {
        id: string;
        number: number;
        label: string | null;
        planned_planting_at: string | null;
        planted_at: string | null;
        hst: number | null;
        farm: { id: string; name: string };
        block: { id: string; name: string };
        variety: { id: string; name: string };
    };
};

export type PetugasTaskFilters = {
    status: 'all' | PetugasTaskUrgency;
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
    tasks: PetugasTask[];
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
    work_date: string;
    start_time: string;
    end_time: string;
    worker_count: number;
    notes: string | null;
    details: Record<string, string | number | null>;
    photos: { id: string; url: string }[];
};
