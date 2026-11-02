export type PetugasTaskStatus =
    | 'pending'
    | 'in_progress'
    | 'submitted'
    | 'completed'
    | 'skipped'
    | 'cancelled';

export type PetugasTaskUrgency = 'overdue' | 'today' | 'upcoming' | 'completed';

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
    stage_code: string;
    activity_type: { id: string; code: string; name: string };
    offset_days: number;
    scheduled_date: string | null;
    due_date: string | null;
    is_required: boolean;
    linked_activities_count: number;
    latest_submission: PetugasTaskSubmission | null;
    can: {
        start: boolean;
        submit: boolean;
    };
    batch: {
        id: string;
        batch_number: number;
        subblock_label: string | null;
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

export type PetugasTaskPageProps = {
    tasks: PetugasTask[];
    filters: PetugasTaskFilters;
    isFixture: boolean;
};
