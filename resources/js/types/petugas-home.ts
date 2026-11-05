import type { PetugasTaskStatus } from '@/types/petugas-task';
import type { SopActivityCategory, SopActivitySubcategory } from '@/types/sop';

export type PetugasHomeTask = {
    id: string;
    name: string;
    instructions: string | null;
    status: PetugasTaskStatus;
    is_required: boolean;
    offset_days: number;
    activity_category: SopActivityCategory;
    activity_subcategory: SopActivitySubcategory | null;
    scheduled_date: string;
    due_date: string | null;
    stage: string | null;
    batch_number: number | null;
    subblock_label: string | null;
    block_name: string | null;
    hst: number | null;
};

export type PetugasHome = {
    date: string;
    farm: {
        id: string;
        name: string;
        city: string | null;
        district: string | null;
        province: string | null;
    } | null;
    summary: { pre_planting: number; planting: number; harvest: number };
    task_summary: { total: number; completed: number };
    tasks: {
        data: PetugasHomeTask[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};
