export type DailyHeader = {
    id: string;
    farm_id: string;
    work_date: string;
    recorded_by: string;
    recorder_name?: string | null;
};

export type DailyReportActivity = {
        id: string;
        name: string;
        position: number | null;
        worker_count: number | null;
        performed_by: string;
        performer_name: string | null;
        subblok: string | null;
        block: string | null;
        notes: string | null;
        task_id: string | null;
        details: { label: string; value: string | number }[];
        photos: { id: string; url: string }[];
};

export type DailyReport = DailyHeader & {
    activities: DailyReportActivity[];
};
