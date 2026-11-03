import {
    sopActivityCategoryLabels,
    sopActivitySubcategoryLabels,
    type SopActivityCategory,
    type SopActivitySubcategory,
} from '@/types/sop';

export type AdminTaskStatus =
    | 'pending'
    | 'in_progress'
    | 'submitted'
    | 'completed'
    | 'skipped'
    | 'cancelled';

export type AdminTaskUrgency = 'overdue' | 'today' | 'upcoming' | 'completed';

export type AdminTask = {
    id: string;
    name: string;
    instructions: string | null;
    status: AdminTaskStatus;
    urgency: AdminTaskUrgency;
    activity_category: SopActivityCategory;
    activity_subcategory: SopActivitySubcategory | null;
    offset_days: number;
    scheduled_date: string | null;
    due_date: string | null;
    effective_scheduled_date: string | null;
    rescheduled_date: string | null;
    reschedule_reason: string | null;
    rescheduled_at: string | null;
    skip_reason: string | null;
    cancel_reason: string | null;
    is_required: boolean;
    completed_at: string | null;
    created_at: string | null;
    farm: { id: string; name: string } | null;
    block: { id: string; code: string; name: string } | null;
    subblok: { id: string; number: number; label: string | null } | null;
    stage: { code: string } | null;
    sop_version: { id: string; version: number } | null;
    can: { update: boolean };
};

export type AdminTaskPage = {
    data: AdminTask[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type AdminTaskFilters = {
    search?: string;
    block_id?: string;
    subblok_id?: string;
    status?: string;
    category?: string;
    per_page?: number;
};

export type AdminTaskBlockOption = {
    id: string;
    farm_id: string;
    code: string;
    name: string;
    farm_name: string | null;
};

export type AdminTaskSubblokOption = {
    id: string;
    block_id: string | null;
    number: number;
    label: string | null;
    block_name: string | null;
};

export type AdminTaskSummary = {
    total: number;
    open: number;
    overdue: number;
    today: number;
    completed: number;
};

export const adminTaskStatusLabels: Record<AdminTaskStatus, string> = {
    pending: 'Belum dimulai',
    in_progress: 'Sedang dikerjakan',
    submitted: 'Menunggu review',
    completed: 'Selesai',
    skipped: 'Dilewati',
    cancelled: 'Dibatalkan',
};

export const adminTaskUrgencyLabels: Record<AdminTaskUrgency, string> = {
    overdue: 'Terlambat',
    today: 'Hari ini',
    upcoming: 'Mendatang',
    completed: 'Selesai',
};

export const adminTaskStatusOptions = Object.entries(adminTaskStatusLabels) as [
    AdminTaskStatus,
    string,
][];

export const adminTaskCategoryOptions = Object.entries(sopActivityCategoryLabels) as [
    SopActivityCategory,
    string,
][];

export function adminTaskCategoryLabel(task: Pick<AdminTask, 'activity_category'>): string {
    return sopActivityCategoryLabels[task.activity_category] ?? task.activity_category;
}

export function adminTaskSubcategoryLabel(
    task: Pick<AdminTask, 'activity_subcategory'>,
): string {
    return task.activity_subcategory
        ? (sopActivitySubcategoryLabels[task.activity_subcategory] ??
              task.activity_subcategory)
        : '-';
}

export function adminTaskStatusClass(status: AdminTaskStatus): string {
    return {
        pending: 'bg-muted text-muted-foreground',
        in_progress: 'bg-warning/10 text-warning',
        submitted: 'bg-info/10 text-info',
        completed: 'bg-success/10 text-success',
        skipped: 'bg-muted text-muted-foreground',
        cancelled: 'bg-error/10 text-error',
    }[status];
}

export function adminTaskUrgencyClass(urgency: AdminTaskUrgency): string {
    return {
        overdue: 'bg-error/10 text-error',
        today: 'bg-warning/10 text-warning',
        upcoming: 'bg-muted text-muted-foreground',
        completed: 'bg-success/10 text-success',
    }[urgency];
}

export function adminTaskDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value))
        : '-';
}

export function adminTaskHst(offset: number): string {
    return `H${offset >= 0 ? '+' : ''}${offset}`;
}
