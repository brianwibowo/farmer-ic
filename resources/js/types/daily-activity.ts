export type DailyHeader = {
    id: string;
    farm_id: string;
    work_date: string;
    start_time: string;
    end_time: string;
    worker_count: number;
    notes: string | null;
    recorded_by: string;
    recorder_name?: string | null;
};

export type DailyReport = DailyHeader & {
    activities: {
        id: string;
        name: string;
        position: number;
        performed_by: string;
        performer_name: string | null;
        subblok: string | null;
        block: string | null;
        notes: string | null;
        task_id: string | null;
        details: { label: string; value: string | number }[];
        photos: { id: string; url: string }[];
    }[];
};
